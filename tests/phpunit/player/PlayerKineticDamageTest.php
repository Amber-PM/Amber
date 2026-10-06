<?php

declare(strict_types=1);

namespace pocketmine\player;

use Logger;
use pmmp\encoding\ByteBufferReader;
use pmmp\encoding\ByteBufferWriter;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use pocketmine\entity\AttributeMap;
use pocketmine\entity\effect\EffectManager;
use pocketmine\entity\Entity;
use pocketmine\entity\GlidePhysics;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\ExperienceManager;
use pocketmine\entity\Human;
use pocketmine\entity\HungerManager;
use pocketmine\entity\Living;
use pocketmine\entity\Location;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\inventory\ArmorInventory;
use pocketmine\inventory\PlayerCraftingInventory;
use pocketmine\inventory\PlayerCursorInventory;
use pocketmine\inventory\PlayerEnderInventory;
use pocketmine\inventory\PlayerInventory;
use pocketmine\block\VanillaBlocks;
use pocketmine\inventory\PlayerOffHandInventory;
use pocketmine\item\VanillaItems;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\InventoryManager;
use pocketmine\network\mcpe\handler\InGamePacketHandler;
use pocketmine\network\mcpe\protocol\PlayerAuthInputPacket;
use pocketmine\network\mcpe\protocol\serializer\BitSet;
use pocketmine\network\mcpe\protocol\types\PlayerAuthInputFlags;
use pocketmine\math\Vector2;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\player\GameMode;
use pocketmine\world\World;
use Ramsey\Uuid\Uuid;
use ReflectionClass;
use ReflectionProperty;

class PlayerKineticDamageTest extends TestCase{

	#[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
	#[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
	public function testLegacyMovementOverrideRemainsCompatible() : void{
		eval('class LegacyMovementPlayer extends \\pocketmine\\player\\Player{ public bool $movementHandled = false; public function handleMovement(\\pocketmine\\math\\Vector3 $newPos) : void{ $this->movementHandled = true; } }');
		self::assertTrue(is_subclass_of("LegacyMovementPlayer", Player::class));
		self::assertSame(1, (new \ReflectionMethod(Player::class, "handleMovement"))->getNumberOfParameters());
		$player = (new ReflectionClass("LegacyMovementPlayer"))->newInstanceWithoutConstructor();
		(new ReflectionProperty(Player::class, "logger"))->setValue($player, $this->createMock(Logger::class));
		(new ReflectionProperty(Entity::class, "closed"))->setValue($player, true);
		$player->handleMovementInput(Vector3::zero(), 1, Vector3::zero());
		self::assertTrue($player->movementHandled);
		self::assertFalse((new ReflectionProperty(Player::class, "processingGlideInput"))->getValue($player));
	}

	private function inputPacket(float $x, float $z, int $tick, bool $collision, ?Vector3 $velocity = null, float $pitch = 0, float $yaw = 270, float $feetY = 10) : PlayerAuthInputPacket{
		$flags = new BitSet(PlayerAuthInputFlags::NUMBER_OF_FLAGS);
		$flags->set(PlayerAuthInputFlags::HORIZONTAL_COLLISION, $collision);
		return PlayerAuthInputPacket::create(new Vector3($x, $feetY + 1.62, $z), $pitch, $yaw, $yaw, 0, 1, $flags, 1, 0, 1, null, new Vector2(0, 0), $tick, $velocity ?? ($collision ? Vector3::zero() : new Vector3($x, 0, $z)), null, null, null, null, 0, 1, new Vector3(1, 0, 0), new Vector2(0, 1));
	}

	public static function collisionFlags() : array{
		return [[false], [true]];
	}

	#[DataProvider("collisionFlags")]
	public function testClientPredictedWallCollisionUsesNativeVelocityLoss(bool $collisionFlag) : void{
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);
		$world->method("isInLoadedTerrain")->willReturn(true);
		$world->method("getBlockAt")->willReturn(VanillaBlocks::AIR());
		$world->method("getBlockCollisionBoxes")->willReturn([new AxisAlignedBB(2, 9, -1, 3, 12, 1)]);
		$player = $this->createTestPlayer($world, true);
		$player->getArmorInventory()->setChestplate(VanillaItems::ELYTRA());
		$player->toggleGlide(true);
		$handler = new InGamePacketHandler($player, $player->getNetworkSession(), $this->createMock(InventoryManager::class));
		try{
			self::assertTrue($handler->handlePlayerAuthInput($this->inputPacket(1.5, 0, 100, false)));
			self::assertTrue($handler->handlePlayerAuthInput($this->inputPacket(1.7, 0, 101, $collisionFlag, Vector3::zero())));
			self::assertEqualsWithDelta(8.13218, $player->getHealth(), 0.000001);
			self::assertTrue($player->isGliding());
			self::assertTrue($handler->handlePlayerAuthInput($this->inputPacket(1.7, 0, 102, true)));
			self::assertEqualsWithDelta(8.13218, $player->getHealth(), 0.000001);
		}finally{
			(new ReflectionProperty(Entity::class, "closed"))->setValue($player, true);
		}
	}

	#[DataProvider("collisionFlags")]
	public function testClientCollisionFlagCannotCauseDamageWithoutWall(bool $collisionFlag) : void{
		$player = $this->createTestPlayer(null, true);
		$player->getArmorInventory()->setChestplate(VanillaItems::ELYTRA());
		$player->toggleGlide(true);
		$handler = new InGamePacketHandler($player, $player->getNetworkSession(), $this->createMock(InventoryManager::class));
		try{
			$handler->handlePlayerAuthInput($this->inputPacket(1.5, 0, 100, false));
			$handler->handlePlayerAuthInput($this->inputPacket(1.7, 0, 101, $collisionFlag));
			self::assertSame(20.0, $player->getHealth());
		}finally{
			(new ReflectionProperty(Entity::class, "closed"))->setValue($player, true);
		}
	}

	#[DataProvider("collisionFlags")]
	public function testApproachingWallWithoutReachingItDoesNotDealDamage(bool $collisionFlag) : void{
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);
		$world->method("isInLoadedTerrain")->willReturn(true);
		$world->method("getBlockAt")->willReturn(VanillaBlocks::AIR());
		$world->method("getBlockCollisionBoxes")->willReturn([new AxisAlignedBB(2, 9, -1, 3, 12, 1)]);
		$player = $this->createTestPlayer($world, true);
		$player->getArmorInventory()->setChestplate(VanillaItems::ELYTRA());
		$player->toggleGlide(true);
		$handler = new InGamePacketHandler($player, $player->getNetworkSession(), $this->createMock(InventoryManager::class));
		try{
			$handler->handlePlayerAuthInput($this->inputPacket(0.212718, 0, 100, false, new Vector3(1.5, 0, 0)));
			$handler->handlePlayerAuthInput($this->inputPacket(1.6995, 0, 101, $collisionFlag, new Vector3(1.486782, -0.01764, 0)));
			self::assertSame(20.0, $player->getHealth());
		}finally{
			(new ReflectionProperty(Entity::class, "closed"))->setValue($player, true);
		}
	}

	public static function legacyCollisionProtocols() : array{
		return [[589], [712]];
	}

	#[DataProvider("legacyCollisionProtocols")]
	public function testLegacyEncodedMovementDealsWallDamageWithoutCollisionFlag(int $protocol) : void{
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);
		$world->method("isInLoadedTerrain")->willReturn(true);
		$world->method("getBlockAt")->willReturn(VanillaBlocks::AIR());
		$world->method("getBlockCollisionBoxes")->willReturn([new AxisAlignedBB(2, 9, -1, 3, 12, 1)]);
		$player = $this->createTestPlayer($world, true);
		$player->getNetworkSession()->method("getProtocolId")->willReturn($protocol);
		$player->getArmorInventory()->setChestplate(VanillaItems::ELYTRA());
		$player->toggleGlide(true);
		$handler = new InGamePacketHandler($player, $player->getNetworkSession(), $this->createMock(InventoryManager::class));
		try{
			foreach([$this->inputPacket(1.5, 0, 100, false, new Vector3(1.5, 0, 0)), $this->inputPacket(1.7, 0, 101, false, Vector3::zero())] as $packet){
				$buffer = new ByteBufferWriter();
				$packet->encode($buffer, $protocol);
				$decoded = new PlayerAuthInputPacket();
				$decoded->decode(new ByteBufferReader($buffer->getData()), $protocol);
				self::assertFalse($decoded->getInputFlags()->get(PlayerAuthInputFlags::HORIZONTAL_COLLISION));
				self::assertTrue($handler->handlePlayerAuthInput($decoded));
			}
			self::assertEqualsWithDelta(8.13218, $player->getHealth(), 0.000001);
		}finally{
			(new ReflectionProperty(Entity::class, "closed"))->setValue($player, true);
		}
	}

	public function testCornerCollisionZerosBothBlockedAxes() : void{
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);
		$world->method("isInLoadedTerrain")->willReturn(true);
		$world->method("getBlockAt")->willReturn(VanillaBlocks::AIR());
		$world->method("getBlockCollisionBoxes")->willReturn([new AxisAlignedBB(2, 9, -2, 3, 12, 3), new AxisAlignedBB(-2, 9, 2, 3, 12, 3)]);
		$player = $this->createTestPlayer($world, true);
		$player->getArmorInventory()->setChestplate(VanillaItems::ELYTRA());
		$player->toggleGlide(true);
		$handler = new InGamePacketHandler($player, $player->getNetworkSession(), $this->createMock(InventoryManager::class));
		try{
			$previous = new Vector3(1.5, 0, 1.5);
			$handler->handlePlayerAuthInput($this->inputPacket(1.5, 1.5, 100, false, $previous, 0, 315));
			$predicted = GlidePhysics::calculateMotion($previous, $player->getDirectionVector());
			$handler->handlePlayerAuthInput($this->inputPacket(1.7, 1.7, 101, false, Vector3::zero(), 0, 315));
			self::assertEqualsWithDelta(20 - (sqrt($predicted->x ** 2 + $predicted->z ** 2) * 10 - 3), $player->getHealth(), 0.000001);
		}finally{
			(new ReflectionProperty(Entity::class, "closed"))->setValue($player, true);
		}
	}

	public function testMovingAwayFromWallDoesNotDealDamage() : void{
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);
		$world->method("isInLoadedTerrain")->willReturn(true);
		$world->method("getBlockAt")->willReturn(VanillaBlocks::AIR());
		$world->method("getBlockCollisionBoxes")->willReturn([new AxisAlignedBB(2, 9, -1, 3, 12, 1)]);
		$player = $this->createTestPlayer($world, true);
		$player->getArmorInventory()->setChestplate(VanillaItems::ELYTRA());
		$player->toggleGlide(true);
		$handler = new InGamePacketHandler($player, $player->getNetworkSession(), $this->createMock(InventoryManager::class));
		try{
			$handler->handlePlayerAuthInput($this->inputPacket(1.7, 0, 100, false, new Vector3(-1.5, 0, 0), 0, 90));
			$handler->handlePlayerAuthInput($this->inputPacket(0.2132, 0, 101, true, new Vector3(-1.486782, 0, 0), 0, 90));
			self::assertSame(20.0, $player->getHealth());
		}finally{
			(new ReflectionProperty(Entity::class, "closed"))->setValue($player, true);
		}
	}

	public function testGlancingImpactStillDamagesAfterLeavingWallSide() : void{
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);
		$world->method("isInLoadedTerrain")->willReturn(true);
		$world->method("getBlockAt")->willReturn(VanillaBlocks::AIR());
		$world->method("getBlockCollisionBoxes")->willReturn([new AxisAlignedBB(2, 9, 0, 3, 12, 1)]);
		$player = $this->createTestPlayer($world, true);
		$player->getArmorInventory()->setChestplate(VanillaItems::ELYTRA());
		$player->toggleGlide(true);
		$handler = new InGamePacketHandler($player, $player->getNetworkSession(), $this->createMock(InventoryManager::class));
		try{
			$handler->handlePlayerAuthInput($this->inputPacket(1.5, 0.5, 100, false, new Vector3(1.5, 0, 1.5), 0, 315));
			$handler->handlePlayerAuthInput($this->inputPacket(1.7, 1.9863, 101, false, new Vector3(0, -0.01764, 1.486260064284), 0, 315, 9.9824));
			self::assertEqualsWithDelta(20 - 3.1562907584, $player->getHealth(), 0.000001);
		}finally{
			(new ReflectionProperty(Entity::class, "closed"))->setValue($player, true);
		}
	}

	public function testClearingSlabDoesNotDealWallDamage() : void{
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);
		$world->method("isInLoadedTerrain")->willReturn(true);
		$world->method("getBlockAt")->willReturn(VanillaBlocks::AIR());
		$world->method("getBlockCollisionBoxes")->willReturn([new AxisAlignedBB(2, 9, -1, 3, 10.5, 1), new AxisAlignedBB(-10, 9, -10, 10, 10, 10)]);
		$player = $this->createTestPlayer($world, true);
		$player->getArmorInventory()->setChestplate(VanillaItems::ELYTRA());
		$player->toggleGlide(true);
		$handler = new InGamePacketHandler($player, $player->getNetworkSession(), $this->createMock(InventoryManager::class));
		try{
			$handler->handlePlayerAuthInput($this->inputPacket(1.5, 0, 100, false, new Vector3(1.5, -0.1, 0)));
			$handler->handlePlayerAuthInput($this->inputPacket(2.3, 0, 101, false, new Vector3(1.486782, 0, 0), 0, 270, 10.5));
			self::assertSame(20.0, $player->getHealth());
		}finally{
			(new ReflectionProperty(Entity::class, "closed"))->setValue($player, true);
		}
	}

	public function testFlushCollisionSurvivesFloatingPointClipping() : void{
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);
		$world->method("isInLoadedTerrain")->willReturn(true);
		$world->method("getBlockAt")->willReturn(VanillaBlocks::AIR());
		$world->method("getBlockCollisionBoxes")->willReturn([new AxisAlignedBB(2.5, 9, -1, 3.5, 12, 1)]);
		$player = $this->createTestPlayer($world, true);
		$player->getArmorInventory()->setChestplate(VanillaItems::ELYTRA());
		$player->toggleGlide(true);
		$handler = new InGamePacketHandler($player, $player->getNetworkSession(), $this->createMock(InventoryManager::class));
		try{
			$handler->handlePlayerAuthInput($this->inputPacket(1.5, 0, 100, false));
			$handler->handlePlayerAuthInput($this->inputPacket(2.2, 0, 101, true));
			self::assertEqualsWithDelta(8.13218, $player->getHealth(), 0.000001);
		}finally{
			(new ReflectionProperty(Entity::class, "closed"))->setValue($player, true);
		}
	}

	public function testFloorContactCannotBeUsedAsHorizontalCollision() : void{
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);
		$world->method("isInLoadedTerrain")->willReturn(true);
		$world->method("getBlockAt")->willReturn(VanillaBlocks::AIR());
		$world->method("getBlockCollisionBoxes")->willReturn([new AxisAlignedBB(-10, 9, -10, 10, 10, 10)]);
		$player = $this->createTestPlayer($world, true);
		$player->getArmorInventory()->setChestplate(VanillaItems::ELYTRA());
		$player->toggleGlide(true);
		$handler = new InGamePacketHandler($player, $player->getNetworkSession(), $this->createMock(InventoryManager::class));
		try{
			$handler->handlePlayerAuthInput($this->inputPacket(1.5, 0, 100, false));
			$handler->handlePlayerAuthInput($this->inputPacket(1.7, 0, 101, true));
			self::assertSame(20.0, $player->getHealth());
		}finally{
			(new ReflectionProperty(Entity::class, "closed"))->setValue($player, true);
		}
	}

	private function createTestPlayer(?World $world = null, bool $clientPrediction = false) : Player{
		$session = $this->getMockBuilder(NetworkSession::class)->disableOriginalConstructor()->getMock();
		$player = $this->getMockBuilder(Player::class)
			->disableOriginalConstructor()
			->onlyMethods(["getName", "getUniqueId", "isSneaking", "isCreative", "isSpectator", "getViewers", "canInteract", "broadcastSound", "broadcastAnimation", "getNetworkSession", "isConnected", "broadcastMovement", "sendPosition"])
			->getMock();

		(new ReflectionProperty(Entity::class, "closed"))->setValue($player, false);
		(new ReflectionProperty(Entity::class, "keepMovement"))->setValue($player, $clientPrediction);
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
			$world->method("getBlockAt")->willReturn(VanillaBlocks::AIR());
		}
		$location = new Location(0.0, 10.0, 0.0, $world, 0.0, 0.0);
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
		(new ReflectionProperty(Human::class, "enderInventory"))->setValue($player, new PlayerEnderInventory($player));
		(new ReflectionProperty(Player::class, "cursorInventory"))->setValue($player, new PlayerCursorInventory($player));
		(new ReflectionProperty(Player::class, "craftingGrid"))->setValue($player, new PlayerCraftingInventory($player));
		(new ReflectionProperty(Human::class, "hungerManager"))->setValue($player, new HungerManager($player));
		(new ReflectionProperty(Human::class, "xpManager"))->setValue($player, new ExperienceManager($player));
		(new ReflectionProperty(Entity::class, "onGround"))->setValue($player, false);
		(new ReflectionProperty(Player::class, "gamemode"))->setValue($player, GameMode::SURVIVAL);

		$player->method("getName")->willReturn("KineticTester");
		$player->method("getUniqueId")->willReturn(Uuid::uuid4());
		$player->method("isSneaking")->willReturn(false);
		$player->method("isCreative")->willReturn(false);
		$player->method("isSpectator")->willReturn(false);
		$player->method("getNetworkSession")->willReturn($session);
		$player->method("isConnected")->willReturn(false);

		return $player;
	}

	public function testNativeObliqueImpactMatchesBedrockRecording() : void{
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);
		$world->method("isInLoadedTerrain")->willReturn(true);
		$world->method("getBlockAt")->willReturn(VanillaBlocks::AIR());
		$world->method("getBlockCollisionBoxes")->willReturn([new AxisAlignedBB(2, 9, -1, 3, 12, 1)]);
		$player = $this->createTestPlayer($world, true);
		$player->getArmorInventory()->setChestplate(VanillaItems::ELYTRA());
		$player->toggleGlide(true);
		$handler = new InGamePacketHandler($player, $player->getNetworkSession(), $this->createMock(InventoryManager::class));
		try{
			$handler->handlePlayerAuthInput($this->inputPacket(1.5, 0.08, 100, false, new Vector3(1.4318370819091797, -0.16983795166015625, 0.08031368255615234)));
			$handler->handlePlayerAuthInput($this->inputPacket(1.71, 0.1804, 101, true, new Vector3(0, -0.06499481201171875, 0.10038185119628906), -85.39335632324219, -71.56504821777344));
			self::assertEqualsWithDelta(20.0 - 9.655743598937988, $player->getHealth(), 0.0002);
			$before = clone $player->getBoundingBox();
			(new ReflectionClass(Player::class))->getMethod("getGlidingCollisionMotion")->invoke($player, new Vector3(1, 0, 0.1), clone $before, false);
			self::assertEquals($before, $player->getBoundingBox());
		}finally{
			(new ReflectionProperty(Entity::class, "closed"))->setValue($player, true);
		}
	}

	public function testGapsAndRejectedInputsDiscardImpactHistory() : void{
		foreach(["gap", "rejected", "restart", "duplicate"] as $scenario){
			$world = $this->createMock(World::class);
			$world->method("isLoaded")->willReturn(true);
			$world->method("isInLoadedTerrain")->willReturn(true);
			$world->method("getBlockAt")->willReturn(VanillaBlocks::AIR());
			$world->method("getBlockCollisionBoxes")->willReturn([new AxisAlignedBB(2, 9, -1, 3, 12, 1)]);
			$player = $this->createTestPlayer($world, true);
			$player->getArmorInventory()->setChestplate(VanillaItems::ELYTRA());
			$player->toggleGlide(true);
			$handler = new InGamePacketHandler($player, $player->getNetworkSession(), $this->createMock(InventoryManager::class));
			try{
				$handler->handlePlayerAuthInput($this->inputPacket(1.5, 0, 100, false));
				if($scenario === "rejected"){
					$handler->handlePlayerAuthInput($this->inputPacket(100, 0, 101, false));
				}elseif($scenario === "restart"){
					$player->setGliding(false);
					$player->setGliding(true);
				}elseif($scenario === "duplicate"){
					$handler->handlePlayerAuthInput($this->inputPacket(1.5, 0, 100, false));
				}
				$handler->handlePlayerAuthInput($this->inputPacket(1.7, 0, $scenario === "restart" || $scenario === "duplicate" ? 101 : 102, true));
				self::assertSame(20.0, $player->getHealth(), $scenario);
			}finally{
				(new ReflectionProperty(Entity::class, "closed"))->setValue($player, true);
			}
		}
	}

	public function testKineticDamageAppliedOnHighSpeedCollision() : void{
		$player = $this->createTestPlayer();
		$player->getArmorInventory()->setChestplate(VanillaItems::ELYTRA());
		$player->toggleGlide(true);
		self::assertTrue($player->isGliding());

		$initialHealth = $player->getHealth();

		$damaged = $player->checkGlidingKineticDamage(1.5, true);
		self::assertTrue($damaged);
		self::assertSame($initialHealth - 12.0, $player->getHealth());
		self::assertTrue($player->isGliding());

		(new ReflectionProperty(Entity::class, "closed"))->setValue($player, true);
	}

	public function testWallDamageBypassesArmorWithoutWearingIt() : void{
		$player = $this->createTestPlayer();
		$player->getArmorInventory()->setChestplate(VanillaItems::ELYTRA());
		$player->getArmorInventory()->setHelmet(VanillaItems::DIAMOND_HELMET());
		$player->getArmorInventory()->setLeggings(VanillaItems::DIAMOND_LEGGINGS());
		$player->getArmorInventory()->setBoots(VanillaItems::DIAMOND_BOOTS());
		$player->toggleGlide(true);
		try{
			self::assertTrue($player->checkGlidingKineticDamage(1.5));
			self::assertSame(8.0, $player->getHealth());
			foreach($player->getArmorInventory()->getContents() as $item){
				self::assertSame(0, $item->getDamage());
			}
		}finally{
			(new ReflectionProperty(Entity::class, "closed"))->setValue($player, true);
		}
	}

	public function testNoKineticDamageBelowThreshold() : void{
		$player = $this->createTestPlayer();
		$player->getArmorInventory()->setChestplate(VanillaItems::ELYTRA());
		$player->toggleGlide(true);
		self::assertTrue($player->isGliding());

		$initialHealth = $player->getHealth();

		$damaged = $player->checkGlidingKineticDamage(0.2, true);
		self::assertFalse($damaged);
		self::assertSame($initialHealth, $player->getHealth());
		self::assertTrue($player->isGliding());

		(new ReflectionProperty(Entity::class, "closed"))->setValue($player, true);
	}

	public function testNoKineticDamageWithoutCollision() : void{
		$player = $this->createTestPlayer();
		$player->getArmorInventory()->setChestplate(VanillaItems::ELYTRA());
		$player->toggleGlide(true);
		self::assertTrue($player->isGliding());

		$initialHealth = $player->getHealth();

		$damaged = $player->checkGlidingKineticDamage(2.0, false);
		self::assertFalse($damaged);
		self::assertSame($initialHealth, $player->getHealth());
		self::assertTrue($player->isGliding());

		(new ReflectionProperty(Entity::class, "closed"))->setValue($player, true);
	}

	public function testMovementIntoWallAppliesKineticDamage() : void{
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);
		$world->method("isInLoadedTerrain")->willReturn(true);
		$world->method("getBlockAt")->willReturn(VanillaBlocks::AIR());
		$wallBox = new AxisAlignedBB(1.0, 9.0, -1.0, 2.0, 12.0, 1.0);
		$world->method("getBlockCollisionBoxes")->willReturn([$wallBox]);

		$player = $this->createTestPlayer($world);
		$player->getArmorInventory()->setChestplate(VanillaItems::ELYTRA());
		$player->toggleGlide(true);
		self::assertTrue($player->isGliding());

		$initialHealth = $player->getHealth();

		$moveMethod = (new ReflectionClass(Player::class))->getMethod("move");
		$moveMethod->invoke($player, 1.5, 0.0, 0.0);

		self::assertEqualsWithDelta($initialHealth - 12.0, $player->getHealth(), 0.000001);
		self::assertTrue($player->isGliding());

		(new ReflectionProperty(Entity::class, "closed"))->setValue($player, true);
	}

	public function testMovementIntoWallBelowThresholdDealsNoDamage() : void{
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);
		$world->method("isInLoadedTerrain")->willReturn(true);
		$world->method("getBlockAt")->willReturn(VanillaBlocks::AIR());
		$wallBox = new AxisAlignedBB(1.0, 9.0, -1.0, 2.0, 12.0, 1.0);
		$world->method("getBlockCollisionBoxes")->willReturn([$wallBox]);

		$player = $this->createTestPlayer($world);
		$player->getArmorInventory()->setChestplate(VanillaItems::ELYTRA());
		$player->toggleGlide(true);
		self::assertTrue($player->isGliding());

		$initialHealth = $player->getHealth();

		$moveMethod = (new ReflectionClass(Player::class))->getMethod("move");
		$moveMethod->invoke($player, 0.5, 0.0, 0.0);

		self::assertSame($initialHealth, $player->getHealth());
		self::assertTrue($player->isGliding());

		(new ReflectionProperty(Entity::class, "closed"))->setValue($player, true);
	}

	public function testMovementInOpenAirDoesNotCauseKineticDamage() : void{
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);
		$world->method("isInLoadedTerrain")->willReturn(true);
		$world->method("getBlockAt")->willReturn(VanillaBlocks::AIR());
		$world->method("getBlockCollisionBoxes")->willReturn([]);

		$player = $this->createTestPlayer($world);
		$player->getArmorInventory()->setChestplate(VanillaItems::ELYTRA());
		$player->toggleGlide(true);
		self::assertTrue($player->isGliding());

		$initialHealth = $player->getHealth();

		$moveMethod = (new ReflectionClass(Player::class))->getMethod("move");
		$moveMethod->invoke($player, 1.5, 0.0, 0.0);

		self::assertSame($initialHealth, $player->getHealth());
		self::assertTrue($player->isGliding());

		(new ReflectionProperty(Entity::class, "closed"))->setValue($player, true);
	}

	public function testHandleMovementIntoWallTriggersKineticDamage() : void{
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);
		$world->method("isInLoadedTerrain")->willReturn(true);
		$world->method("getBlockAt")->willReturn(VanillaBlocks::AIR());
		$wallBox = new AxisAlignedBB(1.0, 9.0, -1.0, 2.0, 12.0, 1.0);
		$world->method("getBlockCollisionBoxes")->willReturn([$wallBox]);

		$player = $this->createTestPlayer($world);
		$player->getArmorInventory()->setChestplate(VanillaItems::ELYTRA());
		$player->toggleGlide(true);
		self::assertTrue($player->isGliding());

		$initialHealth = $player->getHealth();

		$player->handleMovement(new Vector3(1.5, 10.0, 0.0));

		self::assertEqualsWithDelta($initialHealth - 12.0, $player->getHealth(), 0.000001);
		self::assertTrue($player->isGliding());

		(new ReflectionProperty(Entity::class, "closed"))->setValue($player, true);
	}
}
