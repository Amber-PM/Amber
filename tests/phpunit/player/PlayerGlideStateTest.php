<?php

declare(strict_types=1);

namespace pocketmine\player;

use Logger;
use PHPUnit\Framework\TestCase;
use pocketmine\entity\AttributeMap;
use pocketmine\entity\effect\EffectManager;
use pocketmine\entity\Entity;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\ExperienceManager;
use pocketmine\entity\Human;
use pocketmine\entity\HungerManager;
use pocketmine\entity\Living;
use pocketmine\entity\Location;
use pocketmine\entity\object\Boat;
use pocketmine\inventory\ArmorInventory;
use pocketmine\inventory\PlayerInventory;
use pocketmine\inventory\PlayerOffHandInventory;
use pocketmine\item\BoatType;
use pocketmine\item\Elytra;
use pocketmine\item\VanillaItems;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\world\World;
use Ramsey\Uuid\Uuid;
use ReflectionClass;
use ReflectionProperty;

class PlayerGlideStateTest extends TestCase{

	public function testDisablingInactiveGlidePreservesNormalFallDamage() : void{
		$player = $this->createTestPlayer();
		$updateFallState = (new ReflectionClass(Player::class))->getMethod("updateFallState");
		try{
			$updateFallState->invoke($player, -8.0, false);
			self::assertSame(8.0, $player->getFallDistance());
			$player->setGliding(false);
			self::assertSame(8.0, $player->getFallDistance());
			$updateFallState->invoke($player, -1.0, true);
			self::assertSame(14.0, $player->getHealth());
		}finally{
			(new ReflectionProperty(Entity::class, "closed"))->setValue($player, true);
		}
	}

	public function testGlideTransitionsClearFallDistance() : void{
		$player = $this->createTestPlayer();
		$player->getArmorInventory()->setChestplate(VanillaItems::ELYTRA());
		try{
			$player->setFallDistance(8.0);
			$player->setGliding(true);
			self::assertSame(0.0, $player->getFallDistance());
			$player->setFallDistance(8.0);
			$player->setGliding(false);
			self::assertSame(0.0, $player->getFallDistance());
		}finally{
			(new ReflectionProperty(Entity::class, "closed"))->setValue($player, true);
		}
	}

	private function createVehicle(Player $player) : Boat{
		return new Boat(clone $player->getLocation(), BoatType::OAK);
	}

	public function testAirborneRiderCannotBeginGliding() : void{
		$player = $this->createTestPlayer();
		$player->getArmorInventory()->setChestplate(VanillaItems::ELYTRA());
		$vehicle = $this->createVehicle($player);
		try{
			self::assertTrue($vehicle->addRider($player));
			self::assertSame($vehicle, Boat::getVehicleOf($player));
			self::assertFalse($player->toggleGlide(true));
			self::assertFalse($player->isGliding());
		}finally{
			(new ReflectionProperty(Entity::class, "closed"))->setValue($player, true);
			$vehicle->close();
		}
	}

	public function testMountingStopsGlideAndRejectsRocketBoost() : void{
		$player = $this->createTestPlayer();
		$player->getArmorInventory()->setChestplate(VanillaItems::ELYTRA());
		$player->toggleGlide(true);
		$vehicle = $this->createVehicle($player);
		try{
			self::assertTrue($vehicle->addRider($player));
			self::assertFalse($player->boostGlideWithFirework(VanillaItems::FIREWORK_ROCKET()));
			(new ReflectionClass(Player::class))->getMethod("entityBaseTick")->invoke($player, 20);
			self::assertFalse($player->isGliding());
			self::assertSame(0, $player->getArmorInventory()->getChestplate()->getDamage());
			self::assertSame($vehicle, Boat::getVehicleOf($player));
		}finally{
			(new ReflectionProperty(Entity::class, "closed"))->setValue($player, true);
			$vehicle->close();
		}
	}

	public function testDeathResetsGlidingPose() : void{
		$player = $this->createTestPlayer();
		(new ReflectionProperty(Entity::class, "server"))->setValue($player, $this->createMock(\pocketmine\Server::class));
		(new ReflectionProperty(Player::class, "displayName"))->setValue($player, "GlideTester");
		$player->getArmorInventory()->setChestplate(VanillaItems::ELYTRA());
		$player->toggleGlide(true);
		try{
			$player->kill();
			self::assertFalse($player->isGliding());
			self::assertGreaterThan(0.6, $player->getBoundingBox()->getYLength());
		}finally{
			(new ReflectionProperty(Entity::class, "closed"))->setValue($player, true);
		}
	}

	public function testCombatDoesNotWearElytraButWearsArmor() : void{
		$player = $this->createTestPlayer();
		try{
			$player->getArmorInventory()->setChestplate(VanillaItems::ELYTRA());
			$player->attack(new \pocketmine\event\entity\EntityDamageEvent($player, \pocketmine\event\entity\EntityDamageEvent::CAUSE_ENTITY_ATTACK, 4));
			self::assertSame(16.0, $player->getHealth());
			self::assertSame(0, $player->getArmorInventory()->getChestplate()->getDamage());
			$player->getArmorInventory()->setChestplate(VanillaItems::DIAMOND_CHESTPLATE());
			$player->damageArmor(4);
			self::assertSame(1, $player->getArmorInventory()->getChestplate()->getDamage());
		}finally{
			(new ReflectionProperty(Entity::class, "closed"))->setValue($player, true);
		}
	}

	public function testWaterAtFeetPreventsGlideEvenWithEyesAboveWater() : void{
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);
		$world->method("getBlockAt")->willReturnCallback(fn(int $x, int $y, int $z) => $y === 10 ? \pocketmine\block\VanillaBlocks::WATER() : \pocketmine\block\VanillaBlocks::AIR());
		$player = $this->createTestPlayer($world);
		try{
			$player->getArmorInventory()->setChestplate(VanillaItems::ELYTRA());
			self::assertFalse($player->isUnderwater());
			self::assertFalse($player->toggleGlide(true));
		}finally{
			(new ReflectionProperty(Entity::class, "closed"))->setValue($player, true);
		}
	}

	public function testDelayedFlightTickPreservesDurabilityIntervals() : void{
		$player = $this->createTestPlayer();
		$player->getArmorInventory()->setChestplate(VanillaItems::ELYTRA());
		$player->toggleGlide(true);
		$tick = (new ReflectionClass(Player::class))->getMethod("entityBaseTick");
		try{
			$tick->invoke($player, 45);
			self::assertSame(2, $player->getArmorInventory()->getChestplate()->getDamage());
			$tick->invoke($player, 15);
			self::assertSame(3, $player->getArmorInventory()->getChestplate()->getDamage());
		}finally{
			(new ReflectionProperty(Entity::class, "closed"))->setValue($player, true);
		}
	}

	public function testSeparateFlightsDoNotShareDurabilityCounter() : void{
		$player = $this->createTestPlayer();
		$player->getArmorInventory()->setChestplate(VanillaItems::ELYTRA());
		$player->toggleGlide(true);
		$tick = (new ReflectionClass(Player::class))->getMethod("entityBaseTick");
		try{
			$tick->invoke($player, 19);
			$player->toggleGlide(false);
			$player->toggleGlide(true);
			$tick->invoke($player, 1);
			self::assertSame(0, $player->getArmorInventory()->getChestplate()->getDamage());
			$tick->invoke($player, 19);
			self::assertSame(1, $player->getArmorInventory()->getChestplate()->getDamage());
		}finally{
			(new ReflectionProperty(Entity::class, "closed"))->setValue($player, true);
		}
	}

	private function createTestPlayer(?World $world = null, ?Vector3 $pos = null) : Player{
		$session = $this->getMockBuilder(NetworkSession::class)->disableOriginalConstructor()->getMock();
		$player = $this->getMockBuilder(Player::class)
			->disableOriginalConstructor()
			->onlyMethods(["getName", "getUniqueId", "isSneaking", "isCreative", "isSpectator", "getViewers", "canInteract", "broadcastSound", "broadcastAnimation", "getNetworkSession", "isConnected", "broadcastMovement", "sendPosition"])
			->getMock();

		(new ReflectionProperty(Entity::class, "closed"))->setValue($player, false);
		(new ReflectionProperty(Entity::class, "id"))->setValue($player, 100);
		(new ReflectionProperty(Player::class, "logger"))->setValue($player, $this->createMock(Logger::class));
		(new ReflectionProperty(Player::class, "networkSession"))->setValue($player, $session);
		(new ReflectionProperty(Living::class, "effectManager"))->setValue($player, new EffectManager($player));
		(new ReflectionProperty(Entity::class, "attributeMap"))->setValue($player, new AttributeMap());
		(new ReflectionClass(Player::class))->getMethod("addAttributes")->invoke($player);
		(new ReflectionProperty(Living::class, "armorInventory"))->setValue($player, new ArmorInventory($player));
		(new ReflectionProperty(Entity::class, "networkProperties"))->setValue($player, new EntityMetadataCollection());
		(new ReflectionProperty(Entity::class, "size"))->setValue($player, new EntitySizeInfo(1.8, 0.6, 1.62));
		(new ReflectionProperty(Entity::class, "scale"))->setValue($player, 1.0);

		if($world === null){
			$world = $this->createMock(World::class);
			$world->method("isLoaded")->willReturn(true);
			$world->method("isInLoadedTerrain")->willReturn(true);
			$stone = \pocketmine\block\VanillaBlocks::STONE();
			(new ReflectionProperty(\pocketmine\block\Block::class, "position"))->setValue($stone, new \pocketmine\world\Position(0, 9, 0, $world));
			$world->method("getBlock")->willReturn($stone);
			$world->method("getBlockAt")->willReturn($stone);
		}
		$location = new Location($pos !== null ? $pos->x : 0.0, $pos !== null ? $pos->y : 10.0, $pos !== null ? $pos->z : 0.0, $world, 0.0, 0.0);
		(new ReflectionProperty(Entity::class, "location"))->setValue($player, $location);
		(new ReflectionProperty(Entity::class, "boundingBox"))->setValue($player, new AxisAlignedBB(
			$location->x - 0.3,
			$location->y,
			$location->z - 0.3,
			$location->x + 0.3,
			$location->y + 1.8,
			$location->z + 0.3
		));
		(new ReflectionProperty(Entity::class, "motion"))->setValue($player, Vector3::zero());
		(new ReflectionProperty(Human::class, "inventory"))->setValue($player, new PlayerInventory($player));
		(new ReflectionProperty(Human::class, "offHandInventory"))->setValue($player, new PlayerOffHandInventory($player));
		(new ReflectionProperty(Human::class, "enderInventory"))->setValue($player, new \pocketmine\inventory\PlayerEnderInventory($player));
		(new ReflectionProperty(Player::class, "cursorInventory"))->setValue($player, new \pocketmine\inventory\PlayerCursorInventory($player));
		(new ReflectionProperty(Player::class, "craftingGrid"))->setValue($player, new \pocketmine\inventory\PlayerCraftingInventory($player));
		(new ReflectionProperty(Human::class, "hungerManager"))->setValue($player, new HungerManager($player));
		(new ReflectionProperty(Human::class, "xpManager"))->setValue($player, new ExperienceManager($player));
		(new ReflectionProperty(Entity::class, "onGround"))->setValue($player, false);
		(new ReflectionProperty(Player::class, "gamemode"))->setValue($player, GameMode::SURVIVAL);

		$player->method("getName")->willReturn("GlideTester");
		$player->method("getUniqueId")->willReturn(Uuid::uuid4());
		$player->method("isSneaking")->willReturn(false);
		$player->method("isCreative")->willReturn(false);
		$player->method("isSpectator")->willReturn(false);
		$player->method("getNetworkSession")->willReturn($session);
		$player->method("isConnected")->willReturn(false);

		return $player;
	}

	public function testCannotGlideWithoutElytra() : void{
		$player = $this->createTestPlayer();
		self::assertFalse($player->toggleGlide(true));
		self::assertFalse($player->isGliding());
		(new ReflectionProperty(Entity::class, "closed"))->setValue($player, true);
	}

	public function testCannotGlideWithBrokenElytra() : void{
		$player = $this->createTestPlayer();
		$elytra = VanillaItems::ELYTRA();
		$elytra->setDamage(431); // 1 durability remaining = broken
		self::assertTrue($elytra->isBroken());

		$player->getArmorInventory()->setChestplate($elytra);

		self::assertFalse($player->toggleGlide(true));
		self::assertFalse($player->isGliding());
		(new ReflectionProperty(Entity::class, "closed"))->setValue($player, true);
	}

	public function testCannotGlideWhileOnGround() : void{
		$player = $this->createTestPlayer();
		$player->getArmorInventory()->setChestplate(VanillaItems::ELYTRA());

		(new ReflectionProperty(Entity::class, "onGround"))->setValue($player, true);

		self::assertFalse($player->toggleGlide(true));
		self::assertFalse($player->isGliding());
		(new ReflectionProperty(Entity::class, "closed"))->setValue($player, true);
	}

	public function testGlideActivationWhenAirborneWithElytra() : void{
		$player = $this->createTestPlayer();
		$player->getArmorInventory()->setChestplate(VanillaItems::ELYTRA());
		(new ReflectionProperty(Entity::class, "onGround"))->setValue($player, false);

		self::assertTrue($player->toggleGlide(true));
		self::assertTrue($player->isGliding());
		(new ReflectionProperty(Entity::class, "closed"))->setValue($player, true);
	}

	public function testFallDamageNegatedWhileGliding() : void{
		$player = $this->createTestPlayer();
		$player->getArmorInventory()->setChestplate(VanillaItems::ELYTRA());
		(new ReflectionProperty(Entity::class, "onGround"))->setValue($player, false);

		$calcMethod = (new ReflectionClass(Player::class))->getMethod("calculateFallDamage");

		// Normal fall damage when not gliding
		$damageNotGliding = $calcMethod->invoke($player, 10.0);
		self::assertGreaterThan(0.0, $damageNotGliding);

		// Negated when gliding
		$player->toggleGlide(true);
		self::assertTrue($player->isGliding());
		$damageGliding = $calcMethod->invoke($player, 10.0);
		self::assertSame(0.0, $damageGliding);
		(new ReflectionProperty(Entity::class, "closed"))->setValue($player, true);
	}

	public function testGlideTickDurabilityDegradation() : void{
		$player = $this->createTestPlayer();
		$elytra = VanillaItems::ELYTRA();
		$player->getArmorInventory()->setChestplate($elytra);
		$player->toggleGlide(true);
		self::assertTrue($player->isGliding());

		// Tick 20 times (1 second of flight)
		for($i = 0; $i < 20; ++$i){
			(new ReflectionClass(Player::class))->getMethod("entityBaseTick")->invoke($player, 1);
		}

		/** @var Elytra $chest */
		$chest = $player->getArmorInventory()->getChestplate();
		self::assertInstanceOf(Elytra::class, $chest);
		self::assertSame(1, $chest->getDamage());
		(new ReflectionProperty(Entity::class, "closed"))->setValue($player, true);
	}

	public function testGlideAutoTerminatesOnGround() : void{
		$player = $this->createTestPlayer();
		$player->getArmorInventory()->setChestplate(VanillaItems::ELYTRA());
		$player->toggleGlide(true);
		self::assertTrue($player->isGliding());

		// Land on ground
		(new ReflectionProperty(Entity::class, "onGround"))->setValue($player, true);
		(new ReflectionClass(Player::class))->getMethod("entityBaseTick")->invoke($player, 1);

		self::assertFalse($player->isGliding());
		(new ReflectionProperty(Entity::class, "closed"))->setValue($player, true);
	}

	public function testCreativeFlightDoesNotConsumeDurability() : void{
		$player = $this->createTestPlayer();
		(new ReflectionProperty(Player::class, "gamemode"))->setValue($player, GameMode::CREATIVE);
		self::assertFalse($player->hasFiniteResources());

		$elytra = VanillaItems::ELYTRA();
		$player->getArmorInventory()->setChestplate($elytra);
		$player->toggleGlide(true);
		self::assertTrue($player->isGliding());

		// Tick 40 times (2 seconds of flight)
		for($i = 0; $i < 40; ++$i){
			(new ReflectionClass(Player::class))->getMethod("entityBaseTick")->invoke($player, 1);
		}

		/** @var Elytra $chest */
		$chest = $player->getArmorInventory()->getChestplate();
		self::assertInstanceOf(Elytra::class, $chest);
		self::assertSame(0, $chest->getDamage());
		self::assertTrue($player->isGliding());
		(new ReflectionProperty(Entity::class, "closed"))->setValue($player, true);
	}

	public function testGlideDescentResetsFallDistanceDebt() : void{
		$player = $this->createTestPlayer();
		$player->getArmorInventory()->setChestplate(VanillaItems::ELYTRA());
		$player->toggleGlide(true);
		self::assertTrue($player->isGliding());

		$updateFallState = (new ReflectionClass(Player::class))->getMethod("updateFallState");

		// Simulate gliding down 8 blocks
		$updateFallState->invoke($player, -8.0, false);
		self::assertSame(0.0, $player->getFallDistance());

		// Stop gliding 1 block above ground
		$player->toggleGlide(false);
		self::assertFalse($player->isGliding());
		self::assertSame(0.0, $player->getFallDistance());

		$initialHealth = $player->getHealth();

		// Fall the remaining 1 block and land on ground
		$updateFallState->invoke($player, -1.0, true);

		// If debt had been retained (8 blocks), landing would have dealt 6 damage.
		// With debt cleared, falling 1 block deals 0 damage, leaving health untouched:
		self::assertSame($initialHealth, $player->getHealth());
		self::assertSame(0.0, $player->getFallDistance()); // reset upon landing
		(new ReflectionProperty(Entity::class, "closed"))->setValue($player, true);
	}

	public function testElytraBreakingEndsGlideAndClearsPriorDescentDebt() : void{
		$player = $this->createTestPlayer();
		$elytra = VanillaItems::ELYTRA();
		// Set damage to 430: 1 tick of flight will deal 1 damage -> 431 = broken
		$elytra->setDamage(430);
		$player->getArmorInventory()->setChestplate($elytra);
		$player->toggleGlide(true);
		self::assertTrue($player->isGliding());

		$updateFallState = (new ReflectionClass(Player::class))->getMethod("updateFallState");
		$updateFallState->invoke($player, -8.0, false);
		self::assertSame(0.0, $player->getFallDistance());

		// Tick 20 times to trigger durability consumption
		for($i = 0; $i < 20; ++$i){
			(new ReflectionClass(Player::class))->getMethod("entityBaseTick")->invoke($player, 1);
		}

		// Elytra is now broken and glide auto-terminated
		/** @var Elytra $chest */
		$chest = $player->getArmorInventory()->getChestplate();
		self::assertTrue($chest->isBroken());
		self::assertFalse($player->isGliding());
		self::assertSame(0.0, $player->getFallDistance());

		// Player now falls 1 block to ground without earlier descent debt
		$calcMethod = (new ReflectionClass(Player::class))->getMethod("calculateFallDamage");
		$damage = $calcMethod->invoke($player, 1.0);
		self::assertLessThanOrEqual(0.0, $damage);

		(new ReflectionProperty(Entity::class, "closed"))->setValue($player, true);
	}
}
