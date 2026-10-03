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
use pocketmine\event\entity\EntityRegainHealthEvent;
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
use pocketmine\network\mcpe\protocol\types\entity\IntMetadataProperty;
use pocketmine\network\mcpe\protocol\types\entity\LongMetadataProperty;
use pocketmine\player\Player;
use pocketmine\plugin\Plugin;
use pocketmine\timings\TimingsHandler;
use pocketmine\world\World;
use Ramsey\Uuid\Uuid;
use ReflectionClass;
use ReflectionProperty;

if(!class_exists(CatTestCreeper::class)){
	class CatTestCreeper extends Living{
		public static function getNetworkTypeId() : string{ return EntityIds::CREEPER; }
		protected function getInitialSizeInfo() : EntitySizeInfo{ return new EntitySizeInfo(1.7, 0.6); }
		public function getName() : string{ return "Creeper"; }
	}
}

if(!class_exists(CatTestNamedCreeper::class)){
	class CatTestNamedCreeper extends Living{
		public static function getNetworkTypeId() : string{ return "custom:creeper_mob"; }
		protected function getInitialSizeInfo() : EntitySizeInfo{ return new EntitySizeInfo(1.7, 0.6); }
		public function getName() : string{ return "Creeper"; }
	}
}

if(!class_exists(CatTestTargetEntity::class)){
	class CatTestTargetEntity extends Living{
		public static function getNetworkTypeId() : string{ return "minecraft:test_target"; }
		protected function getInitialSizeInfo() : EntitySizeInfo{ return new EntitySizeInfo(1.0, 1.0); }
		public function getName() : string{ return "Test Target"; }
	}
}

final class CatTest extends TestCase{

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

	private function createCat(?World $world = null, ?Vector3 $pos = null) : Cat{
		$cat = (new ReflectionClass(Cat::class))->newInstanceWithoutConstructor();
		(new ReflectionProperty(Entity::class, "closed"))->setValue($cat, true);
		(new ReflectionProperty(Entity::class, "id"))->setValue($cat, 30);
		(new ReflectionProperty(Entity::class, "size"))->setValue($cat, new EntitySizeInfo(0.7, 0.6));
		(new ReflectionProperty(Entity::class, "scale"))->setValue($cat, 1.0);
		(new ReflectionProperty(Entity::class, "networkProperties"))->setValue($cat, new EntityMetadataCollection());
		(new ReflectionProperty(Entity::class, "attributeMap"))->setValue($cat, new AttributeMap());
		(new ReflectionClass(Cat::class))->getMethod("addAttributes")->invoke($cat);
		(new ReflectionProperty(Living::class, "effectManager"))->setValue($cat, new EffectManager($cat));
		(new ReflectionProperty(Living::class, "armorInventory"))->setValue($cat, new ArmorInventory($cat));
		if($world === null){
			$world = $this->createMock(World::class);
			$world->method("isLoaded")->willReturn(true);
			$world->method("getPlayers")->willReturn([]);
			$world->method("getDamageY")->willReturn(-64);
		}
		$location = new Location($pos?->x ?? 0.0, $pos?->y ?? 0.0, $pos?->z ?? 0.0, $world, 0.0, 0.0);
		(new ReflectionProperty(Entity::class, "location"))->setValue($cat, $location);
		(new ReflectionProperty(Entity::class, "boundingBox"))->setValue($cat, new AxisAlignedBB(
			$location->x - 0.3,
			$location->y,
			$location->z - 0.3,
			$location->x + 0.3,
			$location->y + 0.7,
			$location->z + 0.3
		));
		(new ReflectionProperty(Entity::class, "motion"))->setValue($cat, Vector3::zero());
		(new ReflectionProperty(Entity::class, "closed"))->setValue($cat, false);
		$this->createdEntities[] = $cat;
		return $cat;
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
			$world->method("getDamageY")->willReturn(-64);
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

	private function createCreeper(?World $world = null, ?Vector3 $pos = null) : CatTestCreeper{
		$creeper = (new ReflectionClass(CatTestCreeper::class))->newInstanceWithoutConstructor();
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
			$world->method("getDamageY")->willReturn(-64);
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

	private function createTargetEntity(?World $world = null, ?Vector3 $pos = null) : CatTestTargetEntity{
		$target = (new ReflectionClass(CatTestTargetEntity::class))->newInstanceWithoutConstructor();
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
			$world->method("getDamageY")->willReturn(-64);
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

	private function getGenericFlag(EntityMetadataCollection $flags, int $flagId) : bool{
		$propertyId = $flagId >= 64 ? EntityMetadataProperties::FLAGS2 : EntityMetadataProperties::FLAGS;
		$realFlagId = $flagId % 64;
		$prop = $flags->getAll()[$propertyId] ?? null;
		if($prop instanceof LongMetadataProperty){
			return (($prop->getValue() >> $realFlagId) & 1) !== 0;
		}
		return false;
	}

	private function tickCat(Cat $cat, int $tickDiff = 1) : void{
		(new ReflectionClass(Cat::class))->getMethod("entityBaseTick")->invoke($cat, $tickDiff);
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

	public function testDefaultAttributesAndConstants() : void{
		$cat = $this->createCat();

		self::assertSame(EntityIds::CAT, Cat::getNetworkTypeId());
		self::assertSame("Cat", $cat->getName());
		self::assertSame(10, $cat->getMaxHealth());
		self::assertSame(10.0, $cat->getHealth());

		/** @var EntitySizeInfo $size */
		$size = (new ReflectionClass(Cat::class))->getMethod("getInitialSizeInfo")->invoke($cat);
		self::assertEqualsWithDelta(0.7, $size->getHeight(), 0.001);
		self::assertEqualsWithDelta(0.6, $size->getWidth(), 0.001);

		self::assertGreaterThanOrEqual(0, $cat->getCatType());
		self::assertLessThanOrEqual(10, $cat->getCatType());

		self::assertSame(0, Cat::TYPE_TABBY);
		self::assertSame(1, Cat::TYPE_BLACK);
		self::assertSame(2, Cat::TYPE_RED);
		self::assertSame(3, Cat::TYPE_SIAMESE);
		self::assertSame(4, Cat::TYPE_BRITISH_SHORTHAIR);
		self::assertSame(5, Cat::TYPE_CALICO);
		self::assertSame(6, Cat::TYPE_PERSIAN);
		self::assertSame(7, Cat::TYPE_RAGDOLL);
		self::assertSame(8, Cat::TYPE_WHITE);
		self::assertSame(9, Cat::TYPE_JELLIE);
		self::assertSame(10, Cat::TYPE_ALL_BLACK);

		self::assertSame("CatType", Cat::TAG_CAT_TYPE);

		self::assertFalse($cat->isTamed());
		self::assertFalse($cat->isSitting());
	}

	public function testEntityFactoryRegistration() : void{
		$factory = EntityFactory::getInstance();
		self::assertTrue($factory->isRegistered(Cat::class));
		self::assertSame("Cat", $factory->getSaveId(Cat::class));
	}

	public function testVariantSetterGetterAndMetadataSync() : void{
		$cat = $this->createCat();

		for($type = Cat::TYPE_TABBY; $type <= Cat::TYPE_ALL_BLACK; ++$type){
			$cat->setCatType($type);
			self::assertSame($type, $cat->getCatType());

			$variantProp = $cat->getNetworkProperties()->getAll()[EntityMetadataProperties::VARIANT] ?? null;
			self::assertInstanceOf(IntMetadataProperty::class, $variantProp);
			self::assertSame($type, $variantProp->getValue());
		}

		// Clamping tests
		$cat->setCatType(-5);
		self::assertSame(Cat::TYPE_TABBY, $cat->getCatType());

		$cat->setCatType(99);
		self::assertSame(Cat::TYPE_ALL_BLACK, $cat->getCatType());
	}

	public function testFallDamageImmunity() : void{
		$cat = $this->createCat();

		self::assertSame(0.0, $cat->calculateFallDamage(0.0));
		self::assertSame(0.0, $cat->calculateFallDamage(5.0));
		self::assertSame(0.0, $cat->calculateFallDamage(20.0));
		self::assertSame(0.0, $cat->calculateFallDamage(100.0));
	}

	public function testTameMethodDirect() : void{
		$cat = $this->createCat();
		$player = $this->createPlayer("CatLover");

		$result = $cat->tame($player);
		self::assertTrue($result);
		self::assertTrue($cat->isTamed());
		self::assertSame($player->getUniqueId()->toString(), $cat->getOwnerUUID());
		self::assertSame("CatLover", $cat->getOwnerName());
		self::assertSame(DyeColor::RED, $cat->getCollarColor());
		self::assertTrue($cat->isSitting());
		self::assertSame(10, $cat->getMaxHealth());
		self::assertSame(10.0, $cat->getHealth());

		self::assertTrue($this->getGenericFlag($cat->getNetworkProperties(), EntityMetadataFlags::TAMED));
		self::assertTrue($this->getGenericFlag($cat->getNetworkProperties(), EntityMetadataFlags::SITTING));
	}

	public function testTameEventCancellation() : void{
		$cat = $this->createCat();
		$player = $this->createPlayer("CatLover");

		$this->registerCancellingListener(EntityTameEvent::class);

		$result = $cat->tame($player);
		self::assertFalse($result);
		self::assertFalse($cat->isTamed());
		self::assertNull($cat->getOwnerUUID());
		self::assertNull($cat->getOwnerName());
		self::assertFalse($cat->isSitting());
	}

	public function testIsFishHelper() : void{
		self::assertTrue(Cat::isFish(VanillaItems::RAW_FISH()));
		self::assertTrue(Cat::isFish(VanillaItems::RAW_SALMON()));
		self::assertFalse(Cat::isFish(VanillaItems::COOKED_FISH()));
		self::assertFalse(Cat::isFish(VanillaItems::COOKED_SALMON()));
		self::assertFalse(Cat::isFish(VanillaItems::STEAK()));
		self::assertFalse(Cat::isFish(VanillaItems::BONE()));
	}

	public function testUntamedInteractionNonFishDoesNothing() : void{
		$cat = $this->createCat();
		$player = $this->createPlayer("Player", heldItem: VanillaItems::BONE()->setCount(5));

		$result = $cat->onInteract($player, Vector3::zero());
		self::assertFalse($result);
		self::assertFalse($cat->isTamed());
		self::assertSame(5, $player->getInventory()->getItemInHand()->getCount());
	}

	public function testUntamedFishInteractionConsumesFish() : void{
		$cat = $this->createCat();
		$player = $this->createPlayer("Player", heldItem: VanillaItems::RAW_FISH()->setCount(3), finiteResources: true);

		$result = $cat->onInteract($player, Vector3::zero());
		self::assertTrue($result);
		self::assertSame(2, $player->getInventory()->getItemInHand()->getCount());
	}

	public function testUntamedFishInteractionCreativeDoesNotConsume() : void{
		$cat = $this->createCat();
		$player = $this->createPlayer("Player", heldItem: VanillaItems::RAW_SALMON()->setCount(1), finiteResources: false);

		$result = $cat->onInteract($player, Vector3::zero());
		self::assertTrue($result);
		self::assertSame(1, $player->getInventory()->getItemInHand()->getCount());
	}

	public function testCollarDyeingInteraction() : void{
		$cat = $this->createCat();
		$owner = $this->createPlayer("Owner");
		$cat->tame($owner);
		self::assertSame(DyeColor::RED, $cat->getCollarColor());

		$cyanDye = VanillaItems::DYE()->setColor(DyeColor::CYAN)->setCount(2);
		$owner->getInventory()->setItemInHand($cyanDye);

		$result = $cat->onInteract($owner, Vector3::zero());
		self::assertTrue($result);
		self::assertSame(DyeColor::CYAN, $cat->getCollarColor());
		self::assertSame(1, $owner->getInventory()->getItemInHand()->getCount());

		$colorProp = $cat->getNetworkProperties()->getAll()[EntityMetadataProperties::COLOR] ?? null;
		self::assertNotNull($colorProp);
		self::assertSame(DyeColorIdMap::getInstance()->toId(DyeColor::CYAN), $colorProp->getValue());
	}

	public function testCollarDyeingCancelledDoesNotConsumeOrChangeColor() : void{
		$cat = $this->createCat();
		$owner = $this->createPlayer("Owner");
		$cat->tame($owner);
		self::assertSame(DyeColor::RED, $cat->getCollarColor());

		$this->registerCancellingListener(PetCollarColorChangeEvent::class);

		$limeDye = VanillaItems::DYE()->setColor(DyeColor::LIME)->setCount(3);
		$owner->getInventory()->setItemInHand($limeDye);

		$result = $cat->onInteract($owner, Vector3::zero());
		self::assertFalse($result);
		self::assertSame(DyeColor::RED, $cat->getCollarColor());
		self::assertSame(3, $owner->getInventory()->getItemInHand()->getCount());
	}

	public function testCollarDyeingByNonOwnerRejected() : void{
		$cat = $this->createCat();
		$owner = $this->createPlayer("Owner");
		$cat->tame($owner);

		$stranger = $this->createPlayer("Stranger");
		$stranger->getInventory()->setItemInHand(VanillaItems::DYE()->setColor(DyeColor::PURPLE)->setCount(1));

		$result = $cat->onInteract($stranger, Vector3::zero());
		self::assertFalse($result);
		self::assertSame(DyeColor::RED, $cat->getCollarColor());
		self::assertSame(1, $stranger->getInventory()->getItemInHand()->getCount());
	}

	public function testSitStandToggleInteraction() : void{
		$cat = $this->createCat();
		$owner = $this->createPlayer("Owner");
		$cat->tame($owner);
		self::assertTrue($cat->isSitting());

		// Toggle to standing
		$owner->getInventory()->setItemInHand(VanillaItems::AIR());
		$result = $cat->onInteract($owner, Vector3::zero());
		self::assertTrue($result);
		self::assertFalse($cat->isSitting());
		self::assertFalse($this->getGenericFlag($cat->getNetworkProperties(), EntityMetadataFlags::SITTING));

		// Toggle to sitting
		$result2 = $cat->onInteract($owner, Vector3::zero());
		self::assertTrue($result2);
		self::assertTrue($cat->isSitting());
		self::assertTrue($this->getGenericFlag($cat->getNetworkProperties(), EntityMetadataFlags::SITTING));
	}

	public function testSitStandToggleCancellation() : void{
		$cat = $this->createCat();
		$owner = $this->createPlayer("Owner");
		$cat->tame($owner);
		self::assertTrue($cat->isSitting());

		$this->registerCancellingListener(PetSitChangeEvent::class);

		$result = $cat->onInteract($owner, Vector3::zero());
		self::assertFalse($result);
		self::assertTrue($cat->isSitting());
	}

	public function testSitStandToggleByNonOwnerRejected() : void{
		$cat = $this->createCat();
		$owner = $this->createPlayer("Owner");
		$cat->tame($owner);
		self::assertTrue($cat->isSitting());

		$stranger = $this->createPlayer("Stranger");
		$result = $cat->onInteract($stranger, Vector3::zero());
		self::assertFalse($result);
		self::assertTrue($cat->isSitting());
	}

	public function testFishHealingInteraction() : void{
		$cat = $this->createCat();
		$owner = $this->createPlayer("Owner");
		$cat->tame($owner);
		$cat->setHealth(6.0);

		$owner->getInventory()->setItemInHand(VanillaItems::RAW_FISH()->setCount(5));

		$result = $cat->onInteract($owner, Vector3::zero());
		self::assertTrue($result);
		self::assertEqualsWithDelta(8.0, $cat->getHealth(), 0.001);
		self::assertSame(4, $owner->getInventory()->getItemInHand()->getCount());

		// Second heal caps at max health (10.0)
		$result2 = $cat->onInteract($owner, Vector3::zero());
		self::assertTrue($result2);
		self::assertEqualsWithDelta(10.0, $cat->getHealth(), 0.001);
		self::assertSame(3, $owner->getInventory()->getItemInHand()->getCount());
	}

	public function testFishHealingCancellationAbortsConsumption() : void{
		$cat = $this->createCat();
		$owner = $this->createPlayer("Owner");
		$cat->tame($owner);
		$cat->setHealth(6.0);

		$this->registerCancellingListener(EntityRegainHealthEvent::class);

		$fish = VanillaItems::RAW_FISH()->setCount(4);
		$owner->getInventory()->setItemInHand($fish);

		$result = $cat->onInteract($owner, Vector3::zero());
		self::assertFalse($result);
		self::assertEqualsWithDelta(6.0, $cat->getHealth(), 0.001);
		self::assertSame(4, $owner->getInventory()->getItemInHand()->getCount());
	}

	public function testLoveModeInteraction() : void{
		$cat = $this->createCat();
		$owner = $this->createPlayer("Owner");
		$cat->tame($owner);
		$cat->setHealth(10.0);
		self::assertFalse($cat->isInLove());

		$owner->getInventory()->setItemInHand(VanillaItems::RAW_FISH()->setCount(3));

		$result = $cat->onInteract($owner, Vector3::zero());
		self::assertTrue($result);
		self::assertTrue($cat->isInLove());
		self::assertSame(600, $cat->getInLoveTicks());
		self::assertSame(2, $owner->getInventory()->getItemInHand()->getCount());

		// Already in love -> rejects interaction
		$result2 = $cat->onInteract($owner, Vector3::zero());
		self::assertFalse($result2);
		self::assertSame(2, $owner->getInventory()->getItemInHand()->getCount());
	}

	public function testBabyCatDoesNotEnterLoveMode() : void{
		$cat = $this->createCat();
		$owner = $this->createPlayer("Owner");
		$cat->tame($owner);
		$cat->setHealth(10.0);
		$cat->setAge(-24000);
		self::assertTrue($cat->isBaby());

		$owner->getInventory()->setItemInHand(VanillaItems::RAW_SALMON()->setCount(2));

		$result = $cat->onInteract($owner, Vector3::zero());
		self::assertTrue($result);
		self::assertFalse($cat->isInLove());
		self::assertSame(-21600, $cat->getAge());
		self::assertSame(1, $owner->getInventory()->getItemInHand()->getCount());
	}

	public function testNbtSerializationRoundtrip() : void{
		$cat = $this->createCat();
		$cat->setCatType(Cat::TYPE_JELLIE);
		$cat->setOwnerUUID("uuid-cat-5678");
		$cat->setOwnerName("Felix");
		$cat->setTamed(true);
		$cat->setSitting(true);
		$cat->setCollarColor(DyeColor::LIME);
		$cat->setAge(-500);
		$cat->setInLoveTicks(300);

		$nbt = CompoundTag::create();
		$cat->saveNBTData($nbt);

		self::assertSame(Cat::TYPE_JELLIE, $nbt->getInt(Cat::TAG_CAT_TYPE));
		self::assertSame("uuid-cat-5678", $nbt->getString(TameableAnimal::TAG_OWNER_UUID));
		self::assertSame("Felix", $nbt->getString(TameableAnimal::TAG_OWNER_NAME));
		self::assertSame(1, $nbt->getByte(TameableAnimal::TAG_SITTING));
		self::assertSame(DyeColorIdMap::getInstance()->toId(DyeColor::LIME), $nbt->getByte(TameableAnimal::TAG_COLLAR_COLOR));
		self::assertSame(-500, $nbt->getInt(TameableAnimal::TAG_AGE));
		self::assertSame(300, $nbt->getInt(TameableAnimal::TAG_IN_LOVE));

		$loaded = $this->createCat();
		$loaded->readNBTData($nbt);

		self::assertSame(Cat::TYPE_JELLIE, $loaded->getCatType());
		self::assertTrue($loaded->isTamed());
		self::assertTrue($loaded->isSitting());
		self::assertSame("uuid-cat-5678", $loaded->getOwnerUUID());
		self::assertSame("Felix", $loaded->getOwnerName());
		self::assertSame(DyeColor::LIME, $loaded->getCollarColor());
		self::assertTrue($loaded->isBaby());
		self::assertSame(300, $loaded->getInLoveTicks());
	}

	public function testNbtMissingCatTypeLoadsRandomVariant() : void{
		$cat = $this->createCat();
		$nbt = CompoundTag::create();
		$cat->readNBTData($nbt);

		self::assertGreaterThanOrEqual(Cat::TYPE_TABBY, $cat->getCatType());
		self::assertLessThanOrEqual(Cat::TYPE_ALL_BLACK, $cat->getCatType());
	}

	public function testIsCreeperDetection() : void{
		$cat = $this->createCat();

		$creeper1 = $this->createCreeper();
		self::assertTrue($cat->isCreeper($creeper1));

		$namedCreeper = (new ReflectionClass(CatTestNamedCreeper::class))->newInstanceWithoutConstructor();
		(new ReflectionProperty(Entity::class, "closed"))->setValue($namedCreeper, false);
		$this->createdEntities[] = $namedCreeper;
		self::assertTrue($cat->isCreeper($namedCreeper));

		$target = $this->createTargetEntity();
		self::assertFalse($cat->isCreeper($target));

		$player = $this->createPlayer("Steve");
		self::assertFalse($cat->isCreeper($player));

		// Dead creeper
		$creeper1->setHealth(0.0);
		self::assertFalse($cat->isCreeper($creeper1));

		// Closed creeper
		$creeper2 = $this->createCreeper();
		(new ReflectionProperty(Entity::class, "closed"))->setValue($creeper2, true);
		self::assertFalse($cat->isCreeper($creeper2));
	}

	public function testCreeperAvoidanceRepelMethod() : void{
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);
		$world->method("getDamageY")->willReturn(-64);

		$cat = $this->createCat($world, new Vector3(0, 0, 0));

		// Creeper within 10 blocks: at (3, 0, 4) -> distance = 5 blocks
		$nearbyCreeper = $this->createCreeper($world, new Vector3(3, 0, 4));
		(new ReflectionProperty(Entity::class, "motion"))->setValue($nearbyCreeper, new Vector3(0, -0.08, 0));

		// Creeper outside 10 blocks: at (15, 0, 0) -> distance = 15 blocks
		$distantCreeper = $this->createCreeper($world, new Vector3(15, 0, 0));
		(new ReflectionProperty(Entity::class, "motion"))->setValue($distantCreeper, Vector3::zero());

		// Non-creeper within 10 blocks: at (2, 0, 0)
		$target = $this->createTargetEntity($world, new Vector3(2, 0, 0));
		(new ReflectionProperty(Entity::class, "motion"))->setValue($target, Vector3::zero());

		$world->method("getNearbyEntities")->willReturn([$nearbyCreeper, $distantCreeper, $target]);

		$cat->repelCreepers();

		// Check nearby creeper motion:
		// dx = 3 - 0 = 3, dz = 4 - 0 = 4, dist = 5.
		// unit dx = 3/5 = 0.6, unit dz = 4/5 = 0.8
		// motion.x = 0.6 * 0.3 = 0.18, motion.z = 0.8 * 0.3 = 0.24, motion.y preserved (-0.08)
		$motion = $nearbyCreeper->getMotion();
		self::assertEqualsWithDelta(0.18, $motion->x, 0.001);
		self::assertEqualsWithDelta(-0.08, $motion->y, 0.001);
		self::assertEqualsWithDelta(0.24, $motion->z, 0.001);

		// Distant creeper: untouched
		self::assertEqualsWithDelta(0.0, $distantCreeper->getMotion()->x, 0.001);
		self::assertEqualsWithDelta(0.0, $distantCreeper->getMotion()->z, 0.001);

		// Target entity: untouched
		self::assertEqualsWithDelta(0.0, $target->getMotion()->x, 0.001);
		self::assertEqualsWithDelta(0.0, $target->getMotion()->z, 0.001);
	}

	public function testCreeperAvoidanceRepelAtSameLocation() : void{
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);
		$world->method("getDamageY")->willReturn(-64);

		$cat = $this->createCat($world, new Vector3(0, 0, 0));
		$coincidentCreeper = $this->createCreeper($world, new Vector3(0, 0, 0));

		$world->method("getNearbyEntities")->willReturn([$coincidentCreeper]);

		$cat->repelCreepers();

		// When distance is 0, defaults to dx = 1.0, dz = 0.0 -> motion.x = 0.3
		$motion = $coincidentCreeper->getMotion();
		self::assertEqualsWithDelta(0.3, $motion->x, 0.001);
		self::assertEqualsWithDelta(0.0, $motion->z, 0.001);
	}

	public function testCreeperAvoidanceTickTrigger() : void{
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);
		$world->method("getDamageY")->willReturn(-64);

		$cat = $this->createCat($world, new Vector3(0, 0, 0));
		$creeper = $this->createCreeper($world, new Vector3(5, 0, 0));
		$world->method("getNearbyEntities")->willReturn([$creeper]);

		// Ticking 1..9 times should not trigger repel
		for($i = 1; $i <= 9; ++$i){
			$this->tickCat($cat, 1);
			self::assertEqualsWithDelta(0.0, $creeper->getMotion()->x, 0.001);
		}

		// 10th tick should trigger repel
		$this->tickCat($cat, 1);
		self::assertEqualsWithDelta(0.3, $creeper->getMotion()->x, 0.001);

		// Reset creeper motion
		(new ReflectionProperty(Entity::class, "motion"))->setValue($creeper, Vector3::zero());

		// Dead cat should not repel
		$cat->setHealth(0.0);
		$this->tickCat($cat, 10);
		self::assertEqualsWithDelta(0.0, $creeper->getMotion()->x, 0.001);
	}
}
