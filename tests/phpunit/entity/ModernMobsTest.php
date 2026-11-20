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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\AttributeMap;
use pocketmine\entity\effect\EffectManager;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\entity\HungerManager;
use pocketmine\entity\projectile\WindCharge;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\EventPriority;
use pocketmine\event\HandlerListManager;
use pocketmine\event\RegisteredListener;
use pocketmine\inventory\ArmorInventory;
use pocketmine\inventory\PlayerInventory;
use pocketmine\inventory\PlayerOffHandInventory;
use pocketmine\item\Brush;
use pocketmine\item\Item;
use pocketmine\item\StringToItemParser;
use pocketmine\item\VanillaItems;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\player\Player;
use pocketmine\plugin\Plugin;
use pocketmine\Server;
use pocketmine\timings\TimingsHandler;
use pocketmine\world\sound\ExplodeSound;
use pocketmine\world\sound\PopSound;
use pocketmine\world\World;
use pocketmine\world\WorldManager;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use function max;
use function strtolower;

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
			$worldManager = $this->createMock(WorldManager::class);
			$worldManager->method("findEntity")->willReturnCallback(function(int $id) : ?Entity{
				foreach($this->createdEntities as $entity){
					if($entity->getId() === $id){
						return $entity;
					}
				}
				return null;
			});
			$server = $this->createMock(Server::class);
			$server->method("getWorldManager")->willReturn($worldManager);
			$server->method("getPlayerByUUID")->willReturnCallback(function(UuidInterface $uuid) : ?Player{
				foreach($this->createdEntities as $entity){
					if($entity instanceof Player && $entity->getUniqueId()->equals($uuid)){
						return $entity;
					}
				}
				return null;
			});
			$this->defaultWorld = $this->createMock(World::class);
			$this->defaultWorld->method("getServer")->willReturn($server);
			$this->defaultWorld->method("isLoaded")->willReturn(true);
			$this->defaultWorld->method("getPlayers")->willReturn([]);
			$this->defaultWorld->method("getDamageY")->willReturn(-64);
		}
		return $this->defaultWorld;
	}

	private function createPlayer(string $name = "Steve", ?Item $heldItem = null, ?World $world = null, ?Vector3 $pos = null, bool $isSneaking = false, bool $finiteResources = true) : Player{
		$player = $this->getMockBuilder(Player::class)
			->disableOriginalConstructor()
			->onlyMethods(["getName", "getUniqueId", "isSneaking", "isCreative", "isSpectator", "getInventory", "hasFiniteResources", "getViewers", "canInteract", "broadcastSound", "broadcastAnimation", "isConnected", "isOnline", "isAlive", "getArmorInventory", "close", "onDispose", "setMotion"])
			->getMock();

		$player->method("getName")->willReturn($name);
		$player->method("getUniqueId")->willReturn(Uuid::uuid4());
		$player->method("isSneaking")->willReturn($isSneaking);
		$player->method("isCreative")->willReturn(false);
		$player->method("isSpectator")->willReturn(false);
		$player->method("hasFiniteResources")->willReturn($finiteResources);
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
		$warden->lastUpdate = 100;
		(new ReflectionMethod(Warden::class, "entityBaseTick"))->invoke($warden, 100);
		self::assertFalse($warden->isSonicBooming());
	}

	public function testCancelledSonicBoomDoesNotKnockBack() : void{
		$warden = new Warden(new Location(0, 0, 0, $this->getDefaultWorld(), 0, 0));
		$this->createdEntities[] = $warden;
		$victim = $this->createPlayer(pos: new Vector3(10, 0, 0));
		(new ReflectionProperty(Entity::class, "health"))->setValue($victim, 20.0);
		(new ReflectionProperty(Entity::class, "noDamageTicks"))->setValue($victim, 1);
		$victim->expects(self::never())->method("setMotion");

		self::assertTrue($warden->sonicBoom($victim));
		self::assertSame(20.0, $victim->getHealth());
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

	/** @return iterable<string, array{bool}> */
	public static function windChargeDamageCases() : iterable{
		yield "accepted" => [false];
		yield "cancelled" => [true];
	}

	#[DataProvider("windChargeDamageCases")]
	public function testWindChargeProjectileHit(bool $cancelDamage) : void{
		$world = $this->getDefaultWorld();
		$world->method("getBlockAt")->willReturnCallback(static fn() => VanillaBlocks::AIR());
		$world->expects(self::atLeastOnce())->method("getBlock")->willReturnCallback(static fn() => VanillaBlocks::AIR());
		$world->expects(self::once())->method("addSound")->with(self::anything(), self::isInstanceOf(ExplodeSound::class));
		$windCharge = new WindCharge(new Location(0, 0, 0, $world, 0, 0));
		$this->createdEntities[] = $windCharge;
		$windCharge->setMotion(new Vector3(2, 0, 0));

		$victim = $this->createPlayer("Victim", pos: new Vector3(2, 0, 0));
		(new ReflectionProperty(Entity::class, "health"))->setValue($victim, 20.0);
		$bystander = $this->createPlayer("Bystander", pos: new Vector3(3, 0, 0));
		(new ReflectionProperty(Entity::class, "health"))->setValue($bystander, 20.0);
		$bystander->expects(self::atLeastOnce())->method("setMotion");
		$world->method("getCollidingEntities")->willReturn([$victim]);
		$world->method("getNearbyEntities")->willReturnCallback(
			static fn(AxisAlignedBB $bb, ?Entity $source) => $source === $windCharge ? [$victim, $bystander] : []
		);

		$damageEvents = 0;
		$listener = new RegisteredListener(static function(EntityDamageEvent $event) use ($victim, $cancelDamage, &$damageEvents) : void{
			if($event->getEntity() === $victim && $event->getCause() === EntityDamageEvent::CAUSE_PROJECTILE){
				++$damageEvents;
				if($cancelDamage){
					$event->cancel();
				}
			}
		}, EventPriority::NORMAL, $this->createMock(Plugin::class), false, new TimingsHandler("wind charge test"));
		HandlerListManager::global()->getListFor(EntityDamageEvent::class)->register($listener);

		$largestKnockback = 0.0;
		$victim->expects($cancelDamage ? self::never() : self::atLeastOnce())->method("setMotion")->willReturnCallback(
			static function(Vector3 $motion) use (&$largestKnockback) : bool{
				$largestKnockback = max($largestKnockback, $motion->x);
				return true;
			}
		);
		(new ReflectionMethod(WindCharge::class, "move"))->invoke($windCharge, 2.0, 0.0, 0.0);

		self::assertSame(1, $damageEvents);
		self::assertSame($cancelDamage, $windCharge->isHitCancelled());
		self::assertSame($cancelDamage ? 20.0 : 19.0, $victim->getHealth());
		self::assertLessThan(20.0, $bystander->getHealth());
		self::assertTrue($windCharge->isFlaggedForDespawn());
		if(!$cancelDamage){
			self::assertGreaterThan(1.0, $largestKnockback);
		}
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
		$interacted = $allay->onInteract($player, Vector3::zero());

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

		$player = $this->createPlayer(heldItem: VanillaItems::STICK()->setCustomName("Spare Saddle"));
		self::assertFalse($camel->interact($player, Vector3::zero()));
		self::assertFalse($camel->isSaddled());

		// Sitting toggle
		$sneakingPlayer = $this->createPlayer(isSneaking: true);
		self::assertTrue($camel->onInteract($sneakingPlayer, Vector3::zero()));
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

	public function testArmadilloDefense() : void{
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

		$armadillo->lastUpdate = 200;
		(new ReflectionMethod(Armadillo::class, "entityBaseTick"))->invoke($armadillo, 200);
		self::assertFalse($armadillo->isRolledUp());

		$fall = new EntityDamageEvent($armadillo, EntityDamageEvent::CAUSE_FALL, 1.0);
		$armadillo->attack($fall);
		self::assertFalse($fall->isCancelled());
		self::assertFalse($armadillo->isRolledUp());
	}

	public function testArmadilloBrushing() : void{
		$world = $this->getDefaultWorld();
		$world->expects(self::once())->method("dropItem")->with(self::anything(), self::equalTo(VanillaItems::SCUTE()));
		$world->expects(self::once())->method("addSound")->with(self::anything(), self::isInstanceOf(PopSound::class));
		$armadillo = new Armadillo(new Location(0, 0, 0, $world, 0, 0));
		$this->createdEntities[] = $armadillo;

		$player = $this->createPlayer(heldItem: VanillaItems::SHEARS()->setCustomName("Brush"));
		(new ReflectionProperty(Entity::class, "server"))->setValue($player, $world->getServer());
		$session = $this->createMock(NetworkSession::class);
		$session->expects(self::once())->method("onItemCooldownChanged")->with(self::isInstanceOf(Brush::class), 10);
		(new ReflectionProperty(Player::class, "networkSession"))->setValue($player, $session);
		self::assertFalse($armadillo->onInteract($player, Vector3::zero()));

		$brush = StringToItemParser::getInstance()->parse("brush");
		self::assertInstanceOf(Brush::class, $brush);
		$player->getInventory()->setItemInHand($brush);
		self::assertTrue($armadillo->onInteract($player, Vector3::zero()));
		$held = $player->getInventory()->getItemInHand();
		self::assertInstanceOf(Brush::class, $held);
		self::assertSame(16, $held->getDamage());
		self::assertTrue($player->hasItemCooldown($held));
		self::assertFalse($armadillo->onInteract($player, Vector3::zero()));
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
		$interacted = $bogged->onInteract($player, Vector3::zero());
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
		$this->createdEntities[] = $warden;
		$warden->setAnger(95);
		$nbt = $warden->saveNBT();
		self::assertSame(95, $nbt->getInt(Warden::TAG_ANGER));
		$loadedWarden = EntityFactory::getInstance()->createFromData($this->getDefaultWorld(), $nbt);
		self::assertInstanceOf(Warden::class, $loadedWarden);
		$this->createdEntities[] = $loadedWarden;
		self::assertSame(95, $loadedWarden->getAnger());

		$camel = new Camel(new Location(0, 0, 0, $this->getDefaultWorld(), 0, 0));
		$this->createdEntities[] = $camel;
		$camel->setSaddled(true);
		$camel->setSitting(true);
		$nbtCamel = $camel->saveNBT();
		self::assertSame(1, $nbtCamel->getByte(Camel::TAG_SADDLED));
		self::assertSame(1, $nbtCamel->getByte(Camel::TAG_SITTING));
		$loadedCamel = EntityFactory::getInstance()->createFromData($this->getDefaultWorld(), $nbtCamel);
		self::assertInstanceOf(Camel::class, $loadedCamel);
		$this->createdEntities[] = $loadedCamel;
		self::assertTrue($loadedCamel->isSaddled());
		self::assertTrue($loadedCamel->isSitting());

		$bogged = new Bogged(new Location(0, 0, 0, $this->getDefaultWorld(), 0, 0));
		$this->createdEntities[] = $bogged;
		$bogged->setSheared(true);
		$nbtBogged = $bogged->saveNBT();
		self::assertSame(1, $nbtBogged->getByte(Bogged::TAG_SHEARED));
		$loadedBogged = EntityFactory::getInstance()->createFromData($this->getDefaultWorld(), $nbtBogged);
		self::assertInstanceOf(Bogged::class, $loadedBogged);
		$this->createdEntities[] = $loadedBogged;
		self::assertTrue($loadedBogged->isSheared());
	}

	public function testAllayRestoresOwnerAndHeldShard() : void{
		$world = $this->getDefaultWorld();
		$allay = new Allay(new Location(0, 0, 0, $world, 0, 0));
		$this->createdEntities[] = $allay;
		$owner = $this->createPlayer(heldItem: VanillaItems::AMETHYST_SHARD(), finiteResources: false);
		self::assertTrue($allay->interact($owner, Vector3::zero()));
		self::assertSame(1, $owner->getInventory()->getItemInHand()->getCount());

		$nbt = $allay->saveNBT();
		self::assertSame($owner->getUniqueId()->toString(), $nbt->getString(Allay::TAG_OWNER_UUID));
		(new ReflectionProperty(Entity::class, "id"))->setValue($owner, Entity::nextRuntimeId());

		$loaded = EntityFactory::getInstance()->createFromData($world, $nbt);
		self::assertInstanceOf(Allay::class, $loaded);
		$this->createdEntities[] = $loaded;
		self::assertSame($owner->getId(), $loaded->getOwnerId());
		self::assertTrue($loaded->getHeldItem()?->equalsExact(VanillaItems::AMETHYST_SHARD()));

		$hit = new EntityDamageByEntityEvent($owner, $loaded, EntityDamageEvent::CAUSE_ENTITY_ATTACK, 5);
		$loaded->attack($hit);
		self::assertTrue($hit->isCancelled());

		$loaded->setDancing();
		self::assertTrue($loaded->interact($owner, Vector3::zero()));
		self::assertSame(1, $owner->getInventory()->getItemInHand()->getCount());
	}

	/** @return iterable<string, array{string, class-string<Entity>}> */
	public static function entityAliases() : iterable{
		yield "WindCharge" => ["WindCharge", WindCharge::class];
		yield "minecraft:wind_charge_projectile" => ["minecraft:wind_charge_projectile", WindCharge::class];
		yield "minecraft:breeze_wind_charge_projectile" => ["minecraft:breeze_wind_charge_projectile", WindCharge::class];
		foreach([
			"Warden" => Warden::class,
			"Breeze" => Breeze::class,
			"Allay" => Allay::class,
			"Camel" => Camel::class,
			"Sniffer" => Sniffer::class,
			"Armadillo" => Armadillo::class,
			"Bogged" => Bogged::class
		] as $legacy => $class){
			yield $legacy => [$legacy, $class];
			$alias = "minecraft:" . strtolower($legacy);
			yield $alias => [$alias, $class];
		}
	}

	/** @param class-string<Entity> $expectedClass */
	#[DataProvider("entityAliases")]
	public function testEntityFactoryAliases(string $alias, string $expectedClass) : void{
		$world = $this->getDefaultWorld();
		$source = new $expectedClass(new Location(3, 4, 5, $world, 0, 0));
		$this->createdEntities[] = $source;

		$nbt = $source->saveNBT()->setString(EntityFactory::TAG_IDENTIFIER, $alias);
		$loaded = EntityFactory::getInstance()->createFromData($world, $nbt);
		self::assertInstanceOf($expectedClass, $loaded);
		$this->createdEntities[] = $loaded;
		self::assertTrue($loaded->getPosition()->equals(new Vector3(3, 4, 5)));
	}
}
