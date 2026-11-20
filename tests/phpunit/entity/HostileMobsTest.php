<?php

/*
 *
 *     _             _
 *    / \   _ __ ___ | |__   ___ _ __
 *   / _ \ | '_ ` _ \| '_ \ / _ \ '__|
 *  / ___ \| | | | | | |_) |  __/ |
 * /_/   \_\_| |_| |_|_.__/ \___|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author AmberPM Team
 * @link https://github.com/Amber-PM/Amber
 *
 *
 */

declare(strict_types=1);

namespace pocketmine\entity;

if(!class_exists(\pmmp\encoding\BE::class, false)){
	@include_once __DIR__ . '/../fixtures/TestEncodingPolyfill.php';
}

use Logger;
use PHPUnit\Framework\TestCase;
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\AttributeMap;
use pocketmine\entity\effect\EffectManager;
use pocketmine\event\entity\EntityDamageByChildEntityEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\HandlerListManager;
use pocketmine\inventory\ArmorInventory;
use pocketmine\inventory\PlayerInventory;
use pocketmine\inventory\PlayerOffHandInventory;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\ByteMetadataProperty;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\network\mcpe\protocol\types\entity\LongMetadataProperty;
use pocketmine\network\mcpe\protocol\types\entity\ShortMetadataProperty;
use pocketmine\player\Player;
use pocketmine\world\World;
use Ramsey\Uuid\Uuid;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

class HostileMobsTest extends TestCase{

	/** @var Entity[] */
	private array $createdEntities = [];
	private ?World $defaultWorld = null;

	protected function setUp() : void{
		parent::setUp();
	}

	protected function tearDown() : void{
		HandlerListManager::global()->unregisterAll();
		foreach($this->createdEntities as $entity){
			(new ReflectionProperty(Entity::class, "closed"))->setValue($entity, true);
		}
		$this->createdEntities = [];
		$this->defaultWorld = null;
		parent::tearDown();
	}

	private function getDefaultWorld() : World{
		if($this->defaultWorld === null){
			$this->defaultWorld = $this->createMock(World::class);
			$this->defaultWorld->method("isLoaded")->willReturn(true);
			$this->defaultWorld->method("getPlayers")->willReturn([]);
		}
		return $this->defaultWorld;
	}

	private function createPlayer(string $name = "Steve", ?Item $heldItem = null, ?World $world = null, ?Vector3 $pos = null) : Player{
		$player = $this->getMockBuilder(Player::class)
			->disableOriginalConstructor()
			->onlyMethods(["getName", "getUniqueId", "isSneaking", "isCreative", "isSpectator", "getInventory", "hasFiniteResources", "getViewers", "canInteract", "broadcastSound", "broadcastAnimation", "isConnected", "isOnline", "isAlive", "getArmorInventory", "getEyePos", "getDirectionVector"])
			->getMock();

		$player->method("getName")->willReturn($name);
		$player->method("getUniqueId")->willReturn(Uuid::uuid4());
		$player->method("isSneaking")->willReturn(false);
		$player->method("isCreative")->willReturn(false);
		$player->method("isSpectator")->willReturn(false);
		$player->method("hasFiniteResources")->willReturn(true);
		$player->method("getViewers")->willReturn([]);
		$player->method("canInteract")->willReturn(true);
		$player->method("isConnected")->willReturn(true);
		$player->method("isOnline")->willReturn(true);
		$player->method("isAlive")->willReturn(true);

		(new ReflectionProperty(Entity::class, "closed"))->setValue($player, false);
		(new ReflectionProperty(Entity::class, "id"))->setValue($player, Entity::nextRuntimeId());
		(new ReflectionProperty(Player::class, "logger"))->setValue($player, $this->createMock(Logger::class));
		(new ReflectionProperty(Living::class, "effectManager"))->setValue($player, new EffectManager($player));
		(new ReflectionProperty(Entity::class, "attributeMap"))->setValue($player, new AttributeMap());
		(new ReflectionClass(Player::class))->getMethod("addAttributes")->invoke($player);
		$armorInv = new ArmorInventory($player);
		(new ReflectionProperty(Living::class, "armorInventory"))->setValue($player, $armorInv);
		$player->method("getArmorInventory")->willReturn($armorInv);
		(new ReflectionProperty(Entity::class, "networkProperties"))->setValue($player, new EntityMetadataCollection());

		if($world === null){
			$world = $this->getDefaultWorld();
		}
		$location = new Location($pos?->x ?? 0.0, $pos?->y ?? 0.0, $pos?->z ?? 0.0, $world, 0.0, 0.0);
		(new ReflectionProperty(Entity::class, "location"))->setValue($player, $location);
		(new ReflectionProperty(Entity::class, "boundingBox"))->setValue($player, new AxisAlignedBB(
			$location->x - 0.3,
			$location->y,
			$location->z - 0.3,
			$location->x + 0.3,
			$location->y + 1.8,
			$location->z + 0.3
		));

		$player->method("getEyePos")->willReturnCallback(fn() => $location->add(0, 1.62, 0));
		$player->method("getDirectionVector")->willReturn(new Vector3(0, 0, 1));

		$inv = new PlayerInventory($player);
		if($heldItem !== null){
			$inv->setItemInHand($heldItem);
		}
		(new ReflectionProperty(Human::class, "inventory"))->setValue($player, $inv);
		(new ReflectionProperty(Human::class, "offHandInventory"))->setValue($player, new PlayerOffHandInventory($player));
		$player->method("getInventory")->willReturn($inv);

		$this->createdEntities[] = $player;
		return $player;
	}

	private function createCreeper(?World $world = null, ?Vector3 $pos = null) : Creeper{
		if($world === null){
			$world = $this->getDefaultWorld();
		}
		$location = new Location($pos?->x ?? 0.0, $pos?->y ?? 0.0, $pos?->z ?? 0.0, $world, 0.0, 0.0);
		$creeper = new Creeper($location);
		$this->createdEntities[] = $creeper;
		return $creeper;
	}

	private function createEnderman(?World $world = null, ?Vector3 $pos = null) : Enderman{
		if($world === null){
			$world = $this->getDefaultWorld();
		}
		$location = new Location($pos?->x ?? 0.0, $pos?->y ?? 0.0, $pos?->z ?? 0.0, $world, 0.0, 0.0);
		$enderman = new Enderman($location);
		$this->createdEntities[] = $enderman;
		return $enderman;
	}

	private function createSlime(?World $world = null, ?Vector3 $pos = null, int $size = Slime::SIZE_SMALL) : Slime{
		if($world === null){
			$world = $this->getDefaultWorld();
		}
		$location = new Location($pos?->x ?? 0.0, $pos?->y ?? 0.0, $pos?->z ?? 0.0, $world, 0.0, 0.0);
		$slime = new Slime($location);
		$slime->setSlimeSize($size);
		$this->createdEntities[] = $slime;
		return $slime;
	}

	private function createMagmaCube(?World $world = null, ?Vector3 $pos = null, int $size = Slime::SIZE_SMALL) : MagmaCube{
		if($world === null){
			$world = $this->getDefaultWorld();
		}
		$location = new Location($pos?->x ?? 0.0, $pos?->y ?? 0.0, $pos?->z ?? 0.0, $world, 0.0, 0.0);
		$magma = new MagmaCube($location);
		$magma->setSlimeSize($size);
		$this->createdEntities[] = $magma;
		return $magma;
	}

	private function syncNetworkProperties(Entity $entity) : void{
		(new ReflectionMethod($entity, "syncNetworkData"))->invoke($entity, $entity->getNetworkProperties());
	}

	// ---------------- CREEPER TESTS ---------------- //

	public function testCreeperInitializationAndStats() : void{
		$creeper = $this->createCreeper();
		self::assertSame(EntityIds::CREEPER, Creeper::getNetworkTypeId());
		self::assertSame("Creeper", $creeper->getName());
		self::assertSame(20, $creeper->getDefaultMaxHealth());
		self::assertSame(20, $creeper->getMaxHealth());
		self::assertSame(20.0, $creeper->getHealth());
		self::assertSame(Creeper::DEFAULT_FUSE_TICKS, $creeper->getFuse());
		self::assertFalse($creeper->isPowered());
		self::assertFalse($creeper->isIgnited());
		self::assertSame(1, count($creeper->getDrops()));
	}

	public function testCreeperPoweredStateAndNetworkSync() : void{
		$creeper = $this->createCreeper();
		$creeper->setPowered(true);
		self::assertTrue($creeper->isPowered());

		$this->syncNetworkProperties($creeper);
		/** @var LongMetadataProperty|null $flagsProp */
		$flagsProp = $creeper->getNetworkProperties()->getAll()[EntityMetadataProperties::FLAGS] ?? null;
		self::assertNotNull($flagsProp);
		self::assertTrue($flagsProp->getValue() !== 0);

		$creeper->setPowered(false);
		self::assertFalse($creeper->isPowered());
	}

	public function testCreeperFlintAndSteelForcedIgnition() : void{
		$creeper = $this->createCreeper();
		$player = $this->createPlayer(heldItem: VanillaItems::FLINT_AND_STEEL());

		self::assertFalse($creeper->isIgnited());
		$interacted = $creeper->interact($player, new Vector3(0, 0, 0));

		self::assertTrue($interacted);
		self::assertTrue($creeper->isIgnited());
		self::assertSame(1, $creeper->getFuseDirection());
	}

	public function testCreeperProximitySwellAndDeflate() : void{
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);
		$world->method("getPlayers")->willReturn([]);

		$nearbyEntities = [];
		$world->method("getNearbyEntities")->willReturnCallback(function() use (&$nearbyEntities) : array{
			return $nearbyEntities;
		});

		$creeper = $this->createCreeper(world: $world, pos: new Vector3(0, 60, 0));

		// Player at distance 2.0 (distSq = 4.0 <= 6.25) -> swells
		$player = $this->createPlayer(world: $world, pos: new Vector3(2, 60, 0));
		$nearbyEntities = [$player];

		$creeper->onUpdate(1);
		self::assertSame(1, $creeper->getFuseDirection());
		self::assertTrue($creeper->isIgnited());

		// Player moves far away (distance 8.0) -> stops swelling, deflates
		(new ReflectionProperty(Entity::class, "location"))->setValue($player, new Location(8, 60, 0, $world, 0, 0));
		$creeper->onUpdate(2);
		self::assertSame(-1, $creeper->getFuseDirection());
		self::assertFalse($creeper->isIgnited());
	}

	public function testCreeperNbtSerialization() : void{
		$creeper = $this->createCreeper();
		$creeper->setFuse(15);
		$creeper->setPowered(true);
		$creeper->setFuseDirection(1);

		$nbt = $creeper->saveNBT();
		self::assertSame(15, $nbt->getInt(Creeper::TAG_FUSE));
		self::assertSame(1, $nbt->getByte(Creeper::TAG_POWERED));

		$loaded = new Creeper(new Location(0, 0, 0, $this->getDefaultWorld(), 0, 0), $nbt);
		$this->createdEntities[] = $loaded;
		self::assertSame(15, $loaded->getFuse());
		self::assertTrue($loaded->isPowered());
	}

	// ---------------- ENDERMAN TESTS ---------------- //

	public function testEndermanInitializationAndStats() : void{
		$enderman = $this->createEnderman();
		self::assertSame(EntityIds::ENDERMAN, Enderman::getNetworkTypeId());
		self::assertSame("Enderman", $enderman->getName());
		self::assertSame(40, $enderman->getDefaultMaxHealth());
		self::assertSame(40, $enderman->getMaxHealth());
		self::assertSame(40.0, $enderman->getHealth());
		self::assertFalse($enderman->isAngry());
		self::assertNull($enderman->getCarriedBlock());
	}

	public function testEndermanAngryStateAndCarriedBlock() : void{
		$enderman = $this->createEnderman();
		$enderman->setAngry(true);
		self::assertTrue($enderman->isAngry());

		$block = VanillaBlocks::GRASS();
		$enderman->setCarriedBlock($block);
		self::assertSame($block, $enderman->getCarriedBlock());

		$nbt = $enderman->saveNBT();
		self::assertSame(1, $nbt->getByte(Enderman::TAG_ANGRY));
		self::assertSame($block->getTypeId(), $nbt->getShort(Enderman::TAG_CARRIED_BLOCK));

		$loaded = new Enderman(new Location(0, 0, 0, $this->getDefaultWorld(), 0, 0), $nbt);
		$this->createdEntities[] = $loaded;
		self::assertTrue($loaded->isAngry());
	}

	public function testEndermanEvadesProjectileDamage() : void{
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);
		$world->method("getPlayers")->willReturn([]);
		$world->method("getMaxY")->willReturn(320);

		$enderman = $this->createEnderman(world: $world);
		$attacker = $this->createPlayer(world: $world);

		$ev = $this->getMockBuilder(EntityDamageByChildEntityEvent::class)
			->disableOriginalConstructor()
			->onlyMethods(["getCause", "isCancelled", "cancel"])
			->getMock();
		$ev->method("getCause")->willReturn(EntityDamageEvent::CAUSE_PROJECTILE);

		$cancelled = false;
		$ev->method("cancel")->willReturnCallback(function() use (&$cancelled){
			$cancelled = true;
		});

		$enderman->attack($ev);

		self::assertTrue($cancelled);
		self::assertSame(40.0, $enderman->getHealth());
	}

	// ---------------- SLIME & MAGMA CUBE TESTS ---------------- //

	public function testSlimeSizeAndHealthScaling() : void{
		$slimeSmall = $this->createSlime(size: Slime::SIZE_SMALL);
		self::assertSame(Slime::SIZE_SMALL, $slimeSmall->getSlimeSize());
		self::assertSame(1, $slimeSmall->getMaxHealth());
		self::assertSame(1.0, $slimeSmall->getHealth());
		self::assertSame(0.0, $slimeSmall->getAttackDamage());

		$slimeMed = $this->createSlime(size: Slime::SIZE_MEDIUM);
		self::assertSame(Slime::SIZE_MEDIUM, $slimeMed->getSlimeSize());
		self::assertSame(4, $slimeMed->getMaxHealth());
		self::assertSame(4.0, $slimeMed->getHealth());
		self::assertSame(2.0, $slimeMed->getAttackDamage());

		$slimeLarge = $this->createSlime(size: Slime::SIZE_LARGE);
		self::assertSame(Slime::SIZE_LARGE, $slimeLarge->getSlimeSize());
		self::assertSame(16, $slimeLarge->getMaxHealth());
		self::assertSame(16.0, $slimeLarge->getHealth());
		self::assertSame(4.0, $slimeLarge->getAttackDamage());
	}

	public function testSlimeNbtSerialization() : void{
		$slime = $this->createSlime(size: Slime::SIZE_LARGE);
		$nbt = $slime->saveNBT();
		self::assertSame(Slime::SIZE_LARGE, $nbt->getInt(Slime::TAG_SIZE));

		$loaded = new Slime(new Location(0, 0, 0, $this->getDefaultWorld(), 0, 0), $nbt);
		$this->createdEntities[] = $loaded;
		self::assertSame(Slime::SIZE_LARGE, $loaded->getSlimeSize());
		self::assertSame(16, $loaded->getMaxHealth());
	}

	public function testMagmaCubeImmunitiesAndDamage() : void{
		$magma = $this->createMagmaCube(size: Slime::SIZE_LARGE);
		self::assertSame(EntityIds::MAGMA_CUBE, MagmaCube::getNetworkTypeId());
		self::assertSame("Magma Cube", $magma->getName());
		self::assertSame(6.0, $magma->getAttackDamage());

		// Test fire immunity
		$fireEv = new EntityDamageEvent($magma, EntityDamageEvent::CAUSE_FIRE, 5.0);
		$magma->attack($fireEv);
		self::assertTrue($fireEv->isCancelled());

		// Test lava immunity
		$lavaEv = new EntityDamageEvent($magma, EntityDamageEvent::CAUSE_LAVA, 10.0);
		$magma->attack($lavaEv);
		self::assertTrue($lavaEv->isCancelled());

		// Test fall immunity
		$fallEv = new EntityDamageEvent($magma, EntityDamageEvent::CAUSE_FALL, 20.0);
		$magma->attack($fallEv);
		self::assertTrue($fallEv->isCancelled());
	}
}
