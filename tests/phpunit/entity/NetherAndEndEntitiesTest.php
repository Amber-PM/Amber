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
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityTeleportEvent;
use pocketmine\event\EventPriority;
use pocketmine\event\HandlerListManager;
use pocketmine\event\RegisteredListener;
use pocketmine\inventory\ArmorInventory;
use pocketmine\inventory\CallbackInventoryListener;
use pocketmine\inventory\PlayerInventory;
use pocketmine\inventory\PlayerOffHandInventory;
use pocketmine\item\Item;
use pocketmine\item\ItemTypeIds;
use pocketmine\item\VanillaItems;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\ByteMetadataProperty;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\entity\HungerManager;
use pocketmine\entity\projectile\Fireball;
use pocketmine\entity\projectile\SmallFireball;
use pocketmine\Server;
use pocketmine\player\Player;
use pocketmine\plugin\Plugin;
use pocketmine\timings\TimingsHandler;
use pocketmine\world\World;
use Ramsey\Uuid\Uuid;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

class NetherAndEndEntitiesTest extends TestCase{

	/** @var Entity[] */
	private array $createdEntities = [];
	private ?World $defaultWorld = null;
	private int $currentTick = 0;

	private function tickEntity(Entity $entity, int $tick, int $diff = 1) : void{
		$entity->lastUpdate = $tick;
		(new ReflectionMethod($entity, "entityBaseTick"))->invoke($entity, $diff);
	}

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
		$this->currentTick = 0;
		parent::tearDown();
	}

	private function getDefaultWorld() : World{
		if($this->defaultWorld === null){
			$this->defaultWorld = $this->createMock(World::class);
			$server = $this->createMock(Server::class);
			$server->method("getTick")->willReturnCallback(fn() => $this->currentTick);
			$this->defaultWorld->method("getServer")->willReturn($server);
			$this->defaultWorld->method("isLoaded")->willReturn(true);
			$this->defaultWorld->method("getPlayers")->willReturn([]);
			$this->defaultWorld->method("getDamageY")->willReturn(-64);
			$this->defaultWorld->method("getBlockAt")->willReturn(VanillaBlocks::AIR());
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
		(new ReflectionProperty(Entity::class, "size"))->setValue($player, new EntitySizeInfo(1.8, 0.6, 1.62));
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

	private function createGhast(?World $world = null, ?Vector3 $pos = null) : Ghast{
		if($world === null){
			$world = $this->getDefaultWorld();
		}
		$location = new Location($pos?->x ?? 0.0, $pos?->y ?? 0.0, $pos?->z ?? 0.0, $world, 0.0, 0.0);
		$ghast = new Ghast($location);
		$this->createdEntities[] = $ghast;
		return $ghast;
	}

	private function createBlaze(?World $world = null, ?Vector3 $pos = null) : Blaze{
		if($world === null){
			$world = $this->getDefaultWorld();
		}
		$location = new Location($pos?->x ?? 0.0, $pos?->y ?? 0.0, $pos?->z ?? 0.0, $world, 0.0, 0.0);
		$blaze = new Blaze($location);
		$this->createdEntities[] = $blaze;
		return $blaze;
	}

	private function createWitherSkeleton(?World $world = null, ?Vector3 $pos = null) : WitherSkeleton{
		if($world === null){
			$world = $this->getDefaultWorld();
		}
		$location = new Location($pos?->x ?? 0.0, $pos?->y ?? 0.0, $pos?->z ?? 0.0, $world, 0.0, 0.0);
		$skel = new WitherSkeleton($location);
		$this->createdEntities[] = $skel;
		return $skel;
	}

	private function createPiglin(?World $world = null, ?Vector3 $pos = null) : Piglin{
		if($world === null){
			$world = $this->getDefaultWorld();
		}
		$location = new Location($pos?->x ?? 0.0, $pos?->y ?? 0.0, $pos?->z ?? 0.0, $world, 0.0, 0.0);
		$piglin = new Piglin($location);
		$this->createdEntities[] = $piglin;
		return $piglin;
	}

	private function createZombifiedPiglin(?World $world = null, ?Vector3 $pos = null) : ZombifiedPiglin{
		if($world === null){
			$world = $this->getDefaultWorld();
		}
		$location = new Location($pos?->x ?? 0.0, $pos?->y ?? 0.0, $pos?->z ?? 0.0, $world, 0.0, 0.0);
		$zombie = new ZombifiedPiglin($location);
		$this->createdEntities[] = $zombie;
		return $zombie;
	}

	private function createStrider(?World $world = null, ?Vector3 $pos = null) : Strider{
		if($world === null){
			$world = $this->getDefaultWorld();
		}
		$location = new Location($pos?->x ?? 0.0, $pos?->y ?? 0.0, $pos?->z ?? 0.0, $world, 0.0, 0.0);
		$strider = new Strider($location);
		$this->createdEntities[] = $strider;
		return $strider;
	}

	private function createShulker(?World $world = null, ?Vector3 $pos = null) : Shulker{
		if($world === null){
			$world = $this->getDefaultWorld();
		}
		$location = new Location($pos?->x ?? 0.0, $pos?->y ?? 0.0, $pos?->z ?? 0.0, $world, 0.0, 0.0);
		$shulker = new Shulker($location);
		$this->createdEntities[] = $shulker;
		return $shulker;
	}

	private function syncNetworkProperties(Entity $entity) : void{
		(new ReflectionMethod($entity, "syncNetworkData"))->invoke($entity, $entity->getNetworkProperties());
	}

	// ---------------- GHAST TESTS ---------------- //

	public function testGhastInitializationAndImmunities() : void{
		$ghast = $this->createGhast();
		self::assertSame(EntityIds::GHAST, Ghast::getNetworkTypeId());
		self::assertSame("Ghast", $ghast->getName());
		self::assertSame(10, $ghast->getDefaultMaxHealth());
		self::assertSame(10, $ghast->getMaxHealth());
		self::assertSame(10.0, $ghast->getHealth());
		self::assertFalse($ghast->isChargingFireball());

		// Test fire immunity
		$fireEv = new EntityDamageEvent($ghast, EntityDamageEvent::CAUSE_FIRE, 5.0);
		$ghast->attack($fireEv);
		self::assertTrue($fireEv->isCancelled());

		// Test fall immunity
		$fallEv = new EntityDamageEvent($ghast, EntityDamageEvent::CAUSE_FALL, 20.0);
		$ghast->attack($fallEv);
		self::assertTrue($fallEv->isCancelled());
	}

	public function testGhastChargingFireballAndNbt() : void{
		$ghast = $this->createGhast();
		$ghast->setChargingFireball(true);
		self::assertTrue($ghast->isChargingFireball());

		$this->syncNetworkProperties($ghast);

		$nbt = $ghast->saveNBT();
		self::assertSame(1, $nbt->getByte(Ghast::TAG_CHARGING));

		$loaded = new Ghast(new Location(0, 0, 0, $this->getDefaultWorld(), 0, 0), $nbt);
		$this->createdEntities[] = $loaded;
		self::assertTrue($loaded->isChargingFireball());
	}

	public function testRangedMobsLaunchFireballsAtTheirTarget() : void{
		$world = $this->getMockBuilder(World::class)->disableOriginalConstructor()
			->onlyMethods(["getServer", "getBlockAt", "getViewersForPosition", "getDamageY", "getNearbyEntities", "isLoaded", "addSound"])
			->getMock();
		$world->method("getServer")->willReturn($this->createMock(Server::class));
		$world->method("isLoaded")->willReturn(true);
		$world->method("getDamageY")->willReturn(-64);
		$world->method("getBlockAt")->willReturn(VanillaBlocks::AIR());
		$player = $this->createPlayer(world: $world, pos: new Vector3(10, 0, 0));
		$world->addEntity($player);
		$world->method("getNearbyEntities")->willReturn([$player]);
		$ghast = $this->createGhast($world);
		$blaze = $this->createBlaze($world);
		$getShots = static fn() => array_values(array_filter($world->getEntities(), static fn(Entity $entity) => $entity instanceof SmallFireball));
		$this->tickEntity($ghast, 1);
		$this->tickEntity($blaze, 1);
		self::assertTrue($ghast->isChargingFireball());
		$shots = $getShots();
		self::assertCount(1, $shots);
		$this->createdEntities[] = $shots[0];
		self::assertSame(SmallFireball::class, $shots[0]::class);
		self::assertFalse($shots[0]->canSaveWithChunk());
		self::assertSame($blaze->getId(), $shots[0]->getOwningEntityId());
		self::assertGreaterThan(0, $shots[0]->getMotion()->x);
		$this->tickEntity($blaze, 2);
		$this->tickEntity($ghast, 21, 20);
		$shots = $getShots();
		self::assertCount(2, $shots);
		$this->createdEntities[] = $shots[1];
		self::assertInstanceOf(Fireball::class, $shots[1]);
		self::assertSame($ghast->getId(), $shots[1]->getOwningEntityId());
		self::assertGreaterThan(0, $shots[1]->getMotion()->x);
		self::assertFalse($ghast->isChargingFireball());
	}

	// ---------------- BLAZE TESTS ---------------- //

	public function testBlazeInitializationAndWaterDamage() : void{
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);
		$world->method("getPlayers")->willReturn([]);
		$world->method("getDamageY")->willReturn(-64);
		$world->method("getBlockAt")->willReturnCallback(function(int $x, int $y, int $z) use ($world) : Block{
			$water = VanillaBlocks::WATER();
			$water->position($world, $x, $y, $z);
			return $water;
		});

		$blaze = $this->createBlaze(world: $world);
		self::assertSame(EntityIds::BLAZE, Blaze::getNetworkTypeId());
		self::assertSame("Blaze", $blaze->getName());
		self::assertSame(20, $blaze->getDefaultMaxHealth());
		self::assertSame(20, $blaze->getMaxHealth());
		self::assertSame(20.0, $blaze->getHealth());

		// Fire immunity
		$fireEv = new EntityDamageEvent($blaze, EntityDamageEvent::CAUSE_LAVA, 10.0);
		$blaze->attack($fireEv);
		self::assertTrue($fireEv->isCancelled());

		// Water hurts Blaze
		$blaze->onUpdate(1);
		self::assertSame(19.0, $blaze->getHealth());
	}

	// ---------------- WITHER SKELETON TESTS ---------------- //

	public function testWitherSkeletonInitializationAndWitherAttack() : void{
		$world = $this->getDefaultWorld();
		$target = $this->createPlayer(pos: new Vector3(1, 0, 0));
		$world->method("getNearbyEntities")->willReturn([$target]);
		$skel = $this->createWitherSkeleton();
		self::assertSame(EntityIds::WITHER_SKELETON, WitherSkeleton::getNetworkTypeId());
		self::assertSame("Wither Skeleton", $skel->getName());
		self::assertSame(20, $skel->getDefaultMaxHealth());
		self::assertSame(20, $skel->getMaxHealth());
		self::assertSame(20.0, $skel->getHealth());

		// Attack target inflicts Wither
		self::assertFalse($target->getEffects()->has(VanillaEffects::WITHER()));

		$this->tickEntity($skel, 1);
		self::assertTrue($target->getEffects()->has(VanillaEffects::WITHER()));
		$wither = $target->getEffects()->get(VanillaEffects::WITHER());
		self::assertNotNull($wither);
		self::assertSame(200, $wither->getDuration());
	}

	// ---------------- PIGLIN & ZOMBIFIED PIGLIN TESTS ---------------- //

	public function testPiglinGoldPacificationAndBartering() : void{
		$piglin = $this->createPiglin();
		self::assertSame(EntityIds::PIGLIN, Piglin::getNetworkTypeId());
		self::assertSame("Piglin", $piglin->getName());
		self::assertSame(16, $piglin->getDefaultMaxHealth());
		self::assertSame(16, $piglin->getMaxHealth());
		self::assertSame(16.0, $piglin->getHealth());

		// Player without gold -> not pacified
		$playerPlain = $this->createPlayer();
		self::assertFalse($piglin->isPacifiedBy($playerPlain));

		// Golden boots also pacify the Piglin.
		$playerGold = $this->createPlayer();
		$playerGold->getArmorInventory()->setBoots(VanillaItems::GOLDEN_BOOTS());
		self::assertTrue($piglin->isPacifiedBy($playerGold));

		// Bartering with Gold Ingot
		$playerBarter = $this->createPlayer(heldItem: VanillaItems::GOLD_INGOT()->setCount(2));
		self::assertFalse($piglin->isAdmiring());

		$world = $this->getDefaultWorld();
		$inventory = $playerBarter->getInventory();
		$inventory->getListeners()->add(CallbackInventoryListener::onAnyChange(
			static function() use ($playerBarter, $piglin) : void{
				self::assertFalse($playerBarter->interactEntity($piglin, Vector3::zero()));
			}
		));
		unset($world->updateEntities[$piglin->getId()]);
		$this->currentTick = 1000;
		$world->expects(self::once())->method("dropItem")->willReturn(null);
		$world->expects(self::never())->method("addParticle");

		self::assertTrue($playerBarter->interactEntity($piglin, Vector3::zero()));
		self::assertSame(1, $inventory->getItemInHand()->getCount());
		self::assertSame($piglin, $world->updateEntities[$piglin->getId()]);

		$this->tickEntity($piglin, 1001, 1001);
		self::assertSame(119, $piglin->saveNBT()->getInt(Piglin::TAG_ADMIRING_TICKS));
		$this->tickEntity($piglin, 1119, 118);
		self::assertTrue($piglin->isAdmiring());
		$this->tickEntity($piglin, 1120);
		self::assertFalse($piglin->isAdmiring());
	}

	public function testPiglinReevaluatesTargetWhenItPutsOnGoldBoots() : void{
		$world = $this->getDefaultWorld();
		$nearest = $this->createPlayer(pos: new Vector3(1, 0, 0));
		$further = $this->createPlayer(pos: new Vector3(2, 0, 0));
		$world->method("getNearbyEntities")->willReturn([$nearest, $further]);
		$world->method("getEntity")->willReturnMap([[$nearest->getId(), $nearest], [$further->getId(), $further]]);
		$piglin = $this->createPiglin();

		$this->tickEntity($piglin, 1);
		self::assertSame($nearest->getId(), $piglin->getTargetEntityId());
		$nearest->getArmorInventory()->setBoots(VanillaItems::GOLDEN_BOOTS());
		$this->tickEntity($piglin, 2);
		self::assertSame($further->getId(), $piglin->getTargetEntityId());
	}

	public function testZombifiedPiglinGroupAnger() : void{
		$world = $this->getMockBuilder(World::class)->disableOriginalConstructor()
			->onlyMethods(["getServer", "isLoaded", "getPlayers", "addEntity", "getBlockAt", "getDamageY", "getEntity"])
			->getMock();
		$server = $this->createMock(Server::class);
		$world->method("getServer")->willReturn($server);
		$server->method("getTick")->willReturn(1000);
		$world->method("isLoaded")->willReturn(true);
		$world->method("getPlayers")->willReturn([]);
		$world->method("getBlockAt")->willReturn(VanillaBlocks::AIR());

		$z1 = $this->createZombifiedPiglin(world: $world, pos: new Vector3(0, 60, 0));
		$z2 = $this->createZombifiedPiglin(world: $world, pos: new Vector3(19.9, 60, 0));
		$outside = $this->createZombifiedPiglin(world: $world, pos: new Vector3(20.1, 60, 0));
		(new ReflectionProperty(World::class, "entitiesByChunk"))->setValue($world, [
			World::chunkHash(0, 0) => [$z1->getId() => $z1],
			World::chunkHash(1, 0) => [$z2->getId() => $z2, $outside->getId() => $outside],
		]);
		unset($world->updateEntities[$z2->getId()]);

		$player = $this->createPlayer(world: $world, pos: new Vector3(0, 60, 0));
		$world->method("getEntity")->with($player->getId())->willReturn($player);
		$z1->attack($this->createDamageByEntityEvent($player, $z1, 5.0));

		self::assertTrue($z1->isAngry());
		self::assertTrue($z2->isAngry());
		self::assertFalse($outside->isAngry());
		self::assertSame($z2, $world->updateEntities[$z2->getId()]);
		self::assertSame($player->getId(), $z1->getTargetEntityId());
		self::assertSame($player->getId(), $z2->getTargetEntityId());
		$this->tickEntity($z2, 1001, 1001);
		self::assertSame(499, $z2->getAngerTicks());
		$this->tickEntity($z2, 1499, 498);
		self::assertTrue($z2->isAngry());
		$this->tickEntity($z2, 1500);
		self::assertFalse($z2->isAngry());
		self::assertNull($z2->getTargetEntityId());
	}

	// ---------------- STRIDER TESTS ---------------- //

	public function testStriderSaddlingAndShivering() : void{
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);
		$world->method("getPlayers")->willReturn([]);
		$world->method("getDamageY")->willReturn(-64);
		$world->method("getBlockAt")->willReturn(VanillaBlocks::AIR());

		$strider = $this->createStrider(world: $world);
		self::assertSame(EntityIds::STRIDER, Strider::getNetworkTypeId());
		self::assertSame("Strider", $strider->getName());
		self::assertSame(20, $strider->getDefaultMaxHealth());
		self::assertSame(20, $strider->getMaxHealth());
		self::assertSame(20.0, $strider->getHealth());
		self::assertFalse($strider->isSaddled());

		// Saddle interaction
		$player = $this->createPlayer(heldItem: VanillaItems::STICK()->setCustomName("saddle"), world: $world);
		self::assertFalse($player->interactEntity($strider, Vector3::zero()));
		$player->getInventory()->setItemInHand(VanillaItems::SADDLE());
		$interacted = $player->interactEntity($strider, Vector3::zero());

		self::assertTrue($interacted);
		self::assertTrue($strider->isSaddled());

		// Shivering out of lava
		$strider->onUpdate(1);
		self::assertTrue($strider->isShivering());

		// Drops include saddle
		$drops = $strider->getDrops();
		$hasSaddle = false;
		foreach($drops as $drop){
			if($drop->getTypeId() === ItemTypeIds::SADDLE){
				$hasSaddle = true;
				break;
			}
		}
		self::assertTrue($hasSaddle);
	}

	// ---------------- SHULKER TESTS ---------------- //

	public function testShulkerDefenseAndPeekState() : void{
		$shulker = $this->createShulker();
		self::assertSame(EntityIds::SHULKER, Shulker::getNetworkTypeId());
		self::assertSame("Shulker", $shulker->getName());
		self::assertSame(30, $shulker->getDefaultMaxHealth());
		self::assertSame(30, $shulker->getMaxHealth());
		self::assertSame(30.0, $shulker->getHealth());
		self::assertTrue($shulker->isClosedShell());

		// Closed shell reduces incoming damage by 80% (0.2x multiplier)
		$ev = new EntityDamageEvent($shulker, EntityDamageEvent::CAUSE_ENTITY_ATTACK, 10.0);
		$shulker->attack($ev);
		// 10.0 * 0.2 = 2.0 damage
		self::assertSame(28.0, $shulker->getHealth());

		// Open shell takes full damage
		$shulker->setPeek(100);
		self::assertFalse($shulker->isClosedShell());
		(new ReflectionProperty(Living::class, "attackTime"))->setValue($shulker, 0);
		$ev2 = new EntityDamageEvent($shulker, EntityDamageEvent::CAUSE_ENTITY_ATTACK, 10.0);
		$shulker->attack($ev2);
		self::assertSame(18.0, $shulker->getHealth());
	}

	public function testShulkerWallAttachmentSurvivesGravityUntilSupportIsRemoved() : void{
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);
		$world->method("getDamageY")->willReturn(-64);
		$supported = true;
		$world->method("getBlockAt")->willReturnCallback(static function(int $x, int $y, int $z) use (&$supported) : Block{
			return $supported && $x === 1 && $y === 10 && $z === 0 ? VanillaBlocks::STONE() : VanillaBlocks::AIR();
		});
		$location = new Location(0.5, 10, 0.5, $world, 0, 0);
		$nbt = CompoundTag::create()->setFloat("HealF", 12.0)->setByte(Shulker::TAG_ATTACH_FACE, Facing::EAST);
		$shulker = new Shulker($location, $nbt);
		$this->createdEntities[] = $shulker;
		self::assertSame(12.0, $shulker->getHealth());
		$piglin = new Piglin($location, $nbt);
		$this->createdEntities[] = $piglin;
		self::assertSame(12.0, $piglin->getHealth());

		$shulker->onUpdate(1);
		self::assertFalse($shulker->hasGravity());
		self::assertSame(10.0, $shulker->getLocation()->y);
		$supported = false;
		$shulker->onNearbyBlockChange();
		$shulker->onUpdate(2);
		self::assertTrue($shulker->hasGravity());
		self::assertLessThan(10.0, $shulker->getLocation()->y);
	}

	public function testShulkerDestinationChecksCollisionAndSupportBeforeChangingFace() : void{
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);
		$world->method("isInWorld")->willReturn(true);
		$world->method("getBlockAt")->willReturnCallback(static fn(int $x, int $y, int $z) : Block =>
			$x === 6 && $y === 10 && $z === 0 ? VanillaBlocks::STONE() : VanillaBlocks::AIR()
		);
		$colliding = true;
		$world->method("getBlockCollisionBoxes")->willReturnCallback(static function(AxisAlignedBB $bb) use (&$colliding) : array{
			return $colliding && $bb->minX === 5.0 ? [new AxisAlignedBB(5, 10, 0, 5.2, 11, 1)] : [];
		});
		$shulker = $this->createShulker($world, new Vector3(0.5, 10, 0.5));
		$tryAttachAt = new ReflectionMethod(Shulker::class, "tryAttachAt");
		$destination = new Vector3(5.5, 10, 0.5);
		self::assertFalse($tryAttachAt->invoke($shulker, $destination));
		$colliding = false;
		self::assertFalse($tryAttachAt->invoke($shulker, new Vector3(4.5, 10, 0.5)));

		$cancel = true;
		HandlerListManager::global()->getListFor(EntityTeleportEvent::class)->register(new RegisteredListener(
			static function(EntityTeleportEvent $event) use (&$cancel) : void{
				if($cancel){
					$event->cancel();
				}
			},
			EventPriority::NORMAL,
			$this->createMock(Plugin::class),
			false,
			new TimingsHandler("Shulker teleport cancellation")
		));
		self::assertFalse($tryAttachAt->invoke($shulker, $destination));
		self::assertSame(Facing::DOWN, $shulker->getAttachFace());
		$cancel = false;
		self::assertTrue($tryAttachAt->invoke($shulker, $destination));
		self::assertSame(Facing::EAST, $shulker->getAttachFace());
	}

	public function testShulkerNbtSerialization() : void{
		$shulker = $this->createShulker();
		$shulker->setPeek(50);
		$shulker->setAttachFace(Facing::UP);

		$nbt = $shulker->saveNBT();
		self::assertSame(50, $nbt->getByte(Shulker::TAG_PEEK));
		self::assertSame(Facing::UP, $nbt->getByte(Shulker::TAG_ATTACH_FACE));

		$loaded = new Shulker(new Location(0, 0, 0, $this->getDefaultWorld(), 0, 0), $nbt);
		$this->createdEntities[] = $loaded;
		self::assertSame(50, $loaded->getPeek());
		self::assertSame(Facing::UP, $loaded->getAttachFace());
	}
}
