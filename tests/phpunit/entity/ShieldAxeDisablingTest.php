<?php

declare(strict_types=1);

namespace pocketmine\entity;

use PHPUnit\Framework\TestCase;
use pocketmine\entity\effect\EffectManager;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\inventory\ArmorInventory;
use pocketmine\inventory\PlayerEnderInventory;
use pocketmine\inventory\PlayerInventory;
use pocketmine\inventory\PlayerOffHandInventory;
use pocketmine\item\Axe;
use pocketmine\item\Shield;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use ReflectionClass;
use ReflectionProperty;

class ShieldAxeDisablingTest extends TestCase{

	private function createHuman(float $yaw = 0.0, Vector3 $pos = null) : Human{
		static $idCounter = 100;
		$human = (new ReflectionClass(Human::class))->newInstanceWithoutConstructor();
		(new ReflectionProperty(Entity::class, "id"))->setValue($human, $idCounter++);
		(new ReflectionProperty(Entity::class, "networkProperties"))->setValue($human, new EntityMetadataCollection());
		(new ReflectionProperty(Entity::class, "size"))->setValue($human, new EntitySizeInfo(1.8, 0.6, 1.62));
		(new ReflectionProperty(Entity::class, "scale"))->setValue($human, 1.0);
		$world = $this->createMock(\pocketmine\world\World::class);
		$world->method("isLoaded")->willReturn(true);
		$pos = $pos ?? Vector3::zero();
		(new ReflectionProperty(Entity::class, "location"))->setValue($human, new Location($pos->x, $pos->y, $pos->z, $world, $yaw, 0.0));
		(new ReflectionProperty(Entity::class, "attributeMap"))->setValue($human, new AttributeMap());
		(new ReflectionClass(Human::class))->getMethod("addAttributes")->invoke($human);
		(new ReflectionProperty(Living::class, "effectManager"))->setValue($human, new EffectManager($human));
		(new ReflectionProperty(Living::class, "armorInventory"))->setValue($human, new ArmorInventory($human));
		(new ReflectionProperty(Human::class, "inventory"))->setValue($human, new PlayerInventory($human));
		(new ReflectionProperty(Human::class, "offHandInventory"))->setValue($human, new PlayerOffHandInventory($human));
		(new ReflectionProperty(Human::class, "enderInventory"))->setValue($human, new PlayerEnderInventory($human));
		$human->getInventory()->getHeldItemIndexChangeListeners()->add(fn(int $oldIndex) => $human->updateBlockingState());
		$human->getInventory()->getListeners()->add(\pocketmine\inventory\CallbackInventoryListener::onAnyChange(fn() => $human->updateBlockingState()));
		$human->getOffHandInventory()->getListeners()->add(\pocketmine\inventory\CallbackInventoryListener::onAnyChange(fn() => $human->updateBlockingState()));
		(new ReflectionProperty(Living::class, "sneaking"))->setValue($human, false);
		(new ReflectionProperty(Entity::class, "closed"))->setValue($human, true);
		return $human;
	}

	private function createDamageByEntityEvent(Entity $damager, Entity $victim, float $damage = 2.0) : EntityDamageByEntityEvent{
		$ev = $this->getMockBuilder(EntityDamageByEntityEvent::class)
			->disableOriginalConstructor()
			->onlyMethods(["getDamager"])
			->getMock();
		$ev->method("getDamager")->willReturn($damager);
		(new ReflectionProperty(EntityDamageEvent::class, "entity"))->setValue($ev, $victim);
		(new ReflectionProperty(EntityDamageEvent::class, "cause"))->setValue($ev, EntityDamageEvent::CAUSE_ENTITY_ATTACK);
		(new ReflectionProperty(EntityDamageEvent::class, "baseDamage"))->setValue($ev, $damage);
		(new ReflectionProperty(EntityDamageEvent::class, "originalBase"))->setValue($ev, $damage);
		(new ReflectionProperty(EntityDamageEvent::class, "modifiers"))->setValue($ev, []);
		(new ReflectionProperty(EntityDamageEvent::class, "originals"))->setValue($ev, []);
		(new ReflectionProperty(EntityDamageByEntityEvent::class, "knockBack"))->setValue($ev, Living::DEFAULT_KNOCKBACK_FORCE);
		(new ReflectionProperty(EntityDamageByEntityEvent::class, "verticalKnockBackLimit"))->setValue($ev, Living::DEFAULT_KNOCKBACK_VERTICAL_LIMIT);
		return $ev;
	}

	public function testDurabilityLossCalculation() : void{
		self::assertSame(0, Human::calculateShieldDurabilityLoss(0.5));
		self::assertSame(0, Human::calculateShieldDurabilityLoss(2.9));
		self::assertSame(3, Human::calculateShieldDurabilityLoss(3.0));
		self::assertSame(4, Human::calculateShieldDurabilityLoss(4.99));
		self::assertSame(15, Human::calculateShieldDurabilityLoss(15.2));
	}

	public function testShieldTakesDamageOnBlockedAttack() : void{
		$defender = $this->createHuman(0.0, new Vector3(0, 0, 0));
		$defender->getOffHandInventory()->setItem(0, VanillaItems::SHIELD());
		$defender->setSneaking(true);
		self::assertTrue($defender->isBlocking());

		$attacker = $this->createHuman(180.0, new Vector3(0, 0, 3));

		// Attack with 5.0 damage
		$ev = $this->createDamageByEntityEvent($attacker, $defender, 5.0);
		$defender->applyDamageModifiers($ev);
		(new ReflectionClass(Human::class))->getMethod("applyPostDamageEffects")->invoke($defender, $ev);

		/** @var Shield $shield */
		$shield = $defender->getOffHandInventory()->getItem(0);
		self::assertInstanceOf(Shield::class, $shield);
		self::assertSame(5, $shield->getDamage());
	}

	public function testAxeDisablesShieldBlocking() : void{
		$defender = $this->createHuman(0.0, new Vector3(0, 0, 0));
		$defender->getOffHandInventory()->setItem(0, VanillaItems::SHIELD());
		$defender->setSneaking(true);
		self::assertTrue($defender->isBlocking());

		$attacker = $this->createHuman(180.0, new Vector3(0, 0, 3));
		$attacker->getInventory()->setItemInHand(VanillaItems::DIAMOND_AXE());
		self::assertInstanceOf(Axe::class, $attacker->getInventory()->getItemInHand());

		$ev = $this->createDamageByEntityEvent($attacker, $defender, 9.0);
		$defender->applyDamageModifiers($ev);
		self::assertSame(-9.0, $ev->getModifier(EntityDamageEvent::MODIFIER_SHIELD));

		(new ReflectionClass(Human::class))->getMethod("applyPostDamageEffects")->invoke($defender, $ev);

		// Shield must be disabled (blocking is now false)
		self::assertFalse($defender->isBlocking());
	}
}
