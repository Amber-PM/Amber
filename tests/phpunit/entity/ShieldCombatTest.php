<?php

declare(strict_types=1);

namespace pocketmine\entity;

use PHPUnit\Framework\TestCase;
use pocketmine\entity\effect\EffectManager;
use pocketmine\event\entity\EntityDamageByChildEntityEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\inventory\ArmorInventory;
use pocketmine\inventory\PlayerEnderInventory;
use pocketmine\inventory\PlayerInventory;
use pocketmine\inventory\PlayerOffHandInventory;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use ReflectionClass;
use ReflectionProperty;

class ShieldCombatTest extends TestCase{

	private function createHuman(float $yaw = 0.0, Vector3 $pos = null) : Human{
		static $idCounter = 1;
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
		(new ReflectionProperty(Living::class, "sneaking"))->setValue($human, false);
		(new ReflectionProperty(Entity::class, "closed"))->setValue($human, true);
		return $human;
	}

	public function testFacingAttackMath() : void{
		$human = $this->createHuman(0.0, new Vector3(0, 0, 0)); // Facing South (towards +Z)

		// Attacker directly in front at (0, 0, 5) -> Frontal (0 deg diff) -> true
		self::assertTrue($human->isFacingAttack(new Vector3(0, 0, 5), new Vector3(0, 0, 0), 0.0));

		// Attacker diagonally front-left at (3, 0, 3) -> ~45 deg diff -> true
		self::assertTrue($human->isFacingAttack(new Vector3(3, 0, 3), new Vector3(0, 0, 0), 0.0));

		// Attacker directly behind at (0, 0, -5) -> Rear (180 deg diff) -> false
		self::assertFalse($human->isFacingAttack(new Vector3(0, 0, -5), new Vector3(0, 0, 0), 0.0));

		// Attacker behind-left at (3, 0, -3) -> ~135 deg diff -> false
		self::assertFalse($human->isFacingAttack(new Vector3(3, 0, -3), new Vector3(0, 0, 0), 0.0));

		// Target facing North (yaw = 180), attacker at (0, 0, -5) is in front -> true
		self::assertTrue($human->isFacingAttack(new Vector3(0, 0, -5), new Vector3(0, 0, 0), 180.0));
	}

	private function createDamageByEntityEvent(Entity $damager, Entity $victim, float $damage = 2.0, int $cause = EntityDamageEvent::CAUSE_ENTITY_ATTACK) : EntityDamageByEntityEvent{
		$ev = $this->getMockBuilder(EntityDamageByEntityEvent::class)
			->disableOriginalConstructor()
			->onlyMethods(["getDamager"])
			->getMock();
		$ev->method("getDamager")->willReturn($damager);
		(new ReflectionProperty(EntityDamageEvent::class, "entity"))->setValue($ev, $victim);
		(new ReflectionProperty(EntityDamageEvent::class, "cause"))->setValue($ev, $cause);
		(new ReflectionProperty(EntityDamageEvent::class, "baseDamage"))->setValue($ev, $damage);
		(new ReflectionProperty(EntityDamageEvent::class, "originalBase"))->setValue($ev, $damage);
		(new ReflectionProperty(EntityDamageEvent::class, "modifiers"))->setValue($ev, []);
		(new ReflectionProperty(EntityDamageEvent::class, "originals"))->setValue($ev, []);
		(new ReflectionProperty(EntityDamageByEntityEvent::class, "knockBack"))->setValue($ev, Living::DEFAULT_KNOCKBACK_FORCE);
		(new ReflectionProperty(EntityDamageByEntityEvent::class, "verticalKnockBackLimit"))->setValue($ev, Living::DEFAULT_KNOCKBACK_VERTICAL_LIMIT);
		return $ev;
	}

	public function testMeleeDamageAbsorptionWhenBlocking() : void{
		$defender = $this->createHuman(0.0, new Vector3(0, 0, 0));
		$defender->getOffHandInventory()->setItem(0, VanillaItems::SHIELD());
		$defender->setSneaking(true);
		$defender->updateBlockingState();
		self::assertTrue($defender->isBlocking());

		$attacker = $this->createHuman(180.0, new Vector3(0, 0, 3)); // In front of defender

		$event = $this->createDamageByEntityEvent($attacker, $defender, 10.0, EntityDamageEvent::CAUSE_ENTITY_ATTACK);
		$defender->applyDamageModifiers($event);

		self::assertSame(-10.0, $event->getModifier(EntityDamageEvent::MODIFIER_SHIELD));
		self::assertEquals(0.0, $event->getFinalDamage());
	}

	public function testMeleeDamageNotAbsorbedFromRear() : void{
		$defender = $this->createHuman(0.0, new Vector3(0, 0, 0));
		$defender->getOffHandInventory()->setItem(0, VanillaItems::SHIELD());
		$defender->setSneaking(true);
		$defender->updateBlockingState();
		self::assertTrue($defender->isBlocking());

		$attacker = $this->createHuman(0.0, new Vector3(0, 0, -3)); // Behind defender

		$event = $this->createDamageByEntityEvent($attacker, $defender, 10.0, EntityDamageEvent::CAUSE_ENTITY_ATTACK);
		$defender->applyDamageModifiers($event);

		self::assertSame(0.0, $event->getModifier(EntityDamageEvent::MODIFIER_SHIELD));
		self::assertEquals(10.0, $event->getFinalDamage());
	}

	public function testUnblockableDamageCausesBypassShield() : void{
		$defender = $this->createHuman(0.0, new Vector3(0, 0, 0));
		$defender->getOffHandInventory()->setItem(0, VanillaItems::SHIELD());
		$defender->setSneaking(true);
		$defender->updateBlockingState();

		$attacker = $this->createHuman(180.0, new Vector3(0, 0, 3));

		// Magic damage cannot be blocked
		$event = $this->createDamageByEntityEvent($attacker, $defender, 8.0, EntityDamageEvent::CAUSE_MAGIC);
		$defender->applyDamageModifiers($event);
		self::assertSame(0.0, $event->getModifier(EntityDamageEvent::MODIFIER_SHIELD));
		self::assertEquals(8.0, $event->getFinalDamage());

		// Fall damage cannot be blocked
		$fallEvent = new EntityDamageEvent($defender, EntityDamageEvent::CAUSE_FALL, 5.0);
		$defender->applyDamageModifiers($fallEvent);
		self::assertSame(0.0, $fallEvent->getModifier(EntityDamageEvent::MODIFIER_SHIELD));
		self::assertEquals(5.0, $fallEvent->getFinalDamage());
	}

	public function testProjectileDeflectionWhenBlocking() : void{
		$defender = $this->createHuman(0.0, new Vector3(0, 0, 0));
		$defender->getOffHandInventory()->setItem(0, VanillaItems::SHIELD());
		$defender->setSneaking(true);
		$defender->updateBlockingState();
		self::assertTrue($defender->isBlocking());

		$shooter = $this->createHuman(180.0, new Vector3(0, 0, 10));

		$projectile = $this->getMockBuilder(\pocketmine\entity\projectile\Arrow::class)
			->disableOriginalConstructor()
			->onlyMethods(["getPosition", "getMotion", "setMotion"])
			->getMock();
		(new ReflectionProperty(Entity::class, "location"))->setValue($projectile, new Location(0.0, 0.0, 2.0, $defender->getWorld(), 0.0, 0.0));
		(new ReflectionProperty(Entity::class, "closed"))->setValue($projectile, true);
		$projectile->method("getPosition")->willReturn(new \pocketmine\world\Position(0.0, 0.0, 2.0, $defender->getWorld()));
		$motion = new Vector3(0, 0, -2);
		$projectile->method("getMotion")->willReturn($motion);
		$setMotionCalled = false;
		$projectile->method("setMotion")->willReturnCallback(function(Vector3 $newMotion) use (&$setMotionCalled) : bool{
			$setMotionCalled = true;
			self::assertSame(1.0, $newMotion->z); // -2 * -0.5 = 1.0 (reversed)
			return true;
		});

		$ev = $this->getMockBuilder(EntityDamageByChildEntityEvent::class)
			->disableOriginalConstructor()
			->onlyMethods(["getDamager", "getChild"])
			->getMock();
		$ev->method("getDamager")->willReturn($shooter);
		$ev->method("getChild")->willReturn($projectile);
		(new ReflectionProperty(EntityDamageEvent::class, "entity"))->setValue($ev, $defender);
		(new ReflectionProperty(EntityDamageEvent::class, "cause"))->setValue($ev, EntityDamageEvent::CAUSE_PROJECTILE);
		(new ReflectionProperty(EntityDamageEvent::class, "baseDamage"))->setValue($ev, 6.0);
		(new ReflectionProperty(EntityDamageEvent::class, "originalBase"))->setValue($ev, 6.0);
		(new ReflectionProperty(EntityDamageEvent::class, "modifiers"))->setValue($ev, []);
		(new ReflectionProperty(EntityDamageEvent::class, "originals"))->setValue($ev, []);

		$defender->applyDamageModifiers($ev);
		self::assertSame(-6.0, $ev->getModifier(EntityDamageEvent::MODIFIER_SHIELD));
		self::assertEquals(0.0, $ev->getFinalDamage());

		// Test post-damage effects (projectile reflection and block sound)
		(new ReflectionClass(Human::class))->getMethod("applyPostDamageEffects")->invoke($defender, $ev);
		self::assertTrue($setMotionCalled);
	}
}
