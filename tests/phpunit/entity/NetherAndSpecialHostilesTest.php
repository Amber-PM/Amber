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
use pocketmine\block\Block;
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\AttributeMap;
use pocketmine\entity\effect\EffectManager;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\entity\HungerManager;
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
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\network\mcpe\protocol\types\entity\LongMetadataProperty;
use pocketmine\player\Player;
use pocketmine\world\World;
use Ramsey\Uuid\Uuid;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

class NetherAndSpecialHostilesTest extends TestCase{

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
		(new ReflectionProperty(EntityDamageByEntityEvent::class, "knockBack"))->setValue($ev, Living::DEFAULT_KNOCKBACK_FORCE);
		(new ReflectionProperty(EntityDamageByEntityEvent::class, "verticalKnockBackLimit"))->setValue($ev, Living::DEFAULT_KNOCKBACK_VERTICAL_LIMIT);
		return $ev;
	}

	private function createPhantom(?World $world = null, ?Vector3 $pos = null) : Phantom{
		if($world === null){
			$world = $this->getDefaultWorld();
		}
		$location = new Location($pos?->x ?? 0.0, $pos?->y ?? 0.0, $pos?->z ?? 0.0, $world, 0.0, 0.0);
		$phantom = new Phantom($location);
		$this->createdEntities[] = $phantom;
		return $phantom;
	}

	private function createDrowned(?World $world = null, ?Vector3 $pos = null) : Drowned{
		if($world === null){
			$world = $this->getDefaultWorld();
		}
		$location = new Location($pos?->x ?? 0.0, $pos?->y ?? 0.0, $pos?->z ?? 0.0, $world, 0.0, 0.0);
		$drowned = new Drowned($location);
		$this->createdEntities[] = $drowned;
		return $drowned;
	}

	private function createZombie(?World $world = null, ?Vector3 $pos = null) : Zombie{
		if($world === null){
			$world = $this->getDefaultWorld();
		}
		$location = new Location($pos?->x ?? 0.0, $pos?->y ?? 0.0, $pos?->z ?? 0.0, $world, 0.0, 0.0);
		$zombie = new Zombie($location);
		$this->createdEntities[] = $zombie;
		return $zombie;
	}

	private function createWitch(?World $world = null, ?Vector3 $pos = null) : Witch{
		if($world === null){
			$world = $this->getDefaultWorld();
		}
		$location = new Location($pos?->x ?? 0.0, $pos?->y ?? 0.0, $pos?->z ?? 0.0, $world, 0.0, 0.0);
		$witch = new Witch($location);
		$this->createdEntities[] = $witch;
		return $witch;
	}

	private function createGuardian(?World $world = null, ?Vector3 $pos = null) : Guardian{
		if($world === null){
			$world = $this->getDefaultWorld();
		}
		$location = new Location($pos?->x ?? 0.0, $pos?->y ?? 0.0, $pos?->z ?? 0.0, $world, 0.0, 0.0);
		$guardian = new Guardian($location);
		$this->createdEntities[] = $guardian;
		return $guardian;
	}

	private function createElderGuardian(?World $world = null, ?Vector3 $pos = null) : ElderGuardian{
		if($world === null){
			$world = $this->getDefaultWorld();
		}
		$location = new Location($pos?->x ?? 0.0, $pos?->y ?? 0.0, $pos?->z ?? 0.0, $world, 0.0, 0.0);
		$elder = new ElderGuardian($location);
		$this->createdEntities[] = $elder;
		return $elder;
	}

	private function syncNetworkProperties(Entity $entity) : void{
		(new ReflectionMethod($entity, "syncNetworkData"))->invoke($entity, $entity->getNetworkProperties());
	}

	// ---------------- PHANTOM TESTS ---------------- //

	public function testPhantomInitializationAndStats() : void{
		$phantom = $this->createPhantom();
		self::assertSame(EntityIds::PHANTOM, Phantom::getNetworkTypeId());
		self::assertSame("Phantom", $phantom->getName());
		self::assertSame(20, $phantom->getDefaultMaxHealth());
		self::assertSame(20, $phantom->getMaxHealth());
		self::assertSame(20.0, $phantom->getHealth());
		self::assertSame(1, count($phantom->getDrops()));
		self::assertSame(VanillaItems::PHANTOM_MEMBRANE()->getTypeId(), $phantom->getDrops()[0]->getTypeId());
	}

	public function testPhantomFallDamageImmunity() : void{
		$phantom = $this->createPhantom();
		$ev = new EntityDamageEvent($phantom, EntityDamageEvent::CAUSE_FALL, 25.0);
		$phantom->attack($ev);
		self::assertTrue($ev->isCancelled());
		self::assertSame(20.0, $phantom->getHealth());
	}

	// ---------------- DROWNED & ZOMBIE TESTS ---------------- //

	public function testDrownedInitializationAndBreathing() : void{
		$drowned = $this->createDrowned();
		self::assertSame(EntityIds::DROWNED, Drowned::getNetworkTypeId());
		self::assertSame("Drowned", $drowned->getName());
		self::assertSame(20, $drowned->getDefaultMaxHealth());
		self::assertSame(20, $drowned->getMaxHealth());
		self::assertSame(20.0, $drowned->getHealth());
		self::assertTrue($drowned->canBreatheInWater());
	}

	public function testZombieDrowningConversion() : void{
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);
		$world->method("getPlayers")->willReturn([]);
		$world->method("getTime")->willReturn(18000); // Nighttime
		$world->method("getBlockAt")->willReturnCallback(function(int $x, int $y, int $z) use ($world) : Block{
			$water = VanillaBlocks::WATER();
			$water->position($world, $x, $y, $z);
			return $water;
		});

		$zombie = $this->createZombie(world: $world);

		self::assertFalse($zombie->isConvertingToDrowned());

		// 600 ticks submerged -> starts conversion
		$zombie->onUpdate(600);
		self::assertTrue($zombie->isConvertingToDrowned());
		self::assertSame(Zombie::CONVERSION_DURATION_TICKS, $zombie->getConversionTicks());

		// NBT saving while converting
		$nbt = $zombie->saveNBT();
		self::assertSame(Zombie::CONVERSION_DURATION_TICKS, $nbt->getInt(Zombie::TAG_DROWNED_CONVERSION_TIME));

		// Conversion finishes -> converts to Drowned
		$drowned = $zombie->convertToDrowned();
		$this->createdEntities[] = $drowned;
		self::assertInstanceOf(Drowned::class, $drowned);
		self::assertTrue($zombie->isFlaggedForDespawn());
		self::assertTrue($drowned->canBreatheInWater());
	}

	// ---------------- WITCH TESTS ---------------- //

	public function testWitchInitializationAndStats() : void{
		$witch = $this->createWitch();
		self::assertSame(EntityIds::WITCH, Witch::getNetworkTypeId());
		self::assertSame("Witch", $witch->getName());
		self::assertSame(26, $witch->getDefaultMaxHealth());
		self::assertSame(26, $witch->getMaxHealth());
		self::assertSame(26.0, $witch->getHealth());
		self::assertFalse($witch->isDrinkingPotion());
	}

	public function testWitchDrinkingHealingPotionWhenDamaged() : void{
		$witch = $this->createWitch();
		$witch->setHealth(10.0);

		$witch->startDrinkingPotion();
		self::assertTrue($witch->isDrinkingPotion());

		$witch->finishDrinkingPotion();
		self::assertFalse($witch->isDrinkingPotion());
		self::assertSame(14.0, $witch->getHealth()); // healed 4.0
	}

	public function testWitchDrinkingFireResistanceWhenOnFire() : void{
		$witch = $this->createWitch();
		$witch->setOnFire(10);
		self::assertTrue($witch->isOnFire());

		$witch->finishDrinkingPotion();
		self::assertTrue($witch->getEffects()->has(VanillaEffects::FIRE_RESISTANCE()));
	}

	public function testWitchDrinkingWaterBreathingWhenUnderwater() : void{
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);
		$world->method("getPlayers")->willReturn([]);
		$world->method("getBlockAt")->willReturnCallback(function(int $x, int $y, int $z) use ($world) : Block{
			$water = VanillaBlocks::WATER();
			$water->position($world, $x, $y, $z);
			return $water;
		});

		$witch = $this->createWitch(world: $world);

		$witch->finishDrinkingPotion();
		self::assertTrue($witch->getEffects()->has(VanillaEffects::WATER_BREATHING()));
	}

	public function testWitchRangedAttackAppliesDebuffs() : void{
		$witch = $this->createWitch();
		$target = $this->createPlayer();

		// Target distance >= 8 blocks -> slowness
		$witch->attackTarget($target, 64.0);
		self::assertTrue($target->getEffects()->has(VanillaEffects::SLOWNESS()));

		// Target close and healthy -> poison
		$targetClose = $this->createPlayer();
		$witch->attackTarget($targetClose, 16.0);
		self::assertTrue($targetClose->getEffects()->has(VanillaEffects::POISON()));
	}

	public function testWitchNbtSerialization() : void{
		$witch = $this->createWitch();
		$witch->startDrinkingPotion();

		$nbt = $witch->saveNBT();
		self::assertSame(1, $nbt->getByte(Witch::TAG_DRINKING_POTION));

		$loaded = new Witch(new Location(0, 0, 0, $this->getDefaultWorld(), 0, 0), $nbt);
		$this->createdEntities[] = $loaded;
		self::assertTrue($loaded->isDrinkingPotion());
	}

	// ---------------- GUARDIAN & ELDER GUARDIAN TESTS ---------------- //

	public function testGuardianInitializationAndStats() : void{
		$guardian = $this->createGuardian();
		self::assertSame(EntityIds::GUARDIAN, Guardian::getNetworkTypeId());
		self::assertSame("Guardian", $guardian->getName());
		self::assertSame(30, $guardian->getDefaultMaxHealth());
		self::assertSame(30, $guardian->getMaxHealth());
		self::assertSame(30.0, $guardian->getHealth());
		self::assertTrue($guardian->areSpikesExtended());
		self::assertNull($guardian->getLaserTarget());
		self::assertSame(5.0, $guardian->getLaserDamage());
	}

	public function testGuardianThornsDefense() : void{
		$guardian = $this->createGuardian();
		$attacker = $this->createPlayer();

		self::assertSame(20.0, $attacker->getHealth());

		// Attack guardian with extended spikes
		$damageEv = $this->createDamageByEntityEvent($attacker, $guardian, 4.0);
		$guardian->attack($damageEv);

		// Attacker took 2.0 thorns damage!
		self::assertSame(18.0, $attacker->getHealth());
	}

	public function testGuardianLaserTargetingAndNetworkSync() : void{
		$guardian = $this->createGuardian();
		$target = $this->createPlayer();

		$guardian->setLaserTarget($target);
		self::assertSame($target, $guardian->getLaserTarget());

		$this->syncNetworkProperties($guardian);
		/** @var LongMetadataProperty|null $targetEidProp */
		$targetEidProp = $guardian->getNetworkProperties()->getAll()[EntityMetadataProperties::TARGET_EID] ?? null;
		self::assertNotNull($targetEidProp);
		self::assertSame($target->getId(), $targetEidProp->getValue());

		$guardian->setLaserTarget(null);
		$this->syncNetworkProperties($guardian);
		/** @var LongMetadataProperty|null $targetEidPropNull */
		$targetEidPropNull = $guardian->getNetworkProperties()->getAll()[EntityMetadataProperties::TARGET_EID] ?? null;
		self::assertNotNull($targetEidPropNull);
		self::assertSame(0, $targetEidPropNull->getValue());
	}

	public function testElderGuardianInitializationCurseAndDrops() : void{
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);
		$world->method("getPlayers")->willReturn([]);

		$player = $this->createPlayer(world: $world, pos: new Vector3(10, 60, 10));
		$world->method("getNearbyEntities")->willReturn([$player]);

		$elder = $this->createElderGuardian(world: $world, pos: new Vector3(0, 60, 0));
		self::assertSame(EntityIds::ELDER_GUARDIAN, ElderGuardian::getNetworkTypeId());
		self::assertSame("Elder Guardian", $elder->getName());
		self::assertSame(80, $elder->getDefaultMaxHealth());
		self::assertSame(80, $elder->getMaxHealth());
		self::assertSame(80.0, $elder->getHealth());
		self::assertSame(8.0, $elder->getLaserDamage());

		// Test mining fatigue curse application
		self::assertFalse($player->getEffects()->has(VanillaEffects::MINING_FATIGUE()));
		$elder->onUpdate(1);
		self::assertTrue($player->getEffects()->has(VanillaEffects::MINING_FATIGUE()));
		$fatigue = $player->getEffects()->get(VanillaEffects::MINING_FATIGUE());
		self::assertNotNull($fatigue);
		self::assertSame(2, $fatigue->getAmplifier()); // Level III

		// Elder guardian drops guaranteed wet sponge
		$drops = $elder->getDrops();
		$hasSponge = false;
		foreach($drops as $drop){
			if($drop->getTypeId() === VanillaBlocks::SPONGE()->asItem()->getTypeId()){
				$hasSponge = true;
				break;
			}
		}
		self::assertTrue($hasSponge);
	}
}
