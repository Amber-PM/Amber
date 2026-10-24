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
use pocketmine\entity\HungerManager;
use pocketmine\entity\projectile\WindCharge;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\ProjectileHitEntityEvent;
use pocketmine\event\HandlerListManager;
use pocketmine\inventory\ArmorInventory;
use pocketmine\inventory\PlayerInventory;
use pocketmine\inventory\PlayerOffHandInventory;
use pocketmine\item\Item;
use pocketmine\item\ItemIdentifier;
use pocketmine\item\ItemTypeIds;
use pocketmine\item\VanillaItems;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\RayTraceResult;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\player\Player;
use pocketmine\world\World;
use Ramsey\Uuid\Uuid;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

class ModernMobsTest extends TestCase{

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
			$this->defaultWorld->method("getDamageY")->willReturn(-64);
		}
		return $this->defaultWorld;
	}

	private function createPlayer(string $name = "Steve", ?Item $heldItem = null, ?World $world = null, ?Vector3 $pos = null, bool $isSneaking = false) : Player{
		$player = $this->getMockBuilder(Player::class)
			->disableOriginalConstructor()
			->onlyMethods(["getName", "getUniqueId", "isSneaking", "isCreative", "isSpectator", "getInventory", "hasFiniteResources", "getViewers", "canInteract", "broadcastSound", "broadcastAnimation", "isConnected", "isOnline", "isAlive", "getArmorInventory", "close", "onDispose", "setMotion"])
			->getMock();

		$player->method("getName")->willReturn($name);
		$player->method("getUniqueId")->willReturn(Uuid::uuid4());
		$player->method("isSneaking")->willReturn($isSneaking);
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
		(new ReflectionProperty(Human::class, "hungerManager"))->setValue($player, new HungerManager($player));
		$armorInv = new ArmorInventory($player);
		(new ReflectionProperty(Living::class, "armorInventory"))->setValue($player, $armorInv);
		$player->method("getArmorInventory")->willReturn($armorInv);
		(new ReflectionProperty(Entity::class, "networkProperties"))->setValue($player, new EntityMetadataCollection());

		if($world === null){
			$world = $this->getDefaultWorld();
		}
		$location = new Location($pos?->x ?? 0.0, $pos?->y ?? 0.0, $pos?->z ?? 0.0, $world, 0.0, 0.0);
		(new ReflectionProperty(Entity::class, "location"))->setValue($player, $location);
		(new ReflectionProperty(Entity::class, "motion"))->setValue($player, Vector3::zero());
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

	// ---------------- WARDEN TESTS ---------------- //

	public function testWardenInitializationAndKnockback() : void{
		$warden = new Warden(new Location(0, 0, 0, $this->getDefaultWorld(), 0, 0));
		$this->createdEntities[] = $warden;

		self::assertSame(EntityIds::WARDEN, Warden::getNetworkTypeId());
		self::assertSame("Warden", $warden->getName());
		self::assertSame(500, $warden->getDefaultMaxHealth());
		self::assertSame(500, $warden->getMaxHealth());
		self::assertSame(500.0, $warden->getHealth());

		// 100% knockback resistance
		$warden->knockBack(1.0, 1.0, 0.5);
		self::assertEquals(0.0, $warden->getMotion()->x);
		self::assertEquals(0.0, $warden->getMotion()->y);
		self::assertEquals(0.0, $warden->getMotion()->z);
	}

	public function testWardenAngerAndImmunities() : void{
		$warden = new Warden(new Location(0, 0, 0, $this->getDefaultWorld(), 0, 0));
		$this->createdEntities[] = $warden;

		// Immunities: fire, lava, drowning, suffocation
		foreach([
			EntityDamageEvent::CAUSE_FIRE,
			EntityDamageEvent::CAUSE_FIRE_TICK,
			EntityDamageEvent::CAUSE_LAVA,
			EntityDamageEvent::CAUSE_DROWNING,
			EntityDamageEvent::CAUSE_SUFFOCATION
		] as $cause){
			$ev = new EntityDamageEvent($warden, $cause, 10.0);
			$warden->attack($ev);
			self::assertTrue($ev->isCancelled(), "Cause $cause should be cancelled");
		}

		self::assertSame(0, $warden->getAnger());
		self::assertFalse($warden->isAngry());

		// Sustaining damage boosts anger
		$attackEv = new EntityDamageEvent($warden, EntityDamageEvent::CAUSE_ENTITY_ATTACK, 10.0);
		$warden->attack($attackEv);
		self::assertSame(20, $warden->getAnger());

		$warden->setAnger(80);
		self::assertTrue($warden->isAngry());
	}

	public function testWardenSonicBoom() : void{
		$warden = new Warden(new Location(0, 0, 0, $this->getDefaultWorld(), 0, 0));
		$this->createdEntities[] = $warden;

		$victim = $this->createPlayer("Victim", pos: new Vector3(10, 0, 0));
		(new ReflectionProperty(Entity::class, "health"))->setValue($victim, 20.0);

		$result = $warden->sonicBoom($victim);
		self::assertTrue($result);
		// 20.0 - 10.0 magic damage = 10.0 HP
		self::assertSame(10.0, $victim->getHealth());
		self::assertTrue($warden->isSonicBooming());
	}

	// ---------------- BREEZE TESTS ---------------- //

	public function testBreezeInitializationAndProjectileImmunity() : void{
		$breeze = new Breeze(new Location(0, 0, 0, $this->getDefaultWorld(), 0, 0));
		$this->createdEntities[] = $breeze;

		self::assertSame(EntityIds::BREEZE, Breeze::getNetworkTypeId());
		self::assertSame("Breeze", $breeze->getName());
		self::assertSame(30, $breeze->getDefaultMaxHealth());
		self::assertSame(30, $breeze->getMaxHealth());
		self::assertSame(30.0, $breeze->getHealth());

		// Fall immunity
		$fallEv = new EntityDamageEvent($breeze, EntityDamageEvent::CAUSE_FALL, 10.0);
		$breeze->attack($fallEv);
		self::assertTrue($fallEv->isCancelled());

		// Projectile deflection immunity
		$projEv = new EntityDamageEvent($breeze, EntityDamageEvent::CAUSE_PROJECTILE, 15.0);
		$breeze->attack($projEv);
		self::assertTrue($projEv->isCancelled());
		self::assertSame(30.0, $breeze->getHealth());

		// Melee attack works
		$meleeEv = new EntityDamageEvent($breeze, EntityDamageEvent::CAUSE_ENTITY_ATTACK, 10.0);
		$breeze->attack($meleeEv);
		self::assertFalse($meleeEv->isCancelled());
		self::assertSame(20.0, $breeze->getHealth());

		// Shoot wind charge
		$windCharge = $breeze->shootWindCharge(new Vector3(5, 0, 0));
		self::assertInstanceOf(WindCharge::class, $windCharge);
	}

	public function testWindChargeProjectileHit() : void{
		$windCharge = new WindCharge(new Location(0, 0, 0, $this->getDefaultWorld(), 0, 0));
		$this->createdEntities[] = $windCharge;

		$victim = $this->createPlayer("Victim");
		(new ReflectionProperty(Entity::class, "health"))->setValue($victim, 20.0);

		$ev = new ProjectileHitEntityEvent($windCharge, new RayTraceResult(new AxisAlignedBB(0, 0, 0, 1, 1, 1), 0, new Vector3(0, 0, 0)), $victim);
		$method = new ReflectionMethod(WindCharge::class, "onHit");
		$method->invoke($windCharge, $ev);

		self::assertSame(19.0, $victim->getHealth());
	}

	// ---------------- ALLAY TESTS ---------------- //

	public function testAllayItemHoldingAndDuplication() : void{
		$allay = new Allay(new Location(0, 0, 0, $this->getDefaultWorld(), 0, 0));
		$this->createdEntities[] = $allay;

		self::assertSame(EntityIds::ALLAY, Allay::getNetworkTypeId());
		self::assertSame("Allay", $allay->getName());
		self::assertSame(20, $allay->getDefaultMaxHealth());

		// Give item to Allay
		$diamond = VanillaItems::DIAMOND()->setCount(5);
		$player = $this->createPlayer("Owner", heldItem: $diamond);
		$interacted = $allay->interact($player, new Vector3(0, 0, 0));

		self::assertTrue($interacted);
		self::assertNotNull($allay->getHeldItem());
		self::assertSame(VanillaItems::DIAMOND()->getTypeId(), $allay->getHeldItem()?->getTypeId());
		self::assertSame(4, $player->getInventory()->getItemInHand()->getCount());

		// Friendly fire protection
		$friendlyAttack = new EntityDamageByEntityEvent($player, $allay, EntityDamageEvent::CAUSE_ENTITY_ATTACK, 5.0);
		$allay->attack($friendlyAttack);
		self::assertTrue($friendlyAttack->isCancelled());

		// Dancing and duplication with Amethyst Shard
		$allay->setDancing(true);
		self::assertTrue($allay->isDancing());

		$player2 = $this->createPlayer("Owner2", heldItem: VanillaItems::AMETHYST_SHARD());
		$dupInteracted = $allay->interact($player2, new Vector3(0, 0, 0));
		self::assertTrue($dupInteracted);
		self::assertTrue($player2->getInventory()->getItemInHand()->isNull());
	}

	// ---------------- CAMEL TESTS ---------------- //

	public function testCamelSaddlingAndSitting() : void{
		$camel = new Camel(new Location(0, 0, 0, $this->getDefaultWorld(), 0, 0));
		$this->createdEntities[] = $camel;

		self::assertSame(EntityIds::CAMEL, Camel::getNetworkTypeId());
		self::assertSame("Camel", $camel->getName());
		self::assertSame(32, $camel->getDefaultMaxHealth());
		self::assertFalse($camel->isSaddled());
		self::assertFalse($camel->isSitting());

		// Saddling
		$saddle = new Item(new ItemIdentifier(ItemTypeIds::FIRST_UNUSED_ITEM_ID), "Saddle");
		$player = $this->createPlayer(heldItem: $saddle);
		$interacted = $camel->interact($player, new Vector3(0, 0, 0));
		self::assertTrue($interacted);
		self::assertTrue($camel->isSaddled());

		// Sitting toggle
		$sneakingPlayer = $this->createPlayer(isSneaking: true);
		$camel->interact($sneakingPlayer, new Vector3(0, 0, 0));
		self::assertTrue($camel->isSitting());
	}

	// ---------------- SNIFFER TESTS ---------------- //

	public function testSnifferDigging() : void{
		$sniffer = new Sniffer(new Location(0, 0, 0, $this->getDefaultWorld(), 0, 0));
		$this->createdEntities[] = $sniffer;

		self::assertSame(EntityIds::SNIFFER, Sniffer::getNetworkTypeId());
		self::assertSame("Sniffer", $sniffer->getName());
		self::assertSame(14, $sniffer->getDefaultMaxHealth());

		$sniffer->setSniffing(true);
		self::assertTrue($sniffer->isSniffing());

		$sniffer->setDigging(true);
		self::assertTrue($sniffer->isDigging());

		$seed = $sniffer->dig();
		self::assertSame(VanillaItems::TORCHFLOWER_SEEDS()->getTypeId(), $seed->getTypeId());
	}

	// ---------------- ARMADILLO TESTS ---------------- //

	public function testArmadilloDefenseAndBrushing() : void{
		$armadillo = new Armadillo(new Location(0, 0, 0, $this->getDefaultWorld(), 0, 0));
		$this->createdEntities[] = $armadillo;

		self::assertSame(EntityIds::ARMADILLO, Armadillo::getNetworkTypeId());
		self::assertSame("Armadillo", $armadillo->getName());
		self::assertSame(12, $armadillo->getDefaultMaxHealth());
		self::assertFalse($armadillo->isRolledUp());

		// Damaging curls armadillo into defensive ball
		$ev = new EntityDamageEvent($armadillo, EntityDamageEvent::CAUSE_ENTITY_ATTACK, 4.0);
		$armadillo->attack($ev);
		self::assertTrue($armadillo->isRolledUp());
		self::assertSame(8.0, $armadillo->getHealth());

		// Next attack deals 50% reduced damage while rolled up
		(new ReflectionProperty(Living::class, "attackTime"))->setValue($armadillo, 0);
		$ev2 = new EntityDamageEvent($armadillo, EntityDamageEvent::CAUSE_ENTITY_ATTACK, 4.0);
		$armadillo->attack($ev2);
		// 4.0 * 0.5 = 2.0 damage -> 8.0 - 2.0 = 6.0 HP
		self::assertSame(6.0, $armadillo->getHealth());

		// Brushing interaction
		$brush = new Item(new ItemIdentifier(ItemTypeIds::FIRST_UNUSED_ITEM_ID), "Brush");
		$player = $this->createPlayer(heldItem: $brush);
		$brushed = $armadillo->interact($player, new Vector3(0, 0, 0));
		self::assertTrue($brushed);
	}

	// ---------------- BOGGED TESTS ---------------- //

	public function testBoggedShearingAndPoisonAttack() : void{
		$bogged = new Bogged(new Location(0, 0, 0, $this->getDefaultWorld(), 0, 0));
		$this->createdEntities[] = $bogged;

		self::assertSame(EntityIds::BOGGED, Bogged::getNetworkTypeId());
		self::assertSame("Bogged", $bogged->getName());
		self::assertSame(16, $bogged->getDefaultMaxHealth());
		self::assertFalse($bogged->isSheared());

		// Shearing interaction
		$player = $this->createPlayer(heldItem: VanillaItems::SHEARS());
		$interacted = $bogged->interact($player, new Vector3(0, 0, 0));
		self::assertTrue($interacted);
		self::assertTrue($bogged->isSheared());

		// Attack inflicts Poison
		$victim = $this->createPlayer("Victim");
		$bogged->attackEntity($victim);
		self::assertTrue($victim->getEffects()->has(VanillaEffects::POISON()));
	}

	// ---------------- NBT SERIALIZATION TESTS ---------------- //

	public function testModernMobsNbtSerialization() : void{
		$warden = new Warden(new Location(0, 0, 0, $this->getDefaultWorld(), 0, 0));
		$warden->setAnger(95);
		$nbt = $warden->saveNBT();
		self::assertSame(95, $nbt->getInt(Warden::TAG_ANGER));
		$loadedWarden = new Warden(new Location(0, 0, 0, $this->getDefaultWorld(), 0, 0), $nbt);
		$this->createdEntities[] = $loadedWarden;
		self::assertSame(95, $loadedWarden->getAnger());

		$camel = new Camel(new Location(0, 0, 0, $this->getDefaultWorld(), 0, 0));
		$camel->setSaddled(true);
		$camel->setSitting(true);
		$nbtCamel = $camel->saveNBT();
		self::assertSame(1, $nbtCamel->getByte(Camel::TAG_SADDLED));
		self::assertSame(1, $nbtCamel->getByte(Camel::TAG_SITTING));
		$loadedCamel = new Camel(new Location(0, 0, 0, $this->getDefaultWorld(), 0, 0), $nbtCamel);
		$this->createdEntities[] = $loadedCamel;
		self::assertTrue($loadedCamel->isSaddled());
		self::assertTrue($loadedCamel->isSitting());

		$bogged = new Bogged(new Location(0, 0, 0, $this->getDefaultWorld(), 0, 0));
		$bogged->setSheared(true);
		$nbtBogged = $bogged->saveNBT();
		self::assertSame(1, $nbtBogged->getByte(Bogged::TAG_SHEARED));
		$loadedBogged = new Bogged(new Location(0, 0, 0, $this->getDefaultWorld(), 0, 0), $nbtBogged);
		$this->createdEntities[] = $loadedBogged;
		self::assertTrue($loadedBogged->isSheared());
	}
}
