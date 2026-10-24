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
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\entity\HungerManager;
use pocketmine\entity\object\AreaEffectCloud;
use pocketmine\entity\object\EndCrystal;
use pocketmine\entity\projectile\DragonFireball;
use pocketmine\entity\projectile\WitherSkull;
use pocketmine\event\entity\EntityDamageByChildEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\ProjectileHitEntityEvent;
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
use pocketmine\player\Player;
use pocketmine\world\World;
use Ramsey\Uuid\Uuid;
use ReflectionClass;
use ReflectionProperty;

class BossFightsTest extends TestCase{

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

	private function createPlayer(string $name = "Steve", ?Item $heldItem = null, ?World $world = null, ?Vector3 $pos = null) : Player{
		$player = $this->getMockBuilder(Player::class)
			->disableOriginalConstructor()
			->onlyMethods(["getName", "getUniqueId", "isSneaking", "isCreative", "isSpectator", "getInventory", "hasFiniteResources", "getViewers", "canInteract", "broadcastSound", "broadcastAnimation", "isConnected", "isOnline", "isAlive", "getArmorInventory", "close", "onDispose"])
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

	private function createEnderDragon(?World $world = null, ?Vector3 $pos = null) : EnderDragon{
		if($world === null){
			$world = $this->getDefaultWorld();
		}
		$location = new Location($pos?->x ?? 0.0, $pos?->y ?? 0.0, $pos?->z ?? 0.0, $world, 0.0, 0.0);
		$dragon = new EnderDragon($location);
		$this->createdEntities[] = $dragon;
		return $dragon;
	}

	private function createWither(?World $world = null, ?Vector3 $pos = null) : Wither{
		if($world === null){
			$world = $this->getDefaultWorld();
		}
		$location = new Location($pos?->x ?? 0.0, $pos?->y ?? 0.0, $pos?->z ?? 0.0, $world, 0.0, 0.0);
		$wither = new Wither($location);
		$this->createdEntities[] = $wither;
		return $wither;
	}

	// ---------------- ENDER DRAGON TESTS ---------------- //

	public function testEnderDragonInitializationAndKnockback() : void{
		$dragon = $this->createEnderDragon();
		self::assertSame(EntityIds::ENDER_DRAGON, EnderDragon::getNetworkTypeId());
		self::assertSame("Ender Dragon", $dragon->getName());
		self::assertSame(200, $dragon->getDefaultMaxHealth());
		self::assertSame(200, $dragon->getMaxHealth());
		self::assertSame(200.0, $dragon->getHealth());
		self::assertSame(EnderDragon::PHASE_CIRCLING, $dragon->getPhase());

		// 100% knockback resistance: motion remains unchanged
		$dragon->knockBack(1.0, 1.0, 0.5);
		self::assertEquals(0.0, $dragon->getMotion()->x);
		self::assertEquals(0.0, $dragon->getMotion()->y);
		self::assertEquals(0.0, $dragon->getMotion()->z);
	}

	public function testEnderDragonImmunitiesAndCombat() : void{
		$dragon = $this->createEnderDragon();

		// Fire, fall, and suffocation immunities
		foreach([
			EntityDamageEvent::CAUSE_FIRE,
			EntityDamageEvent::CAUSE_FIRE_TICK,
			EntityDamageEvent::CAUSE_LAVA,
			EntityDamageEvent::CAUSE_FALL,
			EntityDamageEvent::CAUSE_SUFFOCATION
		] as $cause){
			$ev = new EntityDamageEvent($dragon, $cause, 10.0);
			$dragon->attack($ev);
			self::assertTrue($ev->isCancelled(), "Cause $cause should be cancelled");
		}

		// Valid attack damages dragon
		$attackEv = new EntityDamageEvent($dragon, EntityDamageEvent::CAUSE_ENTITY_ATTACK, 25.0);
		$dragon->attack($attackEv);
		self::assertFalse($attackEv->isCancelled());
		self::assertSame(175.0, $dragon->getHealth());
	}

	public function testEnderDragonPerchingAndAttacks() : void{
		$dragon = $this->createEnderDragon();
		self::assertFalse($dragon->isPerched());

		$dragon->setPhase(EnderDragon::PHASE_PERCHING);
		self::assertTrue($dragon->isPerched());

		$dragon->setPhase(EnderDragon::PHASE_BREATH);
		self::assertTrue($dragon->isPerched());

		// Spray breath spawns AreaEffectCloud
		$cloud = $dragon->sprayBreath(new Vector3(10, 0, 10));
		self::assertInstanceOf(AreaEffectCloud::class, $cloud);
		self::assertTrue(count($cloud->getEffects()->all()) > 0);

		// Shoot fireball spawns DragonFireball
		$fireball = $dragon->shootFireball(new Vector3(10, 0, 10));
		self::assertInstanceOf(DragonFireball::class, $fireball);
	}

	public function testEnderDragonCrystalHealingAndDestruction() : void{
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);
		$world->method("getPlayers")->willReturn([]);
		$world->method("getDamageY")->willReturn(-64);

		$crystalLocation = new Location(10.0, 20.0, 10.0, $world, 0.0, 0.0);
		$crystal = new EndCrystal($crystalLocation);
		$this->createdEntities[] = $crystal;

		$dragon = $this->createEnderDragon(world: $world);
		$dragon->setHealth(150.0);

		$world->method("getEntity")->willReturnCallback(function(int $id) use ($crystal, $dragon) : ?Entity{
			if($id === $crystal->getId()) return $crystal;
			if($id === $dragon->getId()) return $dragon;
			return null;
		});

		// Link crystal
		$dragon->linkCrystal($crystal);
		self::assertNotNull($crystal->getBeamTarget());
		self::assertSame($crystal->getId(), $dragon->getLinkedCrystal()?->getId());

		// Tick regenerates 1.0 HP
		$dragon->onUpdate(1);
		self::assertSame(151.0, $dragon->getHealth());

		// Crystal destroyed -> Dragon takes 10 damage!
		(new ReflectionProperty(Entity::class, "closed"))->setValue($crystal, true);
		$dragon->onUpdate(2);
		// 151.0 - 10.0 = 141.0 HP
		self::assertSame(141.0, $dragon->getHealth());
		self::assertNull($dragon->getLinkedCrystal());
	}

	public function testEnderDragonDeathDrops() : void{
		$dragon = $this->createEnderDragon();
		$drops = $dragon->getDrops();
		self::assertCount(1, $drops);
		self::assertSame(VanillaBlocks::DRAGON_EGG()->asItem()->getTypeId(), $drops[0]->getTypeId());

		// First kill gives 12,000 XP
		self::assertSame(12000, $dragon->getXpDropAmount());

		// Subsequent kill gives 500 XP
		$dragon->setPreviouslyKilled(true);
		self::assertSame(500, $dragon->getXpDropAmount());
	}

	public function testEnderDragonNbtSerialization() : void{
		$dragon = $this->createEnderDragon();
		$dragon->setPhase(EnderDragon::PHASE_CHARGING);
		$dragon->setPreviouslyKilled(true);

		$nbt = $dragon->saveNBT();
		self::assertSame(EnderDragon::PHASE_CHARGING, $nbt->getInt(EnderDragon::TAG_DRAGON_PHASE));
		self::assertSame(1, $nbt->getByte(EnderDragon::TAG_PREVIOUSLY_KILLED));

		$loaded = new EnderDragon(new Location(0, 0, 0, $this->getDefaultWorld(), 0, 0), $nbt);
		$this->createdEntities[] = $loaded;
		self::assertSame(EnderDragon::PHASE_CHARGING, $loaded->getPhase());
		self::assertTrue($loaded->isPreviouslyKilled());
	}

	// ---------------- WITHER TESTS ---------------- //

	public function testWitherInitializationAndImmunities() : void{
		$wither = $this->createWither();
		self::assertSame(EntityIds::WITHER, Wither::getNetworkTypeId());
		self::assertSame("Wither", $wither->getName());
		self::assertSame(600, $wither->getDefaultMaxHealth());
		self::assertSame(600, $wither->getMaxHealth());
		self::assertSame(220, $wither->getInvulnerableTicks());

		// Fire, lava, drowning, suffocation immunities
		foreach([
			EntityDamageEvent::CAUSE_FIRE,
			EntityDamageEvent::CAUSE_FIRE_TICK,
			EntityDamageEvent::CAUSE_LAVA,
			EntityDamageEvent::CAUSE_DROWNING,
			EntityDamageEvent::CAUSE_SUFFOCATION
		] as $cause){
			$ev = new EntityDamageEvent($wither, $cause, 10.0);
			$wither->attack($ev);
			self::assertTrue($ev->isCancelled(), "Cause $cause should be cancelled");
		}
	}

	public function testWitherSpawnInvulnerabilityAndExplosion() : void{
		$wither = $this->createWither();
		self::assertSame(220, $wither->getInvulnerableTicks());

		// During spawn charging, incoming attacks are completely ignored
		$attackEv = new EntityDamageEvent($wither, EntityDamageEvent::CAUSE_ENTITY_ATTACK, 50.0);
		$wither->attack($attackEv);
		self::assertTrue($attackEv->isCancelled());

		// Progress charging
		$wither->onUpdate(20);
		self::assertSame(200, $wither->getInvulnerableTicks());

		// When invulnerable timer finishes, Wither explodes
		$wither->setInvulnerableTicks(1);
		$wither->onUpdate(21);
		self::assertSame(0, $wither->getInvulnerableTicks());
		self::assertFalse($wither->hasWitherArmor());
	}

	public function testWitherArmorAndProjectileImmunity() : void{
		$wither = $this->createWither();
		$wither->setInvulnerableTicks(0);
		$wither->setHealth(500.0);
		self::assertFalse($wither->hasWitherArmor());

		// Before armor, projectiles deal damage
		$projEv = new EntityDamageEvent($wither, EntityDamageEvent::CAUSE_PROJECTILE, 20.0);
		$wither->attack($projEv);
		self::assertFalse($projEv->isCancelled());
		self::assertSame(480.0, $wither->getHealth());

		// At <= 300 HP (50% max HP), enters Wither Armor phase
		$wither->setHealth(300.0);
		self::assertTrue($wither->hasWitherArmor());

		// Projectiles are now completely negated
		$projEv2 = new EntityDamageEvent($wither, EntityDamageEvent::CAUSE_PROJECTILE, 20.0);
		$wither->attack($projEv2);
		self::assertTrue($projEv2->isCancelled());
		self::assertSame(300.0, $wither->getHealth());

		// Melee attack still works
		(new ReflectionProperty(Living::class, "attackTime"))->setValue($wither, 0);
		$meleeEv = new EntityDamageEvent($wither, EntityDamageEvent::CAUSE_ENTITY_ATTACK, 20.0);
		$wither->attack($meleeEv);
		self::assertFalse($meleeEv->isCancelled());
		self::assertSame(280.0, $wither->getHealth());
	}

	public function testWitherSkullProjectileAndEffect() : void{
		$wither = $this->createWither();
		$skull = $wither->shootSkull(new Vector3(5, 0, 0), dangerous: true);
		self::assertInstanceOf(WitherSkull::class, $skull);
		self::assertTrue($skull->isDangerous());

		// Target entity hit inflicts damage and Wither II effect
		$victim = $this->createPlayer("Victim");
		$ev = new ProjectileHitEntityEvent($skull, new \pocketmine\math\RayTraceResult(new AxisAlignedBB(0, 0, 0, 1, 1, 1), 0, new Vector3(0, 0, 0)), $victim);

		// Invoke onHit via reflection
		$method = new \ReflectionMethod(WitherSkull::class, "onHit");
		$method->invoke($skull, $ev);

		self::assertTrue($victim->getEffects()->has(VanillaEffects::WITHER()));
		$witherEffect = $victim->getEffects()->get(VanillaEffects::WITHER());
		self::assertNotNull($witherEffect);
		self::assertSame(1, $witherEffect->getAmplifier()); // Wither II
	}

	public function testWitherDeathDrops() : void{
		$wither = $this->createWither();
		$drops = $wither->getDrops();
		self::assertCount(1, $drops);
		self::assertSame(VanillaItems::NETHER_STAR()->getTypeId(), $drops[0]->getTypeId());
		self::assertSame(50, $wither->getXpDropAmount());
	}

	public function testWitherNbtSerialization() : void{
		$wither = $this->createWither();
		$wither->setInvulnerableTicks(150);

		$nbt = $wither->saveNBT();
		self::assertSame(150, $nbt->getInt(Wither::TAG_INVULNERABLE_TICKS));

		$loaded = new Wither(new Location(0, 0, 0, $this->getDefaultWorld(), 0, 0), $nbt);
		$this->createdEntities[] = $loaded;
		self::assertSame(150, $loaded->getInvulnerableTicks());
	}
}
