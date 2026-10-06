<?php

declare(strict_types=1);

namespace pocketmine\item;

use Logger;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use pocketmine\entity\AttributeMap;
use pocketmine\entity\effect\EffectManager;
use pocketmine\entity\Entity;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\ExperienceManager;
use pocketmine\entity\Human;
use pocketmine\entity\HungerManager;
use pocketmine\entity\Living;
use pocketmine\entity\Location;
use pocketmine\inventory\ArmorInventory;
use pocketmine\inventory\PlayerCraftingInventory;
use pocketmine\inventory\PlayerCursorInventory;
use pocketmine\inventory\PlayerEnderInventory;
use pocketmine\inventory\PlayerInventory;
use pocketmine\inventory\PlayerOffHandInventory;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\MovementEffectPacket;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\network\mcpe\protocol\types\MovementEffectType;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\player\Player;
use pocketmine\world\World;
use Ramsey\Uuid\Uuid;
use ReflectionClass;
use ReflectionProperty;

class FireworkRocketGlideBoostTest extends TestCase{
	private array $rockets = [];

	protected function tearDown() : void{
		foreach($this->rockets as $rocket){
			(new ReflectionProperty(Entity::class, "closed"))->setValue($rocket, true);
		}
	}

	private function createTestPlayer(bool $creative = false, int $protocol = ProtocolInfo::CURRENT_PROTOCOL) : Player{
		$session = $this->getMockBuilder(NetworkSession::class)->disableOriginalConstructor()->getMock();
		$session->method("getProtocolId")->willReturn($protocol);
		$player = $this->getMockBuilder(Player::class)
			->disableOriginalConstructor()
			->onlyMethods(["getName", "getUniqueId", "isSneaking", "isCreative", "isSpectator", "getViewers", "canInteract", "broadcastSound", "broadcastAnimation", "getNetworkSession", "isConnected", "broadcastMovement", "sendPosition", "hasFiniteResources"])
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

		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);
		$world->method("getBlock")->willReturn(\pocketmine\block\VanillaBlocks::AIR());
		$world->method("getBlockAt")->willReturn(\pocketmine\block\VanillaBlocks::AIR());
		$server = $this->createMock(\pocketmine\Server::class);
		$manager = $this->createMock(\pocketmine\world\WorldManager::class);
		$manager->method("findEntity")->willReturnCallback(fn(int $id) => $id === $player->getId() ? $player : null);
		$server->method("getWorldManager")->willReturn($manager);
		$world->method("getServer")->willReturn($server);
		$world->method("addEntity")->willReturnCallback(function(Entity $entity) : void{ $this->rockets[] = $entity; });
		$location = new Location(0.0, 50.0, 0.0, $world, 0.0, 0.0);
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

		$player->method("getName")->willReturn("RocketTester");
		$player->method("getUniqueId")->willReturn(Uuid::uuid4());
		$player->method("isSneaking")->willReturn(false);
		$player->method("isCreative")->willReturn($creative);
		$player->method("hasFiniteResources")->willReturn(!$creative);
		$player->method("isSpectator")->willReturn(false);
		$player->method("getNetworkSession")->willReturn($session);
		$player->method("isConnected")->willReturn(false);

		return $player;
	}

	public static function legacyProtocols() : array{
		return [[589], [729]];
	}

	#[DataProvider("legacyProtocols")]
	public function testLegacyBoostDoesNotSendUnsupportedMovementEffect(int $protocol) : void{
		$player = $this->createTestPlayer(false, $protocol);
		$player->getArmorInventory()->setChestplate(VanillaItems::ELYTRA());
		$player->toggleGlide(true);
		$player->getNetworkSession()->expects(self::never())->method("sendDataPacket");
		try{
			$rocket = VanillaItems::FIREWORK_ROCKET()->setCount(3);
			$returnedItems = [];
			self::assertSame(ItemUseResult::SUCCESS, $rocket->onClickAir($player, $player->getDirectionVector(), $returnedItems));
			self::assertSame(2, $rocket->getCount());
			self::assertCount(1, $this->rockets);
			$player->toggleGlide(false);
		}finally{
			(new ReflectionProperty(Entity::class, "closed"))->setValue($player, true);
		}
	}

	public static function movementEffectProtocols() : array{
		return [[748], [ProtocolInfo::CURRENT_PROTOCOL]];
	}

	#[DataProvider("movementEffectProtocols")]
	public function testBoostUsesClientMovementEffectAndRocketLifetime(int $protocol) : void{
		$player = $this->createTestPlayer(false, $protocol);
		$player->getArmorInventory()->setChestplate(VanillaItems::ELYTRA());
		$player->toggleGlide(true);
		self::assertTrue($player->isGliding());
		$player->setLastPlayerInputTick(600);
		$player->getNetworkSession()->expects(self::once())->method("sendDataPacket")->with(self::callback(function($packet) : bool{
			return $packet instanceof MovementEffectPacket && $packet->getActorRuntimeId() === 100 && $packet->getEffectType() === MovementEffectType::GLIDE_BOOST && $packet->getTick() === 600 && $packet->getDuration() >= 20 && $packet->getDuration() <= 32;
		}));

		$rocket = VanillaItems::FIREWORK_ROCKET();
		$rocket->setCount(3);

		$returnedItems = [];
		$result = $rocket->onClickAir($player, $player->getDirectionVector(), $returnedItems);

		self::assertSame(ItemUseResult::SUCCESS, $result);
		self::assertSame(2, $rocket->getCount());
		self::assertSame(0.0, $player->getMotion()->length());
		self::assertCount(1, $this->rockets);
		self::assertEquals($player->getPosition()->asVector3(), $this->rockets[0]->getPosition()->asVector3());

		(new ReflectionProperty(Entity::class, "closed"))->setValue($player, true);
	}

	public function testBoostFailsWhenNotGliding() : void{
		$player = $this->createTestPlayer(false);
		self::assertFalse($player->isGliding());

		$rocket = VanillaItems::FIREWORK_ROCKET();
		$rocket->setCount(3);

		$returnedItems = [];
		$result = $rocket->onClickAir($player, $player->getDirectionVector(), $returnedItems);

		self::assertSame(ItemUseResult::FAIL, $result);
		self::assertSame(3, $rocket->getCount());
		self::assertSame(0.0, $player->getMotion()->length());

		(new ReflectionProperty(Entity::class, "closed"))->setValue($player, true);
	}

	public function testBlockInteractionWhileGlidingBoostsWithoutGroundLaunch() : void{
		$player = $this->createTestPlayer();
		$player->getArmorInventory()->setChestplate(VanillaItems::ELYTRA());
		$player->toggleGlide(true);
		$world = $player->getWorld();
		$clicked = \pocketmine\block\VanillaBlocks::STONE();
		$clicked->position($world, 0, 49, 0);
		$replace = \pocketmine\block\VanillaBlocks::AIR();
		$replace->position($world, 0, 50, 0);
		$rocket = VanillaItems::FIREWORK_ROCKET()->setCount(3);
		try{
			$returnedItems = [];
			self::assertSame(ItemUseResult::SUCCESS, $rocket->onInteractBlock($player, $replace, $clicked, \pocketmine\math\Facing::UP, Vector3::zero(), $returnedItems));
			self::assertSame(2, $rocket->getCount());
			self::assertCount(1, $this->rockets);
			self::assertEquals($player->getPosition()->asVector3(), $this->rockets[0]->getPosition()->asVector3());
		}finally{
			(new ReflectionProperty(Entity::class, "closed"))->setValue($player, true);
		}
	}

	public function testCreativeModeDoesNotConsumeRocket() : void{
		$player = $this->createTestPlayer(true);
		$player->getArmorInventory()->setChestplate(VanillaItems::ELYTRA());
		$player->toggleGlide(true);
		self::assertTrue($player->isGliding());

		$rocket = VanillaItems::FIREWORK_ROCKET();
		$rocket->setCount(3);

		$returnedItems = [];
		$result = $rocket->onClickAir($player, $player->getDirectionVector(), $returnedItems);

		self::assertSame(ItemUseResult::SUCCESS, $result);
		self::assertSame(3, $rocket->getCount());
		self::assertSame(0.0, $player->getMotion()->length());
		self::assertCount(1, $this->rockets);

		(new ReflectionProperty(Entity::class, "closed"))->setValue($player, true);
	}

	public function testExplosiveRocketDamagesPlayerOnlyAfterFlightExpires() : void{
		$player = $this->createTestPlayer();
		$player->getArmorInventory()->setChestplate(VanillaItems::ELYTRA());
		$player->toggleGlide(true);
		$player->getWorld()->method("getCollidingEntities")->willReturn([$player]);
		$rocket = VanillaItems::FIREWORK_ROCKET()->setExplosions([new FireworkRocketExplosion(FireworkRocketType::SMALL_BALL, [\pocketmine\block\utils\DyeColor::WHITE()], [], false, false)]);
		$returnedItems = [];
		try{
			self::assertSame(ItemUseResult::SUCCESS, $rocket->onClickAir($player, $player->getDirectionVector(), $returnedItems));
			self::assertSame(20.0, $player->getHealth());
			self::assertCount(1, $this->rockets);
			$entity = $this->rockets[0];
			$tick = (new ReflectionClass(\pocketmine\entity\object\FireworkRocket::class))->getMethod("entityBaseTick");
			$tick->invoke($entity, $entity->getMaxFlightTimeTicks() - 1);
			self::assertSame(20.0, $player->getHealth());
			$tick->invoke($entity, 1);
			self::assertSame(13.0, $player->getHealth());
			self::assertTrue($entity->isFlaggedForDespawn());
		}finally{
			(new ReflectionProperty(Entity::class, "closed"))->setValue($player, true);
		}
	}

	public function testMultipleRocketsKeepBoostUntilLastRocketExpires() : void{
		$player = $this->createTestPlayer();
		$player->getArmorInventory()->setChestplate(VanillaItems::ELYTRA());
		$player->toggleGlide(true);
		$durations = [];
		$player->getNetworkSession()->method("sendDataPacket")->willReturnCallback(function($packet) use (&$durations) : bool{
			if($packet instanceof MovementEffectPacket){ $durations[] = $packet->getDuration(); }
			return true;
		});
		try{
			$player->boostGlideWithFirework(VanillaItems::FIREWORK_ROCKET());
			$player->boostGlideWithFirework(VanillaItems::FIREWORK_ROCKET()->setFlightTimeMultiplier(3));
			self::assertCount(2, $this->rockets);
			$tick = (new ReflectionClass(\pocketmine\entity\object\FireworkRocket::class))->getMethod("entityBaseTick");
			$tick->invoke($this->rockets[0], $this->rockets[0]->getMaxFlightTimeTicks());
			self::assertGreaterThan(0, $durations[count($durations) - 1]);
			$tick->invoke($this->rockets[1], $this->rockets[1]->getMaxFlightTimeTicks());
			self::assertSame(0, $durations[count($durations) - 1]);
		}finally{
			(new ReflectionProperty(Entity::class, "closed"))->setValue($player, true);
		}
	}

	public function testStoppingGlideCancelsBoostEffect() : void{
		$player = $this->createTestPlayer();
		$player->getArmorInventory()->setChestplate(VanillaItems::ELYTRA());
		$player->toggleGlide(true);
		$durations = [];
		$player->getNetworkSession()->method("sendDataPacket")->willReturnCallback(function($packet) use (&$durations) : bool{
			if($packet instanceof MovementEffectPacket){ $durations[] = $packet->getDuration(); }
			return true;
		});
		try{
			$player->boostGlideWithFirework(VanillaItems::FIREWORK_ROCKET());
			$player->toggleGlide(false);
			self::assertCount(2, $durations);
			self::assertGreaterThan(0, $durations[0]);
			self::assertSame(0, $durations[1]);
		}finally{
			(new ReflectionProperty(Entity::class, "closed"))->setValue($player, true);
		}
	}
}
