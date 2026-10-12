<?php

declare(strict_types=1);

namespace pocketmine\entity;

use PHPUnit\Framework\TestCase;
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\effect\EffectManager;
use pocketmine\entity\projectile\Arrow;
use pocketmine\entity\projectile\Projectile;
use pocketmine\entity\projectile\Trident;
use pocketmine\event\entity\EntityDamageByChildEntityEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\inventory\ArmorInventory;
use pocketmine\inventory\PlayerEnderInventory;
use pocketmine\inventory\PlayerInventory;
use pocketmine\inventory\PlayerOffHandInventory;
use pocketmine\item\Armor;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\enchantment\VanillaEnchantments;
use pocketmine\item\Shield;
use pocketmine\item\VanillaItems;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\RayTraceResult;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\timings\Timings;
use pocketmine\world\World;
use pocketmine\world\sound\ShieldBlockSound;
use pocketmine\world\sound\Sound;
use ReflectionClass;
use ReflectionProperty;

class ShieldCombatTest extends TestCase{

	/** @var array<int, Entity> */
	private static array $entitiesById = [];
	private \pocketmine\world\World $testWorld;

	protected function setUp() : void{
		Timings::init();
		/** @var array<int, Entity> $empty */
		$empty = [];
		self::$entitiesById = $empty;
		$worldManager = $this->createMock(\pocketmine\world\WorldManager::class);
		$worldManager->method("findEntity")->willReturnCallback(function(int $entityId) : ?Entity{
			return array_key_exists($entityId, self::$entitiesById) ? self::$entitiesById[$entityId] : null;
		});
		$server = $this->createMock(\pocketmine\Server::class);
		$server->method("getWorldManager")->willReturn($worldManager);
		$world = $this->createMock(\pocketmine\world\World::class);
		$world->method("isLoaded")->willReturn(true);
		$world->method("getServer")->willReturn($server);
		$this->testWorld = $world;
	}

	private function createHuman(float $yaw = 0.0, Vector3 $pos = null) : Human{
		static $idCounter = 1;
		$id = $idCounter++;
		$human = (new ReflectionClass(Human::class))->newInstanceWithoutConstructor();
		(new ReflectionProperty(Entity::class, "id"))->setValue($human, $id);
		self::$entitiesById[$id] = $human;
		(new ReflectionProperty(Entity::class, "networkProperties"))->setValue($human, new EntityMetadataCollection());
		(new ReflectionProperty(Entity::class, "size"))->setValue($human, new EntitySizeInfo(1.8, 0.6, 1.62));
		(new ReflectionProperty(Entity::class, "scale"))->setValue($human, 1.0);
		$pos = $pos ?? Vector3::zero();
		(new ReflectionProperty(Entity::class, "location"))->setValue($human, new Location($pos->x, $pos->y, $pos->z, $this->testWorld, $yaw, 0.0));
		(new ReflectionProperty(Entity::class, "attributeMap"))->setValue($human, new AttributeMap());
		(new ReflectionClass(Human::class))->getMethod("addAttributes")->invoke($human);
		(new ReflectionProperty(Living::class, "effectManager"))->setValue($human, new EffectManager($human));
		(new ReflectionProperty(Living::class, "armorInventory"))->setValue($human, new ArmorInventory($human));
		(new ReflectionProperty(Human::class, "inventory"))->setValue($human, new PlayerInventory($human));
		(new ReflectionProperty(Human::class, "offHandInventory"))->setValue($human, new PlayerOffHandInventory($human));
		(new ReflectionProperty(Human::class, "enderInventory"))->setValue($human, new PlayerEnderInventory($human));
		(new ReflectionProperty(Living::class, "sneaking"))->setValue($human, false);
		(new ReflectionProperty(Entity::class, "motion"))->setValue($human, Vector3::zero());
		(new ReflectionProperty(Entity::class, "closed"))->setValue($human, false);
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

	private function createArrow(Vector3 $pos, Vector3 $motion) : Arrow{
		static $arrowIdCounter = 500;
		$id = $arrowIdCounter++;
		$arrow = (new ReflectionClass(Arrow::class))->newInstanceWithoutConstructor();
		(new ReflectionProperty(Entity::class, "id"))->setValue($arrow, $id);
		self::$entitiesById[$id] = $arrow;
		(new ReflectionProperty(Entity::class, "networkProperties"))->setValue($arrow, new EntityMetadataCollection());
		(new ReflectionProperty(Entity::class, "size"))->setValue($arrow, new EntitySizeInfo(0.25, 0.25));
		(new ReflectionProperty(Entity::class, "location"))->setValue($arrow, new Location($pos->x, $pos->y, $pos->z, $this->testWorld, 0.0, 0.0));
		(new ReflectionProperty(Entity::class, "motion"))->setValue($arrow, clone $motion);
		(new ReflectionProperty(Entity::class, "boundingBox"))->setValue($arrow, new \pocketmine\math\AxisAlignedBB(0, 0, 0, 0, 0, 0));
		(new ReflectionProperty(Entity::class, "closed"))->setValue($arrow, false);
		(new ReflectionProperty(Arrow::class, "critical"))->setValue($arrow, false);
		(new ReflectionProperty(Projectile::class, "damage"))->setValue($arrow, 4.0);
		return $arrow;
	}

	private function createTrident(Vector3 $pos, Vector3 $motion) : Trident{
		static $tridentIdCounter = 600;
		$id = $tridentIdCounter++;
		$trident = (new ReflectionClass(Trident::class))->newInstanceWithoutConstructor();
		(new ReflectionProperty(Entity::class, "id"))->setValue($trident, $id);
		self::$entitiesById[$id] = $trident;
		(new ReflectionProperty(Entity::class, "networkProperties"))->setValue($trident, new EntityMetadataCollection());
		(new ReflectionProperty(Entity::class, "size"))->setValue($trident, new EntitySizeInfo(0.25, 0.25));
		(new ReflectionProperty(Entity::class, "location"))->setValue($trident, new Location($pos->x, $pos->y, $pos->z, $this->testWorld, 0.0, 0.0));
		(new ReflectionProperty(Entity::class, "motion"))->setValue($trident, clone $motion);
		(new ReflectionProperty(Entity::class, "boundingBox"))->setValue($trident, new \pocketmine\math\AxisAlignedBB(0, 0, 0, 0, 0, 0));
		(new ReflectionProperty(Entity::class, "closed"))->setValue($trident, false);
		(new ReflectionProperty(Trident::class, "item"))->setValue($trident, VanillaItems::TRIDENT());
		(new ReflectionProperty(Trident::class, "canCollide"))->setValue($trident, true);
		(new ReflectionProperty(Projectile::class, "damage"))->setValue($trident, 8.0);
		return $trident;
	}

	public function testBlockedHitDoesNotDepleteAbsorption() : void{
		$defender = $this->createHuman(0.0, new Vector3(0, 0, 0));
		$defender->getOffHandInventory()->setItem(0, VanillaItems::SHIELD());
		$defender->setSneaking(true);
		$defender->updateBlockingState();
		$defender->setAbsorption(10.0);

		$attacker = $this->createHuman(180.0, new Vector3(0, 0, 3));
		$event = $this->createDamageByEntityEvent($attacker, $defender, 6.0, EntityDamageEvent::CAUSE_ENTITY_ATTACK);

		$defender->applyDamageModifiers($event);
		self::assertSame(-6.0, $event->getModifier(EntityDamageEvent::MODIFIER_SHIELD));
		self::assertFalse($event->isApplicable(EntityDamageEvent::MODIFIER_ABSORPTION));
		self::assertEquals(0.0, $event->getFinalDamage());

		(new ReflectionClass(Human::class))->getMethod("applyPostDamageEffects")->invoke($defender, $event);
		self::assertSame(10.0, $defender->getAbsorption());
		$shield = $defender->getOffHandInventory()->getItem(0);
		self::assertInstanceOf(Shield::class, $shield);
		self::assertSame(6, $shield->getDamage());
	}

	public function testBlockedHitDoesNotDamageArmor() : void{
		$defender = $this->createHuman(0.0, new Vector3(0, 0, 0));
		$defender->getOffHandInventory()->setItem(0, VanillaItems::SHIELD());
		$defender->setSneaking(true);
		$defender->updateBlockingState();
		$chestplate = VanillaItems::DIAMOND_CHESTPLATE();
		$defender->getArmorInventory()->setChestplate($chestplate);

		$attacker = $this->createHuman(180.0, new Vector3(0, 0, 3));
		$event = $this->createDamageByEntityEvent($attacker, $defender, 12.0, EntityDamageEvent::CAUSE_ENTITY_ATTACK);

		$defender->applyDamageModifiers($event);
		(new ReflectionClass(Human::class))->getMethod("applyPostDamageEffects")->invoke($defender, $event);

		$chestplateItem = $defender->getArmorInventory()->getChestplate();
		self::assertInstanceOf(Armor::class, $chestplateItem);
		self::assertSame(0, $chestplateItem->getDamage());
		$shield = $defender->getOffHandInventory()->getItem(0);
		self::assertInstanceOf(Shield::class, $shield);
		self::assertSame(12, $shield->getDamage());
	}

	public function testBlockedHitDoesNotTriggerThorns() : void{
		$defender = $this->createHuman(0.0, new Vector3(0, 0, 0));
		$defender->getOffHandInventory()->setItem(0, VanillaItems::SHIELD());
		$defender->setSneaking(true);
		$defender->updateBlockingState();
		$chestplate = VanillaItems::DIAMOND_CHESTPLATE();
		$chestplate->addEnchantment(new EnchantmentInstance(VanillaEnchantments::THORNS(), 3));
		$defender->getArmorInventory()->setChestplate($chestplate);

		$attacker = $this->createHuman(180.0, new Vector3(0, 0, 3));
		$attacker->setHealth(20.0);
		$event = $this->createDamageByEntityEvent($attacker, $defender, 10.0, EntityDamageEvent::CAUSE_ENTITY_ATTACK);

		$defender->applyDamageModifiers($event);
		(new ReflectionClass(Human::class))->getMethod("applyPostDamageEffects")->invoke($defender, $event);

		$chestplateItem = $defender->getArmorInventory()->getChestplate();
		self::assertInstanceOf(Armor::class, $chestplateItem);
		self::assertSame(0, $chestplateItem->getDamage());
		self::assertSame(20.0, $attacker->getHealth());
	}

	public function testBlockedHitWithAbsorptionStillAppliesShieldDurabilityLoss() : void{
		$defender = $this->createHuman(0.0, new Vector3(0, 0, 0));
		$defender->getOffHandInventory()->setItem(0, VanillaItems::SHIELD());
		$defender->setSneaking(true);
		$defender->updateBlockingState();
		$defender->setAbsorption(20.0);

		$attacker = $this->createHuman(180.0, new Vector3(0, 0, 3));
		$event = $this->createDamageByEntityEvent($attacker, $defender, 8.0, EntityDamageEvent::CAUSE_ENTITY_ATTACK);

		$defender->applyDamageModifiers($event);
		(new ReflectionClass(Human::class))->getMethod("applyPostDamageEffects")->invoke($defender, $event);

		$shield = $defender->getOffHandInventory()->getItem(0);
		self::assertInstanceOf(Shield::class, $shield);
		self::assertSame(8, $shield->getDamage());
		self::assertSame(20.0, $defender->getAbsorption());
	}

	public function testBlockedHitWithAbsorptionStillDisablesShieldOnAxeAttack() : void{
		$defender = $this->createHuman(0.0, new Vector3(0, 0, 0));
		$defender->getOffHandInventory()->setItem(0, VanillaItems::SHIELD());
		$defender->setSneaking(true);
		$defender->updateBlockingState();
		$defender->setAbsorption(20.0);

		$attacker = $this->createHuman(180.0, new Vector3(0, 0, 3));
		$attacker->getInventory()->setItemInHand(VanillaItems::DIAMOND_AXE());
		$event = $this->createDamageByEntityEvent($attacker, $defender, 9.0, EntityDamageEvent::CAUSE_ENTITY_ATTACK);

		$defender->applyDamageModifiers($event);
		(new ReflectionClass(Human::class))->getMethod("applyPostDamageEffects")->invoke($defender, $event);

		self::assertFalse($defender->isBlocking());
		self::assertSame(20.0, $defender->getAbsorption());
	}

	public function testArrowDeflectedOnBlockedHit() : void{
		$defender = $this->createHuman(0.0, new Vector3(0, 0, 0));
		$defender->getOffHandInventory()->setItem(0, VanillaItems::SHIELD());
		$defender->setSneaking(true);
		$defender->updateBlockingState();
		self::assertTrue($defender->isBlocking());

		$arrow = $this->createArrow(new Vector3(0, 0, 2), new Vector3(0, 0, -2));

		$rayTrace = $this->createMock(RayTraceResult::class);
		(new ReflectionClass(Arrow::class))->getMethod("onHitEntity")->invoke($arrow, $defender, $rayTrace);

		self::assertTrue($arrow->isHitBlocked());
		self::assertSame(1.0, $arrow->getMotion()->z); // -2 * -0.5 = 1.0 (reversed)
		self::assertFalse($arrow->isFlaggedForDespawn());
	}

	public function testDeflectedArrowHitsStationaryShieldOnlyOnce() : void{
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);
		$world->method("getServer")->willReturn($this->testWorld->getServer());
		$world->method("getBlockAt")->willReturn(VanillaBlocks::AIR());
		$defender = $this->createHuman(180.0);
		(new ReflectionProperty(Entity::class, "location"))->setValue($defender, new Location(0.5, 0.0, 0.5, $world, 180.0, 0.0));
		(new ReflectionProperty(Entity::class, "boundingBox"))->setValue($defender, new AxisAlignedBB(0, 0, 0, 1, 2, 1));
		$defender->getOffHandInventory()->setItem(0, VanillaItems::SHIELD());
		$defender->setSneaking(true);
		$defender->updateBlockingState();
		$world->method("getCollidingEntities")->willReturnCallback(function(AxisAlignedBB $bb, ?Entity $entity = null) use ($defender){
			return $entity !== null && $entity->canCollideWith($defender) ? [$defender] : [];
		});
		$blocks = 0;
		$world->method("addSound")->willReturnCallback(function(Vector3 $position, Sound $sound) use (&$blocks) : void{
			if($sound instanceof ShieldBlockSound){
				++$blocks;
			}
		});
		$arrow = new Arrow(new Location(0.5, 1.0, -5.0, $world, 0.0, 0.0), null, false);
		self::$entitiesById[$arrow->getId()] = $arrow;
		$arrow->setMotion(new Vector3(0.0, 0.0, 6.0));

		try{
			$arrow->onUpdate(1);
			self::assertTrue($arrow->isHitBlocked());
			self::assertSame(1, $blocks);
			$shield = $defender->getOffHandInventory()->getItem(0);
			self::assertInstanceOf(Shield::class, $shield);
			$damage = $shield->getDamage();
			self::assertGreaterThan(0, $damage);
			for($tick = 2; $tick <= 10; ++$tick){
				$arrow->onUpdate($tick);
			}
			self::assertSame(1, $blocks);
			$shield = $defender->getOffHandInventory()->getItem(0);
			self::assertInstanceOf(Shield::class, $shield);
			self::assertSame($damage, $shield->getDamage());
			self::assertSame(20.0, $defender->getHealth());
			self::assertLessThan(-0.3, $arrow->getPosition()->z);
		}finally{
			(new ReflectionProperty(Entity::class, "closed"))->setValue($arrow, true);
			(new ReflectionProperty(Entity::class, "closed"))->setValue($defender, true);
		}
	}

	public function testCancelledTridentHitContinuesMovingUntilBlockImpact() : void{
		$target = $this->createHuman();
		(new ReflectionProperty(Entity::class, "boundingBox"))->setValue($target, new AxisAlignedBB(0, 0, 0, 1, 2, 1));
		(new ReflectionProperty(Living::class, "noDamageTicks"))->setValue($target, 20);
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);
		$world->method("getServer")->willReturn($this->testWorld->getServer());
		$world->method("getBlockAt")->willReturnCallback(function(int $x, int $y, int $z) use ($world){
			$block = $y < 0 ? VanillaBlocks::STONE() : VanillaBlocks::AIR();
			$block->position($world, $x, $y, $z);
			return $block;
		});
		$world->method("getCollidingEntities")->willReturnCallback(function(AxisAlignedBB $bb, ?Entity $entity = null) use ($target){
			return $entity !== null && $entity->canCollideWith($target) ? [$target] : [];
		});
		$trident = new Trident(new Location(0.5, 1.0, -5.0, $world, 0.0, 0.0), VanillaItems::TRIDENT(), null);
		$trident->setMotion(new Vector3(0.0, 0.0, 6.0));

		try{
			$trident->onUpdate(1);
			self::assertTrue($trident->isHitCancelled());
			self::assertSame(20.0, $target->getHealth());
			self::assertTrue($trident->hasMovementUpdate());
			$position = $trident->getPosition()->asVector3();
			$blockHit = new ReflectionProperty(Projectile::class, "blockHit");
			for($tick = 2; $tick <= 30 && $blockHit->getValue($trident) === null; ++$tick){
				$trident->onUpdate($tick);
			}
			self::assertFalse($trident->getPosition()->asVector3()->equals($position));
			self::assertNotNull($blockHit->getValue($trident));
			self::assertFalse($trident->isFlaggedForDespawn());
		}finally{
			(new ReflectionProperty(Entity::class, "closed"))->setValue($trident, true);
			(new ReflectionProperty(Entity::class, "closed"))->setValue($target, true);
		}
	}

	public function testTridentDeflectedOnBlockedHit() : void{
		$defender = $this->createHuman(0.0, new Vector3(0, 0, 0));
		$defender->getOffHandInventory()->setItem(0, VanillaItems::SHIELD());
		$defender->setSneaking(true);
		$defender->updateBlockingState();
		self::assertTrue($defender->isBlocking());

		$trident = $this->createTrident(new Vector3(0, 0, 2), new Vector3(0, 0, -2));

		$rayTrace = $this->createMock(RayTraceResult::class);
		(new ReflectionClass(Trident::class))->getMethod("onHitEntity")->invoke($trident, $defender, $rayTrace);

		self::assertTrue($trident->isHitBlocked());
		self::assertSame(1.0, $trident->getMotion()->z); // -2 * -0.5 = 1.0 (reversed, NOT -0.01 / -0.1)
		$canCollideProp = (new ReflectionClass(Trident::class))->getProperty("canCollide");
		$canCollideProp->setAccessible(true);
		self::assertTrue($canCollideProp->getValue($trident)); // collision not disabled
	}
}
