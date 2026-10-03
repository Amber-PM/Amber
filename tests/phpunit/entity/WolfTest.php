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

use Logger;
use PHPUnit\Framework\TestCase;
use pocketmine\block\utils\DyeColor;
use pocketmine\data\bedrock\DyeColorIdMap;
use pocketmine\entity\effect\EffectManager;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityTameEvent;
use pocketmine\event\entity\PetCollarColorChangeEvent;
use pocketmine\event\entity\PetSitChangeEvent;
use pocketmine\event\Event;
use pocketmine\event\EventPriority;
use pocketmine\event\HandlerListManager;
use pocketmine\event\RegisteredListener;
use pocketmine\inventory\ArmorInventory;
use pocketmine\inventory\PlayerInventory;
use pocketmine\inventory\PlayerOffHandInventory;
use pocketmine\item\Dye;
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
use pocketmine\plugin\Plugin;
use pocketmine\timings\TimingsHandler;
use pocketmine\world\World;
use Ramsey\Uuid\Uuid;
use ReflectionClass;
use ReflectionProperty;

class TestCreeper extends Living{
	public static function getNetworkTypeId() : string{ return EntityIds::CREEPER; }
	protected function getInitialSizeInfo() : EntitySizeInfo{ return new EntitySizeInfo(1.7, 0.6); }
	public function getName() : string{ return "Creeper"; }
}

class TestTargetEntity extends Living{
	public static function getNetworkTypeId() : string{ return "minecraft:test_target"; }
	protected function getInitialSizeInfo() : EntitySizeInfo{ return new EntitySizeInfo(1.0, 1.0); }
	public function getName() : string{ return "Test Target"; }

	public ?EntityDamageEvent $lastDamageEvent = null;

	public function attack(EntityDamageEvent $source) : void{
		$this->lastDamageEvent = $source;
	}
}

final class WolfTest extends TestCase{

	/** @var Entity[] */
	private array $createdEntities = [];

	protected function tearDown() : void{
		HandlerListManager::global()->unregisterAll();
		foreach($this->createdEntities as $entity){
			(new ReflectionProperty(Entity::class, "closed"))->setValue($entity, true);
		}
		$this->createdEntities = [];
		parent::tearDown();
	}

	private function createWolf(?World $world = null, ?Vector3 $pos = null) : Wolf{
		$wolf = (new ReflectionClass(Wolf::class))->newInstanceWithoutConstructor();
		(new ReflectionProperty(Entity::class, "closed"))->setValue($wolf, true);
		(new ReflectionProperty(Entity::class, "id"))->setValue($wolf, 20);
		(new ReflectionProperty(Entity::class, "size"))->setValue($wolf, new EntitySizeInfo(0.85, 0.6));
		(new ReflectionProperty(Entity::class, "scale"))->setValue($wolf, 1.0);
		(new ReflectionProperty(Entity::class, "networkProperties"))->setValue($wolf, new EntityMetadataCollection());
		(new ReflectionProperty(Entity::class, "attributeMap"))->setValue($wolf, new AttributeMap());
		(new ReflectionClass(Wolf::class))->getMethod("addAttributes")->invoke($wolf);
		(new ReflectionProperty(Living::class, "effectManager"))->setValue($wolf, new EffectManager($wolf));
		(new ReflectionProperty(Living::class, "armorInventory"))->setValue($wolf, new ArmorInventory($wolf));
		if($world === null){
			$world = $this->createMock(World::class);
			$world->method("isLoaded")->willReturn(true);
			$world->method("getPlayers")->willReturn([]);
		}
		$location = new Location($pos?->x ?? 0.0, $pos?->y ?? 0.0, $pos?->z ?? 0.0, $world, 0.0, 0.0);
		(new ReflectionProperty(Entity::class, "location"))->setValue($wolf, $location);
		(new ReflectionProperty(Entity::class, "boundingBox"))->setValue($wolf, new AxisAlignedBB(
			$location->x - 0.3,
			$location->y,
			$location->z - 0.3,
			$location->x + 0.3,
			$location->y + 0.85,
			$location->z + 0.3
		));
		(new ReflectionProperty(Entity::class, "motion"))->setValue($wolf, Vector3::zero());
		(new ReflectionProperty(Entity::class, "closed"))->setValue($wolf, false);
		$this->createdEntities[] = $wolf;
		return $wolf;
	}

	private function createPlayer(string $name = "Steve", ?string $uuid = null, ?Item $heldItem = null, bool $finiteResources = true, ?World $world = null, ?Vector3 $pos = null) : Player{
		$uuidObj = $uuid !== null ? Uuid::fromString($uuid) : Uuid::uuid4();
		$player = $this->getMockBuilder(Player::class)
			->disableOriginalConstructor()
			->onlyMethods(["getName", "getUniqueId", "isSneaking", "isCreative", "isSpectator", "getInventory", "hasFiniteResources", "getViewers", "canInteract", "broadcastSound", "broadcastAnimation"])
			->getMock();
		(new ReflectionProperty(Entity::class, "closed"))->setValue($player, false);
		(new ReflectionProperty(Entity::class, "id"))->setValue($player, 100);
		(new ReflectionProperty(Player::class, "logger"))->setValue($player, $this->createMock(Logger::class));
		(new ReflectionProperty(Living::class, "effectManager"))->setValue($player, new EffectManager($player));
		(new ReflectionProperty(Entity::class, "attributeMap"))->setValue($player, new AttributeMap());
		(new ReflectionClass(Player::class))->getMethod("addAttributes")->invoke($player);
		(new ReflectionProperty(Living::class, "armorInventory"))->setValue($player, new ArmorInventory($player));
		(new ReflectionProperty(Entity::class, "networkProperties"))->setValue($player, new EntityMetadataCollection());
		if($world === null){
			$world = $this->createMock(World::class);
			$world->method("isLoaded")->willReturn(true);
			$world->method("getPlayers")->willReturn([]);
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
		(new ReflectionProperty(Entity::class, "motion"))->setValue($player, Vector3::zero());
		$inventory = new PlayerInventory($player);
		if($heldItem !== null){
			$inventory->setItemInHand($heldItem);
		}
		(new ReflectionProperty(Human::class, "inventory"))->setValue($player, $inventory);
		(new ReflectionProperty(Human::class, "offHandInventory"))->setValue($player, new PlayerOffHandInventory($player));
		$player->method("getName")->willReturn($name);
		$player->method("getUniqueId")->willReturn($uuidObj);
		$player->method("isSneaking")->willReturn(false);
		$player->method("isCreative")->willReturn(!$finiteResources);
		$player->method("isSpectator")->willReturn(false);
		$player->method("getInventory")->willReturn($inventory);
		$player->method("hasFiniteResources")->willReturn($finiteResources);
		$player->method("getViewers")->willReturn([]);
		$player->method("canInteract")->willReturn(true);
		$this->createdEntities[] = $player;
		return $player;
	}

	private function createCreeper(?World $world = null, ?Vector3 $pos = null) : TestCreeper{
		$creeper = (new ReflectionClass(TestCreeper::class))->newInstanceWithoutConstructor();
		(new ReflectionProperty(Entity::class, "closed"))->setValue($creeper, false);
		(new ReflectionProperty(Entity::class, "id"))->setValue($creeper, 50);
		(new ReflectionProperty(Entity::class, "size"))->setValue($creeper, new EntitySizeInfo(1.7, 0.6));
		(new ReflectionProperty(Entity::class, "scale"))->setValue($creeper, 1.0);
		(new ReflectionProperty(Entity::class, "networkProperties"))->setValue($creeper, new EntityMetadataCollection());
		(new ReflectionProperty(Entity::class, "attributeMap"))->setValue($creeper, new AttributeMap());
		(new ReflectionClass(Living::class))->getMethod("addAttributes")->invoke($creeper);
		(new ReflectionProperty(Living::class, "effectManager"))->setValue($creeper, new EffectManager($creeper));
		(new ReflectionProperty(Living::class, "armorInventory"))->setValue($creeper, new ArmorInventory($creeper));
		if($world === null){
			$world = $this->createMock(World::class);
			$world->method("isLoaded")->willReturn(true);
		}
		$location = new Location($pos?->x ?? 0.0, $pos?->y ?? 0.0, $pos?->z ?? 0.0, $world, 0.0, 0.0);
		(new ReflectionProperty(Entity::class, "location"))->setValue($creeper, $location);
		(new ReflectionProperty(Entity::class, "boundingBox"))->setValue($creeper, new AxisAlignedBB(
			$location->x - 0.3,
			$location->y,
			$location->z - 0.3,
			$location->x + 0.3,
			$location->y + 1.7,
			$location->z + 0.3
		));
		(new ReflectionProperty(Entity::class, "motion"))->setValue($creeper, Vector3::zero());
		$this->createdEntities[] = $creeper;
		return $creeper;
	}

	private function createTargetEntity(?World $world = null, ?Vector3 $pos = null) : TestTargetEntity{
		$target = (new ReflectionClass(TestTargetEntity::class))->newInstanceWithoutConstructor();
		(new ReflectionProperty(Entity::class, "closed"))->setValue($target, false);
		(new ReflectionProperty(Entity::class, "id"))->setValue($target, 60);
		(new ReflectionProperty(Entity::class, "size"))->setValue($target, new EntitySizeInfo(1.0, 1.0));
		(new ReflectionProperty(Entity::class, "scale"))->setValue($target, 1.0);
		(new ReflectionProperty(Entity::class, "networkProperties"))->setValue($target, new EntityMetadataCollection());
		(new ReflectionProperty(Entity::class, "attributeMap"))->setValue($target, new AttributeMap());
		(new ReflectionClass(Living::class))->getMethod("addAttributes")->invoke($target);
		(new ReflectionProperty(Living::class, "effectManager"))->setValue($target, new EffectManager($target));
		(new ReflectionProperty(Living::class, "armorInventory"))->setValue($target, new ArmorInventory($target));
		if($world === null){
			$world = $this->createMock(World::class);
			$world->method("isLoaded")->willReturn(true);
		}
		$location = new Location($pos?->x ?? 0.0, $pos?->y ?? 0.0, $pos?->z ?? 0.0, $world, 0.0, 0.0);
		(new ReflectionProperty(Entity::class, "location"))->setValue($target, $location);
		(new ReflectionProperty(Entity::class, "boundingBox"))->setValue($target, new AxisAlignedBB(
			$location->x - 0.5,
			$location->y,
			$location->z - 0.5,
			$location->x + 0.5,
			$location->y + 1.0,
			$location->z + 0.5
		));
		(new ReflectionProperty(Entity::class, "motion"))->setValue($target, Vector3::zero());
		$this->createdEntities[] = $target;
		return $target;
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
		(new ReflectionProperty(EntityDamageEvent::class, "originals"))->setValue($ev, []);
		(new ReflectionProperty(EntityDamageByEntityEvent::class, "knockBack"))->setValue($ev, Living::DEFAULT_KNOCKBACK_FORCE);
		(new ReflectionProperty(EntityDamageByEntityEvent::class, "verticalKnockBackLimit"))->setValue($ev, Living::DEFAULT_KNOCKBACK_VERTICAL_LIMIT);
		return $ev;
	}

	private function getGenericFlag(EntityMetadataCollection $flags, int $flagId) : bool{
		$propertyId = $flagId >= 64 ? EntityMetadataProperties::FLAGS2 : EntityMetadataProperties::FLAGS;
		$realFlagId = $flagId % 64;
		$prop = $flags->getAll()[$propertyId] ?? null;
		if($prop instanceof LongMetadataProperty){
			return (($prop->getValue() >> $realFlagId) & 1) !== 0;
		}
		return false;
	}

	private function registerCancellingListener(string $eventClass) : void{
		$listener = new RegisteredListener(
			function(Event $ev) : void{
				if(method_exists($ev, "cancel")){
					$ev->cancel();
				}
			},
			EventPriority::NORMAL,
			$this->createMock(Plugin::class),
			false,
			new TimingsHandler("cancel test")
		);
		HandlerListManager::global()->getListFor($eventClass)->register($listener);
	}

	public function testWildWolfDefaults() : void{
		$wolf = $this->createWolf();

		self::assertSame(EntityIds::WOLF, Wolf::getNetworkTypeId());
		self::assertSame("Wolf", $wolf->getName());
		self::assertSame(8, $wolf->getMaxHealth());
		self::assertEqualsWithDelta(8.0, $wolf->getHealth(), 0.001);
		self::assertFalse($wolf->isTamed());
		self::assertFalse($wolf->isAngry());
		self::assertFalse($wolf->isSitting());
		self::assertNull($wolf->getOwnerUUID());
		self::assertNull($wolf->getOwnerName());
		self::assertNull($wolf->getOwner());
		self::assertSame(DyeColor::RED, $wolf->getCollarColor());

		// EntityFactory registration check
		self::assertTrue(EntityFactory::getInstance()->isRegistered(Wolf::class));
		self::assertSame("Wolf", EntityFactory::getInstance()->getSaveId(Wolf::class));

		// Metadata collection check
		$properties = new EntityMetadataCollection();
		(new ReflectionClass(Wolf::class))->getMethod("syncNetworkData")->invoke($wolf, $properties);
		self::assertFalse($this->getGenericFlag($properties, EntityMetadataFlags::TAMED));
		self::assertFalse($this->getGenericFlag($properties, EntityMetadataFlags::ANGRY));
		self::assertFalse($this->getGenericFlag($properties, EntityMetadataFlags::SITTING));
	}

	public function testTamingBoostsMaxHealthAndSetsOwner() : void{
		$wolf = $this->createWolf();
		$player = $this->createPlayer("Alex");

		self::assertTrue($wolf->tame($player));

		self::assertTrue($wolf->isTamed());
		self::assertTrue($wolf->isSitting());
		self::assertFalse($wolf->isAngry());
		self::assertSame($player->getUniqueId()->toString(), $wolf->getOwnerUUID());
		self::assertSame("Alex", $wolf->getOwnerName());
		self::assertSame(20, $wolf->getMaxHealth());
		self::assertEqualsWithDelta(20.0, $wolf->getHealth(), 0.001);

		// Network flags
		$flags = (new ReflectionProperty(Entity::class, "networkProperties"))->getValue($wolf);
		self::assertTrue($this->getGenericFlag($flags, EntityMetadataFlags::TAMED));
		self::assertTrue($this->getGenericFlag($flags, EntityMetadataFlags::SITTING));
		self::assertFalse($this->getGenericFlag($flags, EntityMetadataFlags::ANGRY));
	}

	public function testTameEventCancellation() : void{
		$wolf = $this->createWolf();
		$player = $this->createPlayer("Mani");

		$this->registerCancellingListener(EntityTameEvent::class);

		self::assertFalse($wolf->tame($player));
		self::assertFalse($wolf->isTamed());
		self::assertSame(8, $wolf->getMaxHealth());
		self::assertEqualsWithDelta(8.0, $wolf->getHealth(), 0.001);
		self::assertNull($wolf->getOwnerUUID());
	}

	public function testTailAngleAndHealthRatio() : void{
		$wolf = $this->createWolf();

		// Wild full health: 8.0 / 8.0 = 1.0 -> 0.523 + 1.0 * 0.785 = 1.308
		self::assertEqualsWithDelta(1.0, $wolf->getHealthRatio(), 0.001);
		self::assertEqualsWithDelta(1.308, $wolf->getTailAngle(), 0.001);

		// Wild half health: 4.0 / 8.0 = 0.5 -> 0.523 + 0.5 * 0.785 = 0.9155
		$wolf->setHealth(4.0);
		self::assertEqualsWithDelta(0.5, $wolf->getHealthRatio(), 0.001);
		self::assertEqualsWithDelta(0.9155, $wolf->getTailAngle(), 0.001);

		// Tamed full health: 20.0 / 20.0 = 1.0
		$player = $this->createPlayer();
		$wolf->tame($player);
		self::assertEqualsWithDelta(1.0, $wolf->getHealthRatio(), 0.001);
		self::assertEqualsWithDelta(1.308, $wolf->getTailAngle(), 0.001);

		// Tamed quarter health: 5.0 / 20.0 = 0.25 -> 0.523 + 0.25 * 0.785 = 0.71925
		$wolf->setHealth(5.0);
		self::assertEqualsWithDelta(0.25, $wolf->getHealthRatio(), 0.001);
		self::assertEqualsWithDelta(0.71925, $wolf->getTailAngle(), 0.001);
	}

	public function testWildWolfBecomesAngryOnEntityAttack() : void{
		$wolf = $this->createWolf();
		$player = $this->createPlayer("Attacker");

		$damageEv = $this->createDamageByEntityEvent($player, $wolf, 2.0);
		$wolf->attack($damageEv);

		self::assertTrue($wolf->isAngry());
		$flags = (new ReflectionProperty(Entity::class, "networkProperties"))->getValue($wolf);
		self::assertTrue($this->getGenericFlag($flags, EntityMetadataFlags::ANGRY));
		self::assertSame($player, $wolf->getTargetEntity());
	}

	public function testWildWolfNonEntityDamageDoesNotTriggerAggression() : void{
		$wolf = $this->createWolf();

		$fallEv = new EntityDamageEvent($wolf, EntityDamageEvent::CAUSE_FALL, 2.0);
		$wolf->attack($fallEv);

		self::assertFalse($wolf->isAngry());
		self::assertNull($wolf->getTargetEntity());
	}

	public function testAlertNearbyWildWolvesOnAttack() : void{
		$wolf1 = $this->createWolf(null, new Vector3(0, 0, 0));
		$wolf2 = $this->createWolf(null, new Vector3(5, 0, 0));
		$tamedWolf = $this->createWolf(null, new Vector3(8, 0, 0));
		$owner = $this->createPlayer("Owner");
		$tamedWolf->tame($owner);

		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);
		$world->method("getNearbyEntities")->willReturn([$wolf2, $tamedWolf]);

		(new ReflectionProperty(Entity::class, "location"))->setValue($wolf1, new Location(0.0, 0.0, 0.0, $world, 0.0, 0.0));

		$attacker = $this->createPlayer("Hunter");
		$damageEv = $this->createDamageByEntityEvent($attacker, $wolf1, 1.0);
		$wolf1->attack($damageEv);

		self::assertTrue($wolf1->isAngry());
		self::assertTrue($wolf2->isAngry());
		self::assertSame($attacker, $wolf2->getTargetEntity());
		self::assertFalse($tamedWolf->isAngry());
	}

	public function testCollarDyeingInteraction() : void{
		$wolf = $this->createWolf();
		$player = $this->createPlayer("Owner");
		$wolf->tame($player);
		self::assertSame(DyeColor::RED, $wolf->getCollarColor());

		$dye = VanillaItems::DYE()->setColor(DyeColor::BLUE);
		$player->getInventory()->setItemInHand($dye);

		$interactResult = $wolf->onInteract($player, Vector3::zero());
		self::assertTrue($interactResult);
		self::assertSame(DyeColor::BLUE, $wolf->getCollarColor());
		self::assertSame(0, $player->getInventory()->getItemInHand()->getCount());
	}

	public function testCollarDyeingEventCancellation() : void{
		$wolf = $this->createWolf();
		$player = $this->createPlayer("Owner");
		$wolf->tame($player);
		self::assertSame(DyeColor::RED, $wolf->getCollarColor());

		$this->registerCancellingListener(PetCollarColorChangeEvent::class);

		$dye = VanillaItems::DYE()->setColor(DyeColor::GREEN);
		$player->getInventory()->setItemInHand($dye);

		$interactResult = $wolf->onInteract($player, Vector3::zero());
		self::assertFalse($interactResult);
		self::assertSame(DyeColor::RED, $wolf->getCollarColor());
		self::assertSame(1, $player->getInventory()->getItemInHand()->getCount());
	}

	public function testCollarDyeingNonOwnerRejected() : void{
		$wolf = $this->createWolf();
		$owner = $this->createPlayer("Owner");
		$stranger = $this->createPlayer("Stranger");
		$wolf->tame($owner);

		$dye = VanillaItems::DYE()->setColor(DyeColor::PURPLE);
		$stranger->getInventory()->setItemInHand($dye);

		$interactResult = $wolf->onInteract($stranger, Vector3::zero());
		self::assertFalse($interactResult);
		self::assertSame(DyeColor::RED, $wolf->getCollarColor());
		self::assertSame(1, $stranger->getInventory()->getItemInHand()->getCount());
	}

	public function testSitStandToggleInteraction() : void{
		$wolf = $this->createWolf();
		$player = $this->createPlayer("Owner");
		$wolf->tame($player);
		self::assertTrue($wolf->isSitting());

		// 1. Standing up
		$player->getInventory()->setItemInHand(VanillaItems::AIR());
		$res1 = $wolf->onInteract($player, Vector3::zero());
		self::assertTrue($res1);
		self::assertFalse($wolf->isSitting());

		// 2. Sitting down
		$res2 = $wolf->onInteract($player, Vector3::zero());
		self::assertTrue($res2);
		self::assertTrue($wolf->isSitting());
	}

	public function testSitStandEventCancellation() : void{
		$wolf = $this->createWolf();
		$player = $this->createPlayer("Owner");
		$wolf->tame($player);
		self::assertTrue($wolf->isSitting());

		$this->registerCancellingListener(PetSitChangeEvent::class);

		$player->getInventory()->setItemInHand(VanillaItems::AIR());
		$result = $wolf->onInteract($player, Vector3::zero());
		self::assertFalse($result);
		self::assertTrue($wolf->isSitting());
	}

	public function testSitStandNonOwnerRejected() : void{
		$wolf = $this->createWolf();
		$owner = $this->createPlayer("Owner");
		$stranger = $this->createPlayer("Stranger");
		$wolf->tame($owner);
		self::assertTrue($wolf->isSitting());

		$stranger->getInventory()->setItemInHand(VanillaItems::AIR());
		$result = $wolf->onInteract($stranger, Vector3::zero());
		self::assertFalse($result);
		self::assertTrue($wolf->isSitting());
	}

	public function testMeatHealingInteraction() : void{
		$wolf = $this->createWolf();
		$player = $this->createPlayer("Owner");
		$wolf->tame($player);
		$wolf->setHealth(10.0);

		$steak = VanillaItems::STEAK()->setCount(5);
		$player->getInventory()->setItemInHand($steak);

		$result = $wolf->onInteract($player, Vector3::zero());
		self::assertTrue($result);
		// Healed 2 to 4 HP: health should be 12.0 to 14.0
		self::assertGreaterThanOrEqual(12.0, $wolf->getHealth());
		self::assertLessThanOrEqual(14.0, $wolf->getHealth());
		self::assertSame(4, $player->getInventory()->getItemInHand()->getCount());
	}

	public function testMeatHealingClampAtMaxHealth() : void{
		$wolf = $this->createWolf();
		$player = $this->createPlayer("Owner");
		$wolf->tame($player);
		$wolf->setHealth(19.0);

		$beef = VanillaItems::RAW_BEEF()->setCount(2);
		$player->getInventory()->setItemInHand($beef);

		$result = $wolf->onInteract($player, Vector3::zero());
		self::assertTrue($result);
		self::assertEqualsWithDelta(20.0, $wolf->getHealth(), 0.001);
		self::assertSame(1, $player->getInventory()->getItemInHand()->getCount());
	}

	public function testMeatLoveModeWhenAtFullHealth() : void{
		$wolf = $this->createWolf();
		$player = $this->createPlayer("Owner");
		$wolf->tame($player);
		self::assertEqualsWithDelta(20.0, $wolf->getHealth(), 0.001);
		self::assertFalse($wolf->isInLove());

		$pork = VanillaItems::COOKED_PORKCHOP()->setCount(3);
		$player->getInventory()->setItemInHand($pork);

		$result = $wolf->onInteract($player, Vector3::zero());
		self::assertTrue($result);
		self::assertTrue($wolf->isInLove());
		self::assertSame(600, $wolf->getInLoveTicks());
		self::assertSame(2, $player->getInventory()->getItemInHand()->getCount());
	}

	public function testMeatRejectedWhenAtFullHealthAndAlreadyInLove() : void{
		$wolf = $this->createWolf();
		$player = $this->createPlayer("Owner");
		$wolf->tame($player);
		$wolf->setInLoveTicks(500);
		self::assertTrue($wolf->isSitting());

		$rotten = VanillaItems::ROTTEN_FLESH()->setCount(2);
		$player->getInventory()->setItemInHand($rotten);

		$result = $wolf->onInteract($player, Vector3::zero());
		self::assertFalse($result);
		self::assertTrue($wolf->isSitting(), "Must not toggle sit when holding meat");
		self::assertSame(2, $player->getInventory()->getItemInHand()->getCount());
	}

	public function testBoneInteractionConsumesBone() : void{
		$wolf = $this->createWolf();
		$player = $this->createPlayer("Tamer");

		$bone = VanillaItems::BONE()->setCount(3);
		$player->getInventory()->setItemInHand($bone);

		$result = $wolf->onInteract($player, Vector3::zero());
		self::assertTrue($result);
		self::assertSame(2, $player->getInventory()->getItemInHand()->getCount());
	}

	public function testCombatTargetRules() : void{
		$wolf = $this->createWolf();
		$owner = $this->createPlayer("Owner");
		$wolf->tame($owner);

		// 1. Never targets Creeper
		$creeper = $this->createCreeper();
		self::assertFalse($wolf->isValidTarget($creeper));
		$wolf->setTargetEntity($creeper);
		self::assertNull($wolf->getTargetEntity());

		// 2. Never targets Owner
		self::assertFalse($wolf->isValidTarget($owner));
		$wolf->setTargetEntity($owner);
		self::assertNull($wolf->getTargetEntity());

		// 3. Never targets another pet owned by same player
		$otherPet = $this->createWolf();
		$otherPet->tame($owner);
		self::assertFalse($wolf->isValidTarget($otherPet));
		$wolf->setTargetEntity($otherPet);
		self::assertNull($wolf->getTargetEntity());

		// 4. Valid target: hostile or unrelated player
		$enemy = $this->createPlayer("Enemy");
		self::assertTrue($wolf->isValidTarget($enemy));
		$wolf->setTargetEntity($enemy);
		self::assertSame($enemy, $wolf->getTargetEntity());
	}

	public function testCombatAttackCooldownAndDamage() : void{
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);
		$wolf = $this->createWolf($world, new Vector3(0, 0, 0));
		$wolf->setSitting(false);
		$enemy = $this->createTargetEntity($world, new Vector3(1.0, 0.0, 0.0));

		// First attack should succeed
		self::assertTrue($wolf->attackTarget($enemy));
		self::assertSame(20, $wolf->getAttackCooldownTicks());
		self::assertNotNull($enemy->lastDamageEvent);
		self::assertInstanceOf(EntityDamageByEntityEvent::class, $enemy->lastDamageEvent);
		self::assertEqualsWithDelta(4.0, $enemy->lastDamageEvent->getBaseDamage(), 0.001);
		self::assertSame(EntityDamageEvent::CAUSE_ENTITY_ATTACK, $enemy->lastDamageEvent->getCause());

		// Second attack immediately should fail due to cooldown
		$enemy->lastDamageEvent = null;
		self::assertFalse($wolf->attackTarget($enemy));
		self::assertNull($enemy->lastDamageEvent);

		// Advance cooldown
		$wolf->setAttackCooldownTicks(0);

		// Target out of range (> 1.5 blocks)
		(new ReflectionProperty(Entity::class, "location"))->setValue($enemy, new Location(3.0, 0.0, 0.0, $world, 0.0, 0.0));
		self::assertFalse($wolf->attackTarget($enemy));
		self::assertNull($enemy->lastDamageEvent);

		// Move back in range
		(new ReflectionProperty(Entity::class, "location"))->setValue($enemy, new Location(1.2, 0.0, 0.0, $world, 0.0, 0.0));
		self::assertTrue($wolf->attackTarget($enemy));
		self::assertNotNull($enemy->lastDamageEvent);
		self::assertEqualsWithDelta(4.0, $enemy->lastDamageEvent->getBaseDamage(), 0.001);

		// Sitting wolf refuses to attack
		$wolf->setAttackCooldownTicks(0);
		$wolf->setSitting(true);
		self::assertFalse($wolf->attackTarget($enemy));
	}

	public function testNbtSerializationRoundtrip() : void{
		$wolf = $this->createWolf();
		$wolf->setAngry(true);
		$wolf->setOwnerUUID("uuid-wolf-1234");
		$wolf->setOwnerName("Mani");
		$wolf->setTamed(true);
		$wolf->setSitting(true);
		$wolf->setCollarColor(DyeColor::CYAN);

		$nbt = CompoundTag::create();
		$wolf->saveNBTData($nbt);

		self::assertSame(1, $nbt->getByte(Wolf::TAG_ANGRY));
		self::assertSame("uuid-wolf-1234", $nbt->getString(TameableAnimal::TAG_OWNER_UUID));
		self::assertSame("Mani", $nbt->getString(TameableAnimal::TAG_OWNER_NAME));
		self::assertSame(1, $nbt->getByte(TameableAnimal::TAG_SITTING));
		self::assertSame(DyeColorIdMap::getInstance()->toId(DyeColor::CYAN), $nbt->getByte(TameableAnimal::TAG_COLLAR_COLOR));

		$loadedWolf = $this->createWolf();
		$loadedWolf->readNBTData($nbt);

		self::assertTrue($loadedWolf->isAngry());
		self::assertTrue($loadedWolf->isTamed());
		self::assertTrue($loadedWolf->isSitting());
		self::assertSame("uuid-wolf-1234", $loadedWolf->getOwnerUUID());
		self::assertSame("Mani", $loadedWolf->getOwnerName());
		self::assertSame(DyeColor::CYAN, $loadedWolf->getCollarColor());
		self::assertSame(20, $loadedWolf->getMaxHealth());
	}
}
