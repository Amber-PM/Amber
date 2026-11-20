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
use pocketmine\entity\effect\EffectInstance;
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
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\player\Player;
use pocketmine\world\World;
use Ramsey\Uuid\Uuid;
use ReflectionClass;
use ReflectionProperty;

class AquaticWildlifeTest extends TestCase{

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

	private function createPlayer(string $name = "Steve", ?string $uuid = null, ?Item $heldItem = null, bool $underwater = false, ?World $world = null, ?Vector3 $pos = null) : Player{
		$uuidObj = $uuid !== null ? Uuid::fromString($uuid) : Uuid::uuid4();
		$player = $this->getMockBuilder(Player::class)
			->disableOriginalConstructor()
			->onlyMethods(["getName", "getUniqueId", "isSneaking", "isCreative", "isSpectator", "getInventory", "hasFiniteResources", "getViewers", "canInteract", "broadcastSound", "broadcastAnimation", "isConnected", "isOnline", "isUnderwater"])
			->getMock();

		$player->method("getName")->willReturn($name);
		$player->method("getUniqueId")->willReturn($uuidObj);
		$player->method("isSneaking")->willReturn(false);
		$player->method("isCreative")->willReturn(false);
		$player->method("isSpectator")->willReturn(false);
		$player->method("hasFiniteResources")->willReturn(true);
		$player->method("getViewers")->willReturn([]);
		$player->method("canInteract")->willReturn(true);
		$player->method("isConnected")->willReturn(true);
		$player->method("isOnline")->willReturn(true);
		$player->method("isUnderwater")->willReturn($underwater);

		(new ReflectionProperty(Entity::class, "closed"))->setValue($player, false);
		(new ReflectionProperty(Entity::class, "id"))->setValue($player, 100);
		(new ReflectionProperty(Player::class, "logger"))->setValue($player, $this->createMock(Logger::class));
		(new ReflectionProperty(Living::class, "effectManager"))->setValue($player, new EffectManager($player));
		(new ReflectionProperty(Entity::class, "attributeMap"))->setValue($player, new AttributeMap());
		(new ReflectionClass(Player::class))->getMethod("addAttributes")->invoke($player);
		(new ReflectionProperty(Living::class, "armorInventory"))->setValue($player, new ArmorInventory($player));
		(new ReflectionProperty(Human::class, "hungerManager"))->setValue($player, new HungerManager($player));
		(new ReflectionProperty(Entity::class, "networkProperties"))->setValue($player, new \pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection());

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

	private function createDolphin(?World $world = null, ?Vector3 $pos = null) : Dolphin{
		if($world === null){
			$world = $this->getDefaultWorld();
		}
		$location = new Location($pos?->x ?? 0.0, $pos?->y ?? 0.0, $pos?->z ?? 0.0, $world, 0.0, 0.0);
		$dolphin = new Dolphin($location);
		$this->createdEntities[] = $dolphin;
		return $dolphin;
	}

	private function createGlowSquid(?World $world = null, ?Vector3 $pos = null) : GlowSquid{
		if($world === null){
			$world = $this->getDefaultWorld();
		}
		$location = new Location($pos?->x ?? 0.0, $pos?->y ?? 0.0, $pos?->z ?? 0.0, $world, 0.0, 0.0);
		$squid = new GlowSquid($location);
		$this->createdEntities[] = $squid;
		return $squid;
	}

	private function createAxolotl(?World $world = null, ?Vector3 $pos = null) : Axolotl{
		if($world === null){
			$world = $this->getDefaultWorld();
		}
		$location = new Location($pos?->x ?? 0.0, $pos?->y ?? 0.0, $pos?->z ?? 0.0, $world, 0.0, 0.0);
		$axolotl = new Axolotl($location);
		$this->createdEntities[] = $axolotl;
		return $axolotl;
	}

	// ---------------- DOLPHIN TESTS ---------------- //

	public function testDolphinInitializationAndStats() : void{
		$dolphin = $this->createDolphin();
		self::assertSame(EntityIds::DOLPHIN, Dolphin::getNetworkTypeId());
		self::assertSame("Dolphin", $dolphin->getName());
		self::assertSame(10, $dolphin->getDefaultMaxHealth());
		self::assertSame(Dolphin::MAX_MOISTURE, $dolphin->getMoisture());
		self::assertSame(Dolphin::MAX_UNDERWATER_AIR, $dolphin->getUnderwaterAir());
		self::assertFalse($dolphin->isAngry());
	}

	public function testDolphinFeeding() : void{
		$dolphin = $this->createDolphin();
		$dolphin->setHealth(6.0);

		$player = $this->createPlayer("Feeder", null, VanillaItems::RAW_FISH());
		$interacted = $dolphin->interact($player, new Vector3(0, 0, 0));
		self::assertTrue($interacted);
		self::assertSame(8.0, $dolphin->getHealth());

		// Full health feeding triggers happy state
		$dolphin->setHealth(10.0);
		$player2 = $this->createPlayer("Feeder2", null, VanillaItems::RAW_SALMON());
		$interacted = $dolphin->interact($player2, new Vector3(0, 0, 0));
		self::assertTrue($interacted);
	}

	public function testDolphinPodDefense() : void{
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);
		$world->method("getPlayers")->willReturn([]);

		$dolphin1 = $this->createDolphin($world);
		$dolphin2 = $this->createDolphin($world);
		$world->method("getNearbyEntities")->willReturn([$dolphin2]);

		$player = $this->createPlayer("Attacker", null, null, false, $world);
		$damageEv = $this->createDamageByEntityEvent($player, $dolphin1, 2.0);
		$dolphin1->attack($damageEv);

		self::assertTrue($dolphin1->isAngry());
		self::assertSame($player->getId(), $dolphin1->getTargetEntityId());
		self::assertTrue($dolphin2->isAngry());
		self::assertSame($player->getId(), $dolphin2->getTargetEntityId());
	}

	public function testDolphinNbtSerialization() : void{
		$dolphin = $this->createDolphin();
		$dolphin->setMoisture(800);
		$dolphin->setUnderwaterAir(2400);

		$nbt = $dolphin->saveNBT();
		$loaded = new Dolphin($dolphin->getLocation(), $nbt);
		$this->createdEntities[] = $loaded;

		self::assertSame(800, $loaded->getMoisture());
		self::assertSame(2400, $loaded->getUnderwaterAir());
	}

	// ---------------- GLOW SQUID TESTS ---------------- //

	public function testGlowSquidInitializationAndDrops() : void{
		$squid = $this->createGlowSquid();
		self::assertSame(EntityIds::GLOW_SQUID, GlowSquid::getNetworkTypeId());
		self::assertSame("Glow Squid", $squid->getName());

		$drops = $squid->getDrops();
		self::assertNotEmpty($drops);
		self::assertSame(VanillaItems::GLOW_INK_SAC()->getTypeId(), $drops[0]->getTypeId());
		self::assertGreaterThanOrEqual(1, $drops[0]->getCount());
		self::assertLessThanOrEqual(3, $drops[0]->getCount());
	}

	// ---------------- AXOLOTL TESTS ---------------- //

	public function testAxolotlInitializationAndVariants() : void{
		$axolotl = $this->createAxolotl();
		self::assertSame(EntityIds::AXOLOTL, Axolotl::getNetworkTypeId());
		self::assertSame("Axolotl", $axolotl->getName());
		self::assertSame(14, $axolotl->getDefaultMaxHealth());
		self::assertSame(Axolotl::VARIANT_LUCY, $axolotl->getVariant());
		self::assertFalse($axolotl->isPlayingDead());
		self::assertFalse($axolotl->isBaby());

		$axolotl->setVariant(Axolotl::VARIANT_BLUE);
		self::assertSame(Axolotl::VARIANT_BLUE, $axolotl->getVariant());
	}

	public function testAxolotlPlayingDead() : void{
		$axolotl = $this->createAxolotl();
		$axolotl->setPlayingDead(true, 200);

		self::assertTrue($axolotl->isPlayingDead());
		self::assertTrue($axolotl->getEffects()->has(VanillaEffects::REGENERATION()));

		$axolotl->setPlayingDead(false);
		self::assertFalse($axolotl->isPlayingDead());
	}

	public function testAxolotlCombatAssistance() : void{
		$axolotl = $this->createAxolotl();
		$player = $this->createPlayer("Helper");

		$player->getEffects()->add(new EffectInstance(VanillaEffects::MINING_FATIGUE(), 600));
		self::assertTrue($player->getEffects()->has(VanillaEffects::MINING_FATIGUE()));

		$axolotl->assistPlayerCombat($player);

		self::assertFalse($player->getEffects()->has(VanillaEffects::MINING_FATIGUE()));
		self::assertTrue($player->getEffects()->has(VanillaEffects::REGENERATION()));
	}

	public function testAxolotlBreeding() : void{
		$axolotl = $this->createAxolotl();
		$player = $this->createPlayer("Breeder", null, VanillaItems::CLOWNFISH());

		$interacted = $axolotl->interact($player, new Vector3(0, 0, 0));
		self::assertTrue($interacted);
		self::assertTrue($axolotl->isInLove());
	}

	public function testAxolotlNbtSerialization() : void{
		$axolotl = $this->createAxolotl();
		$axolotl->setVariant(Axolotl::VARIANT_CYAN);
		$axolotl->setPlayingDead(true);
		$axolotl->setAge(-15000);

		$nbt = $axolotl->saveNBT();
		$loaded = new Axolotl($axolotl->getLocation(), $nbt);
		$this->createdEntities[] = $loaded;

		self::assertSame(Axolotl::VARIANT_CYAN, $loaded->getVariant());
		self::assertTrue($loaded->isPlayingDead());
		self::assertSame(-15000, $loaded->getAge());
		self::assertTrue($loaded->isBaby());
	}
}
