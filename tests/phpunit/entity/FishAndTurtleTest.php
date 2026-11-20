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
use pocketmine\entity\AttributeMap;
use pocketmine\entity\effect\EffectManager;
use pocketmine\entity\effect\VanillaEffects;
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
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\network\mcpe\protocol\types\entity\IntMetadataProperty;
use pocketmine\entity\HungerManager;
use pocketmine\player\Player;
use pocketmine\world\World;
use Ramsey\Uuid\Uuid;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

class FishAndTurtleTest extends TestCase{

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
			->onlyMethods(["getName", "getUniqueId", "isSneaking", "isCreative", "isSpectator", "getInventory", "hasFiniteResources", "getViewers", "canInteract", "broadcastSound", "broadcastAnimation", "isConnected", "isOnline", "isAlive"])
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
		(new ReflectionProperty(Living::class, "armorInventory"))->setValue($player, new ArmorInventory($player));
		(new ReflectionProperty(Human::class, "hungerManager"))->setValue($player, new HungerManager($player));
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

	private function syncNetworkProperties(Entity $entity) : void{
		(new ReflectionMethod($entity, "syncNetworkData"))->invoke($entity, $entity->getNetworkProperties());
	}

	private function createTurtle(?World $world = null, ?Vector3 $pos = null) : SeaTurtle{
		if($world === null){
			$world = $this->getDefaultWorld();
		}
		$location = new Location($pos?->x ?? 0.0, $pos?->y ?? 0.0, $pos?->z ?? 0.0, $world, 0.0, 0.0);
		$turtle = new SeaTurtle($location);
		$this->createdEntities[] = $turtle;
		return $turtle;
	}

	private function createPufferfish(?World $world = null, ?Vector3 $pos = null) : Pufferfish{
		if($world === null){
			$world = $this->getDefaultWorld();
		}
		$location = new Location($pos?->x ?? 0.0, $pos?->y ?? 0.0, $pos?->z ?? 0.0, $world, 0.0, 0.0);
		$puffer = new Pufferfish($location);
		$this->createdEntities[] = $puffer;
		return $puffer;
	}

	private function createSalmon(?World $world = null, ?Vector3 $pos = null) : Salmon{
		if($world === null){
			$world = $this->getDefaultWorld();
		}
		$location = new Location($pos?->x ?? 0.0, $pos?->y ?? 0.0, $pos?->z ?? 0.0, $world, 0.0, 0.0);
		$salmon = new Salmon($location);
		$this->createdEntities[] = $salmon;
		return $salmon;
	}

	private function createCod(?World $world = null, ?Vector3 $pos = null) : Cod{
		if($world === null){
			$world = $this->getDefaultWorld();
		}
		$location = new Location($pos?->x ?? 0.0, $pos?->y ?? 0.0, $pos?->z ?? 0.0, $world, 0.0, 0.0);
		$cod = new Cod($location);
		$this->createdEntities[] = $cod;
		return $cod;
	}

	private function createTropicalFish(?World $world = null, ?Vector3 $pos = null) : TropicalFish{
		if($world === null){
			$world = $this->getDefaultWorld();
		}
		$location = new Location($pos?->x ?? 0.0, $pos?->y ?? 0.0, $pos?->z ?? 0.0, $world, 0.0, 0.0);
		$fish = new TropicalFish($location);
		$this->createdEntities[] = $fish;
		return $fish;
	}

	// ---------------- SEA TURTLE TESTS ---------------- //

	public function testTurtleInitializationAndStats() : void{
		$turtle = $this->createTurtle();
		self::assertSame(EntityIds::TURTLE, SeaTurtle::getNetworkTypeId());
		self::assertSame("Sea Turtle", $turtle->getName());
		self::assertSame(30, $turtle->getDefaultMaxHealth());
		self::assertSame(30, $turtle->getMaxHealth());
		self::assertSame(30.0, $turtle->getHealth());
		self::assertTrue($turtle->canBreatheInWater());
		self::assertFalse($turtle->isBaby());
		self::assertFalse($turtle->hasEgg());
		self::assertNotNull($turtle->getHomePos());
	}

	public function testTurtleHomePosAndEgg() : void{
		$turtle = $this->createTurtle();
		$newHome = new Vector3(100, 64, -200);
		$turtle->setHomePos($newHome);
		self::assertEquals($newHome, $turtle->getHomePos());

		self::assertFalse($turtle->hasEgg());
		$turtle->setHasEgg(true);
		self::assertTrue($turtle->hasEgg());
	}

	public function testTurtleBabyAndScuteDropOnGrowUp() : void{
		$droppedItem = null;
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);
		$world->method("getPlayers")->willReturn([]);
		$world->method("dropItem")->willReturnCallback(function(Vector3 $pos, Item $item) use (&$droppedItem){
			$droppedItem = $item;
			return null;
		});

		$turtle = $this->createTurtle(world: $world);
		$turtle->setBaby(true);
		self::assertTrue($turtle->isBaby());
		self::assertSame(0.3, $turtle->getScale());
		self::assertSame(-24000, $turtle->getAge());

		$turtle->setAge(0);
		self::assertFalse($turtle->isBaby());
		self::assertSame(1.0, $turtle->getScale());
		self::assertNotNull($droppedItem);
		self::assertSame(VanillaItems::SCUTE()->getTypeId(), $droppedItem->getTypeId());
	}

	public function testTurtleInteractBabyFoodAcceleratesGrowth() : void{
		$turtle = $this->createTurtle();
		$turtle->setBaby(true);
		$initialAge = $turtle->getAge();

		$player = $this->createPlayer(heldItem: VanillaItems::DRIED_KELP()->setCount(5));
		$interacted = $turtle->interact($player, new Vector3(0, 0, 0));

		self::assertTrue($interacted);
		self::assertSame(4, $player->getInventory()->getItemInHand()->getCount());
		self::assertSame($initialAge + 2400, $turtle->getAge());
	}

	public function testTurtleBreedingMechanic() : void{
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);
		$world->method("getPlayers")->willReturn([]);

		$t1 = $this->createTurtle(world: $world, pos: new Vector3(0, 64, 0));
		$t2 = $this->createTurtle(world: $world, pos: new Vector3(2, 64, 0));

		$player = $this->createPlayer(heldItem: VanillaItems::DRIED_KELP()->setCount(2), world: $world);

		self::assertTrue($t1->canBreed());
		self::assertTrue($t2->canBreed());

		$t1->interact($player, new Vector3(0, 0, 0));
		self::assertTrue($t1->isInLove());

		$t2->interact($player, new Vector3(0, 0, 0));
		self::assertTrue($t2->isInLove());

		$t1->breedWith($t2);

		self::assertFalse($t1->isInLove());
		self::assertFalse($t2->isInLove());
		self::assertTrue($t1->hasEgg());
		self::assertSame(SeaTurtle::BREEDING_COOLDOWN_TICKS, $t1->getBreedingCooldownTicks());
		self::assertSame(SeaTurtle::BREEDING_COOLDOWN_TICKS, $t2->getBreedingCooldownTicks());
		self::assertFalse($t1->canBreed());
	}

	public function testTurtleNbtSerialization() : void{
		$turtle = $this->createTurtle();
		$turtle->setHomePos(new Vector3(15, 62, -45));
		$turtle->setHasEgg(true);
		$turtle->setAge(-12000);
		$turtle->setInLove(400);
		$turtle->setBreedingCooldownTicks(3000);

		$nbt = $turtle->saveNBT();
		self::assertSame(15, $nbt->getInt(SeaTurtle::TAG_HOME_POS_X));
		self::assertSame(62, $nbt->getInt(SeaTurtle::TAG_HOME_POS_Y));
		self::assertSame(-45, $nbt->getInt(SeaTurtle::TAG_HOME_POS_Z));
		self::assertSame(1, $nbt->getByte(SeaTurtle::TAG_HAS_EGG));
		self::assertSame(-12000, $nbt->getInt(SeaTurtle::TAG_AGE));
		self::assertSame(400, $nbt->getInt(SeaTurtle::TAG_IN_LOVE));
		self::assertSame(3000, $nbt->getInt(SeaTurtle::TAG_BREEDING_COOLDOWN));

		$loaded = new SeaTurtle(new Location(0, 0, 0, $this->getDefaultWorld(), 0, 0), $nbt);
		$this->createdEntities[] = $loaded;
		self::assertEquals(new Vector3(15, 62, -45), $loaded->getHomePos());
		self::assertTrue($loaded->hasEgg());
		self::assertSame(-12000, $loaded->getAge());
		self::assertTrue($loaded->isBaby());
		self::assertSame(400, (new ReflectionProperty(SeaTurtle::class, "inLoveTicks"))->getValue($loaded));
		self::assertSame(3000, $loaded->getBreedingCooldownTicks());
	}

	// ---------------- PUFFERFISH TESTS ---------------- //

	public function testPufferfishInitializationAndStats() : void{
		$puffer = $this->createPufferfish();
		self::assertSame(EntityIds::PUFFERFISH, Pufferfish::getNetworkTypeId());
		self::assertSame("Pufferfish", $puffer->getName());
		self::assertSame(3, $puffer->getDefaultMaxHealth());
		self::assertSame(3, $puffer->getMaxHealth());
		self::assertSame(3.0, $puffer->getHealth());
		self::assertSame(Pufferfish::PUFF_DEFLATED, $puffer->getPuffState());
		self::assertSame(VanillaItems::PUFFERFISH()->getTypeId(), $puffer->getDrops()[0]->getTypeId());
	}

	public function testPufferfishStateChangesAndNetworkSync() : void{
		$puffer = $this->createPufferfish();

		$puffer->setPuffState(Pufferfish::PUFF_HALF);
		self::assertSame(Pufferfish::PUFF_HALF, $puffer->getPuffState());
		$this->syncNetworkProperties($puffer);
		/** @var ByteMetadataProperty|null $byteProp */
		$byteProp = $puffer->getNetworkProperties()->getAll()[EntityMetadataProperties::PUFFERFISH_SIZE] ?? null;
		self::assertNotNull($byteProp);
		self::assertSame(Pufferfish::PUFF_HALF, $byteProp->getValue());

		$puffer->setPuffState(Pufferfish::PUFF_FULL);
		self::assertSame(Pufferfish::PUFF_FULL, $puffer->getPuffState());
		$this->syncNetworkProperties($puffer);
		/** @var ByteMetadataProperty|null $bytePropFull */
		$bytePropFull = $puffer->getNetworkProperties()->getAll()[EntityMetadataProperties::PUFFERFISH_SIZE] ?? null;
		self::assertNotNull($bytePropFull);
		self::assertSame(Pufferfish::PUFF_FULL, $bytePropFull->getValue());

		$puffer->setPuffState(Pufferfish::PUFF_DEFLATED);
		self::assertSame(Pufferfish::PUFF_DEFLATED, $puffer->getPuffState());
		$this->syncNetworkProperties($puffer);
		/** @var ByteMetadataProperty|null $bytePropDef */
		$bytePropDef = $puffer->getNetworkProperties()->getAll()[EntityMetadataProperties::PUFFERFISH_SIZE] ?? null;
		self::assertNotNull($bytePropDef);
		self::assertSame(Pufferfish::PUFF_DEFLATED, $bytePropDef->getValue());
	}

	public function testPufferfishStingAppliesPoison() : void{
		$puffer = $this->createPufferfish();
		$player = $this->createPlayer();

		self::assertFalse($player->getEffects()->has(VanillaEffects::POISON()));
		$puffer->sting($player);
		self::assertTrue($player->getEffects()->has(VanillaEffects::POISON()));
		$poison = $player->getEffects()->get(VanillaEffects::POISON());
		self::assertNotNull($poison);
		self::assertSame(140, $poison->getDuration());
		self::assertSame(1, $poison->getAmplifier());
	}

	public function testPufferfishProximityInflationAndDeflation() : void{
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);
		$world->method("getPlayers")->willReturn([]);

		$nearbyEntities = [];
		$world->method("getNearbyEntities")->willReturnCallback(function() use (&$nearbyEntities) : array{
			return $nearbyEntities;
		});

		$puffer = $this->createPufferfish(world: $world, pos: new Vector3(0, 60, 0));

		// Player at distance 4 (distSq = 16) -> between 3 and 5 blocks -> PUFF_HALF
		$p1 = $this->createPlayer(world: $world, pos: new Vector3(4, 60, 0));
		$nearbyEntities = [$p1];

		$puffer->onUpdate(1);
		self::assertSame(Pufferfish::PUFF_HALF, $puffer->getPuffState());

		// Player at distance 2 (distSq = 4) -> <= 3 blocks -> PUFF_FULL
		$p2 = $this->createPlayer(world: $world, pos: new Vector3(2, 60, 0));
		$nearbyEntities = [$p2];

		$puffer->onUpdate(2);
		self::assertSame(Pufferfish::PUFF_FULL, $puffer->getPuffState());

		// No player nearby -> deflate cooldown counts down, then deflates
		$nearbyEntities = [];
		$puffer->onUpdate(63);
		$puffer->onUpdate(64);
		self::assertSame(Pufferfish::PUFF_DEFLATED, $puffer->getPuffState());
	}

	public function testPufferfishNbtSerialization() : void{
		$puffer = $this->createPufferfish();
		$puffer->setPuffState(Pufferfish::PUFF_FULL);

		$nbt = $puffer->saveNBT();
		self::assertSame(Pufferfish::PUFF_FULL, $nbt->getInt(Pufferfish::TAG_PUFF_STATE));

		$loaded = new Pufferfish(new Location(0, 0, 0, $this->getDefaultWorld(), 0, 0), $nbt);
		$this->createdEntities[] = $loaded;
		self::assertSame(Pufferfish::PUFF_FULL, $loaded->getPuffState());
	}

	// ---------------- SALMON TESTS ---------------- //

	public function testSalmonInitializationVariantsAndDrops() : void{
		$salmon = $this->createSalmon();
		self::assertSame(EntityIds::SALMON, Salmon::getNetworkTypeId());
		self::assertSame("Salmon", $salmon->getName());
		self::assertSame(3, $salmon->getDefaultMaxHealth());
		self::assertSame(3, $salmon->getMaxHealth());
		self::assertSame(3.0, $salmon->getHealth());
		self::assertSame(VanillaItems::RAW_SALMON()->getTypeId(), $salmon->getDrops()[0]->getTypeId());

		$salmon->setVariant(Salmon::SIZE_SMALL);
		self::assertSame(Salmon::SIZE_SMALL, $salmon->getVariant());
		self::assertSame(0.75, $salmon->getScale());
		$this->syncNetworkProperties($salmon);
		/** @var IntMetadataProperty|null $intPropSmall */
		$intPropSmall = $salmon->getNetworkProperties()->getAll()[EntityMetadataProperties::VARIANT] ?? null;
		self::assertNotNull($intPropSmall);
		self::assertSame(Salmon::SIZE_SMALL, $intPropSmall->getValue());

		$salmon->setVariant(Salmon::SIZE_MEDIUM);
		self::assertSame(Salmon::SIZE_MEDIUM, $salmon->getVariant());
		self::assertSame(1.0, $salmon->getScale());

		$salmon->setVariant(Salmon::SIZE_LARGE);
		self::assertSame(Salmon::SIZE_LARGE, $salmon->getVariant());
		self::assertSame(1.25, $salmon->getScale());
		$this->syncNetworkProperties($salmon);
		/** @var IntMetadataProperty|null $intPropLarge */
		$intPropLarge = $salmon->getNetworkProperties()->getAll()[EntityMetadataProperties::VARIANT] ?? null;
		self::assertNotNull($intPropLarge);
		self::assertSame(Salmon::SIZE_LARGE, $intPropLarge->getValue());
	}

	public function testSalmonNbtSerialization() : void{
		$salmon = $this->createSalmon();
		$salmon->setVariant(Salmon::SIZE_LARGE);

		$nbt = $salmon->saveNBT();
		self::assertSame(Salmon::SIZE_LARGE, $nbt->getInt(Salmon::TAG_VARIANT));

		$loaded = new Salmon(new Location(0, 0, 0, $this->getDefaultWorld(), 0, 0), $nbt);
		$this->createdEntities[] = $loaded;
		self::assertSame(Salmon::SIZE_LARGE, $loaded->getVariant());
		self::assertSame(1.25, $loaded->getScale());
	}

	// ---------------- COD TESTS ---------------- //

	public function testCodInitializationAndDrops() : void{
		$cod = $this->createCod();
		self::assertSame(EntityIds::COD, Cod::getNetworkTypeId());
		self::assertSame("Cod", $cod->getName());
		self::assertSame(3, $cod->getDefaultMaxHealth());
		self::assertSame(3, $cod->getMaxHealth());
		self::assertSame(3.0, $cod->getHealth());
		self::assertSame(VanillaItems::RAW_FISH()->getTypeId(), $cod->getDrops()[0]->getTypeId());
	}

	// ---------------- TROPICAL FISH TESTS ---------------- //

	public function testTropicalFishInitializationAndVariants() : void{
		$fish = $this->createTropicalFish();
		self::assertSame(EntityIds::TROPICALFISH, TropicalFish::getNetworkTypeId());
		self::assertSame("Tropical Fish", $fish->getName());
		self::assertSame(3, $fish->getDefaultMaxHealth());
		self::assertSame(3, $fish->getMaxHealth());
		self::assertSame(3.0, $fish->getHealth());
		self::assertSame(VanillaItems::CLOWNFISH()->getTypeId(), $fish->getDrops()[0]->getTypeId());

		$fish->setVariant(0x10203);
		self::assertSame(0x10203, $fish->getVariant());
		$this->syncNetworkProperties($fish);
		/** @var IntMetadataProperty|null $intProp */
		$intProp = $fish->getNetworkProperties()->getAll()[EntityMetadataProperties::VARIANT] ?? null;
		self::assertNotNull($intProp);
		self::assertSame(0x10203, $intProp->getValue());

		$nbt = $fish->saveNBT();
		self::assertSame(0x10203, $nbt->getInt(TropicalFish::TAG_VARIANT));

		$loaded = new TropicalFish(new Location(0, 0, 0, $this->getDefaultWorld(), 0, 0), $nbt);
		$this->createdEntities[] = $loaded;
		self::assertSame(0x10203, $loaded->getVariant());
	}
}
