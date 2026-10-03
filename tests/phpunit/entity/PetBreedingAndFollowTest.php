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
use pocketmine\block\Block;
use pocketmine\block\utils\DyeColor;
use pocketmine\entity\effect\EffectManager;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\inventory\ArmorInventory;
use pocketmine\inventory\PlayerInventory;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\player\Player;
use pocketmine\world\World;
use Ramsey\Uuid\Uuid;
use ReflectionClass;
use ReflectionProperty;
use function in_array;
use function sqrt;

if(!class_exists(PetBreedingAndFollowTestTargetEntity::class)){
	class PetBreedingAndFollowTestTargetEntity extends Living{
		public static function getNetworkTypeId() : string{ return "minecraft:test_target"; }
		protected function getInitialSizeInfo() : EntitySizeInfo{ return new EntitySizeInfo(1.0, 1.0); }
		public function getName() : string{ return "Test Target"; }

		public function attack(EntityDamageEvent $source) : void{
			// Prevent world/server lookup during follow movement attack
		}
	}
}

final class PetBreedingAndFollowTest extends TestCase{

	/** @var Entity[] */
	private array $createdEntities = [];

	/** @var array<int, Player[]> */
	private array $worldPlayers = [];

	protected function tearDown() : void{
		foreach($this->createdEntities as $entity){
			(new ReflectionProperty(Entity::class, "closed"))->setValue($entity, true);
		}
		$this->createdEntities = [];
		$this->worldPlayers = [];
		parent::tearDown();
	}

	private function createMockWorld() : World{
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);
		$worldId = spl_object_id($world);
		$this->worldPlayers[$worldId] = [];
		$world->method("getPlayers")->willReturnCallback(function() use ($worldId) : array{
			return $this->worldPlayers[$worldId] ?? [];
		});
		$world->method("getDamageY")->willReturn(-64);
		$world->method("getViewersForPosition")->willReturn([]);
		return $world;
	}

	private function createWolf(?World $world = null, ?Vector3 $pos = null) : Wolf{
		$wolf = (new ReflectionClass(Wolf::class))->newInstanceWithoutConstructor();
		(new ReflectionProperty(Entity::class, "closed"))->setValue($wolf, false);
		(new ReflectionProperty(Entity::class, "id"))->setValue($wolf, Entity::nextRuntimeId());
		(new ReflectionProperty(Entity::class, "size"))->setValue($wolf, new EntitySizeInfo(0.85, 0.6));
		(new ReflectionProperty(Entity::class, "scale"))->setValue($wolf, 1.0);
		(new ReflectionProperty(Entity::class, "networkProperties"))->setValue($wolf, new EntityMetadataCollection());
		(new ReflectionProperty(Entity::class, "attributeMap"))->setValue($wolf, new AttributeMap());
		(new ReflectionClass(Wolf::class))->getMethod("addAttributes")->invoke($wolf);
		(new ReflectionProperty(Living::class, "effectManager"))->setValue($wolf, new EffectManager($wolf));
		(new ReflectionProperty(Living::class, "armorInventory"))->setValue($wolf, new ArmorInventory($wolf));
		if($world === null){
			$world = $this->createMockWorld();
		}
		$location = new Location($pos?->x ?? 0.0, $pos?->y ?? 0.0, $pos?->z ?? 0.0, $world, 0.0, 0.0);
		(new ReflectionProperty(Entity::class, "location"))->setValue($wolf, $location);
		(new ReflectionProperty(Entity::class, "lastLocation"))->setValue($wolf, clone $location);
		(new ReflectionProperty(Entity::class, "boundingBox"))->setValue($wolf, new AxisAlignedBB(
			$location->x - 0.3,
			$location->y,
			$location->z - 0.3,
			$location->x + 0.3,
			$location->y + 0.85,
			$location->z + 0.3
		));
		(new ReflectionProperty(Entity::class, "motion"))->setValue($wolf, Vector3::zero());
		(new ReflectionProperty(Entity::class, "lastMotion"))->setValue($wolf, Vector3::zero());
		$this->createdEntities[] = $wolf;
		return $wolf;
	}

	private function createCat(?World $world = null, ?Vector3 $pos = null) : Cat{
		$cat = (new ReflectionClass(Cat::class))->newInstanceWithoutConstructor();
		(new ReflectionProperty(Entity::class, "closed"))->setValue($cat, false);
		(new ReflectionProperty(Entity::class, "id"))->setValue($cat, Entity::nextRuntimeId());
		(new ReflectionProperty(Entity::class, "size"))->setValue($cat, new EntitySizeInfo(0.7, 0.6));
		(new ReflectionProperty(Entity::class, "scale"))->setValue($cat, 1.0);
		(new ReflectionProperty(Entity::class, "networkProperties"))->setValue($cat, new EntityMetadataCollection());
		(new ReflectionProperty(Entity::class, "attributeMap"))->setValue($cat, new AttributeMap());
		(new ReflectionClass(Cat::class))->getMethod("addAttributes")->invoke($cat);
		(new ReflectionProperty(Living::class, "effectManager"))->setValue($cat, new EffectManager($cat));
		(new ReflectionProperty(Living::class, "armorInventory"))->setValue($cat, new ArmorInventory($cat));
		if($world === null){
			$world = $this->createMockWorld();
		}
		$location = new Location($pos?->x ?? 0.0, $pos?->y ?? 0.0, $pos?->z ?? 0.0, $world, 0.0, 0.0);
		(new ReflectionProperty(Entity::class, "location"))->setValue($cat, $location);
		(new ReflectionProperty(Entity::class, "lastLocation"))->setValue($cat, clone $location);
		(new ReflectionProperty(Entity::class, "boundingBox"))->setValue($cat, new AxisAlignedBB(
			$location->x - 0.3,
			$location->y,
			$location->z - 0.3,
			$location->x + 0.3,
			$location->y + 0.7,
			$location->z + 0.3
		));
		(new ReflectionProperty(Entity::class, "motion"))->setValue($cat, Vector3::zero());
		(new ReflectionProperty(Entity::class, "lastMotion"))->setValue($cat, Vector3::zero());
		$this->createdEntities[] = $cat;
		return $cat;
	}

	private function createPlayer(
		string $name = "Steve",
		?string $uuid = null,
		?Item $heldItem = null,
		bool $finiteResources = true,
		?World $world = null,
		?Vector3 $pos = null
	) : Player{
		$uuidObj = $uuid !== null ? Uuid::fromString($uuid) : Uuid::uuid4();
		$player = $this->getMockBuilder(Player::class)
			->disableOriginalConstructor()
			->onlyMethods(["getName", "getUniqueId", "isSneaking", "isCreative", "isSpectator", "getInventory", "hasFiniteResources", "getViewers", "canInteract", "isOnline"])
			->getMock();
		$player->method("getName")->willReturn($name);
		$player->method("getUniqueId")->willReturn($uuidObj);
		$player->method("isSneaking")->willReturn(false);
		$player->method("isCreative")->willReturn(!$finiteResources);
		$player->method("isSpectator")->willReturn(false);
		$player->method("hasFiniteResources")->willReturn($finiteResources);
		$player->method("getViewers")->willReturn([]);
		$player->method("canInteract")->willReturn(true);
		$player->method("isOnline")->willReturn(true);

		(new ReflectionProperty(Entity::class, "closed"))->setValue($player, false);
		(new ReflectionProperty(Entity::class, "id"))->setValue($player, Entity::nextRuntimeId());
		(new ReflectionProperty(Player::class, "logger"))->setValue($player, $this->createMock(Logger::class));
		(new ReflectionProperty(Living::class, "effectManager"))->setValue($player, new EffectManager($player));
		(new ReflectionProperty(Entity::class, "attributeMap"))->setValue($player, new AttributeMap());
		(new ReflectionClass(Player::class))->getMethod("addAttributes")->invoke($player);
		(new ReflectionProperty(Living::class, "armorInventory"))->setValue($player, new ArmorInventory($player));
		(new ReflectionProperty(Entity::class, "networkProperties"))->setValue($player, new EntityMetadataCollection());

		if($world === null){
			$world = $this->createMockWorld();
		}
		$location = new Location($pos?->x ?? 0.0, $pos?->y ?? 0.0, $pos?->z ?? 0.0, $world, 0.0, 0.0);
		(new ReflectionProperty(Entity::class, "location"))->setValue($player, $location);
		(new ReflectionProperty(Entity::class, "lastLocation"))->setValue($player, clone $location);
		(new ReflectionProperty(Entity::class, "boundingBox"))->setValue($player, new AxisAlignedBB(
			$location->x - 0.3,
			$location->y,
			$location->z - 0.3,
			$location->x + 0.3,
			$location->y + 1.8,
			$location->z + 0.3
		));
		(new ReflectionProperty(Entity::class, "motion"))->setValue($player, Vector3::zero());
		(new ReflectionProperty(Entity::class, "lastMotion"))->setValue($player, Vector3::zero());

		$inventory = new PlayerInventory($player);
		if($heldItem !== null){
			$inventory->setItemInHand($heldItem);
		}
		$player->method("getInventory")->willReturn($inventory);

		$worldId = spl_object_id($world);
		$this->worldPlayers[$worldId][] = $player;

		$this->createdEntities[] = $player;
		return $player;
	}

	private function createTargetEntity(?World $world = null, ?Vector3 $pos = null) : PetBreedingAndFollowTestTargetEntity{
		$target = (new ReflectionClass(PetBreedingAndFollowTestTargetEntity::class))->newInstanceWithoutConstructor();
		(new ReflectionProperty(Entity::class, "closed"))->setValue($target, false);
		(new ReflectionProperty(Entity::class, "id"))->setValue($target, Entity::nextRuntimeId());
		(new ReflectionProperty(Entity::class, "size"))->setValue($target, new EntitySizeInfo(1.0, 1.0));
		(new ReflectionProperty(Entity::class, "scale"))->setValue($target, 1.0);
		(new ReflectionProperty(Entity::class, "networkProperties"))->setValue($target, new EntityMetadataCollection());
		(new ReflectionProperty(Entity::class, "attributeMap"))->setValue($target, new AttributeMap());
		(new ReflectionClass(Living::class))->getMethod("addAttributes")->invoke($target);
		(new ReflectionProperty(Living::class, "effectManager"))->setValue($target, new EffectManager($target));
		(new ReflectionProperty(Living::class, "armorInventory"))->setValue($target, new ArmorInventory($target));
		if($world === null){
			$world = $this->createMockWorld();
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

	public function testBabyFeedingReducesAge() : void{
		$wolf = $this->createWolf();
		$wolf->setAge(-24000);
		self::assertTrue($wolf->isBaby());

		// 1st feed: accelerates by 2,400 ticks (10% of childhood)
		$fed = $wolf->feedBaby();
		self::assertTrue($fed);
		self::assertSame(-21600, $wolf->getAge());
		self::assertTrue($wolf->isBaby());

		// Feed when near maturity: transitions to adult and caps at 0
		$wolf->setAge(-1000);
		self::assertTrue($wolf->isBaby());
		$fed = $wolf->feedBaby();
		self::assertTrue($fed);
		self::assertSame(0, $wolf->getAge());
		self::assertFalse($wolf->isBaby());

		// Feeding an adult returns false
		$fedAgain = $wolf->feedBaby();
		self::assertFalse($fedAgain);
		self::assertSame(0, $wolf->getAge());
	}

	public function testSittingSuppressesMovement() : void{
		$world = $this->createMockWorld();
		$owner = $this->createPlayer("Owner", null, null, true, $world, new Vector3(5.0, 0.0, 0.0));

		$wolf = $this->createWolf($world, new Vector3(0.0, 0.0, 0.0));
		$wolf->setTamed(true);
		$wolf->setOwnerUUID($owner->getUniqueId()->toString());
		$wolf->setSitting(true);

		// Give pet initial velocity
		$wolf->setMotion(new Vector3(0.5, 0.0, 0.5));
		$wolf->tickFollowMovement();

		// Sitting must clear horizontal motion
		self::assertSame(0.0, $wolf->getMotion()->x);
		self::assertSame(0.0, $wolf->getMotion()->z);
	}

	public function testFollowMovementSetsVelocityTowardsOwner() : void{
		$world = $this->createMockWorld();
		$owner = $this->createPlayer("Owner", null, null, true, $world, new Vector3(5.0, 0.0, 0.0));

		$wolf = $this->createWolf($world, new Vector3(0.0, 0.0, 0.0));
		$wolf->setTamed(true);
		$wolf->setOwnerUUID($owner->getUniqueId()->toString());
		$wolf->setSitting(false);

		$wolf->tickFollowMovement();

		$motion = $wolf->getMotion();
		// Pet should move towards owner in +X direction with speed ~0.25
		self::assertEqualsWithDelta(0.25, $motion->x, 0.001);
		self::assertEqualsWithDelta(0.0, $motion->z, 0.001);
		$horizontalSpeed = sqrt($motion->x ** 2 + $motion->z ** 2);
		self::assertEqualsWithDelta(0.25, $horizontalSpeed, 0.001);

		// Comfort zone check (<= 3.0 blocks): pet halts horizontal motion
		(new ReflectionProperty(Entity::class, "location"))->setValue($owner, new Location(2.0, 0.0, 0.0, $world, 0.0, 0.0));
		$wolf->setMotion(new Vector3(0.25, 0.0, 0.0));
		$wolf->tickFollowMovement();

		self::assertSame(0.0, $wolf->getMotion()->x);
		self::assertSame(0.0, $wolf->getMotion()->z);
	}

	public function testFollowStepUpAppliesJumpVelocityWhenCollidedHorizontally() : void{
		$world = $this->createMockWorld();
		$owner = $this->createPlayer("Owner", null, null, true, $world, new Vector3(5.0, 0.0, 0.0));

		$wolf = $this->createWolf($world, new Vector3(0.0, 0.0, 0.0));
		$wolf->setTamed(true);
		$wolf->setOwnerUUID($owner->getUniqueId()->toString());
		$wolf->setSitting(false);

		// Collision flags indicate obstacle in front while grounded
		$wolf->isCollidedHorizontally = true;
		$wolf->onGround = true;

		$wolf->tickFollowMovement();

		// Vertical jump velocity must be set to 0.42
		self::assertEqualsWithDelta(0.42, $wolf->getMotion()->y, 0.001);
		self::assertEqualsWithDelta(0.25, $wolf->getMotion()->x, 0.001);
	}

	public function testSafeTeleportationWhenDistanceExceedsTwelveBlocks() : void{
		$world = $this->createMockWorld();

		$solidFloor = $this->createMock(Block::class);
		$solidFloor->method("isSolid")->willReturn(true);

		$airBlock = $this->createMock(Block::class);
		$airBlock->method("isSolid")->willReturn(false);

		// Owner at (20.0, 10.0, 20.0), floor is at y = 9, stand is at y = 10, above is at y = 11
		$world->method("getBlockAt")->willReturnCallback(function(int $x, int $y, int $z) use ($solidFloor, $airBlock){
			if($y === 9){
				return $solidFloor;
			}
			return $airBlock;
		});

		$owner = $this->createPlayer("Owner", null, null, true, $world, new Vector3(20.0, 10.0, 20.0));

		$wolf = $this->createWolf($world, new Vector3(0.0, 10.0, 0.0));
		$wolf->setTamed(true);
		$wolf->setOwnerUUID($owner->getUniqueId()->toString());
		$wolf->setSitting(false);

		// Distance is ~28.28 blocks (> 12.0)
		self::assertGreaterThan(12.0, $wolf->getLocation()->distance($owner->getLocation()));

		$wolf->tickFollowMovement();

		// Teleported within comfort grid near owner
		$distanceAfter = $wolf->getLocation()->distance($owner->getLocation());
		self::assertLessThanOrEqual(3.0, $distanceAfter);
		self::assertSame(10.0, $wolf->getLocation()->y);

		// Motion must be zeroed upon teleportation
		self::assertEqualsWithDelta(0.0, $wolf->getMotion()->x, 0.001);
		self::assertEqualsWithDelta(0.0, $wolf->getMotion()->y, 0.001);
		self::assertEqualsWithDelta(0.0, $wolf->getMotion()->z, 0.001);
		self::assertTrue($wolf->getMotion()->equals(Vector3::zero()));
	}

	public function testInLoveBreedingProducesBaby() : void{
		$world = $this->createMockWorld();

		$wolf1 = $this->createWolf($world, new Vector3(0.0, 0.0, 0.0));
		$wolf2 = $this->createWolf($world, new Vector3(1.0, 0.0, 0.0));

		$ownerUuid = Uuid::uuid4()->toString();
		$wolf1->setTamed(true);
		$wolf1->setOwnerUUID($ownerUuid);
		$wolf1->setOwnerName("Mani");
		$wolf1->setCollarColor(DyeColor::BLUE);
		$wolf1->setAge(0);
		$wolf1->setInLoveTicks(600);

		$wolf2->setTamed(true);
		$wolf2->setOwnerUUID($ownerUuid);
		$wolf2->setOwnerName("Mani");
		$wolf2->setCollarColor(DyeColor::RED);
		$wolf2->setAge(0);
		$wolf2->setInLoveTicks(600);

		$world->method("getNearbyEntities")->willReturn([$wolf2]);

		// Breed produces auto-tamed baby with age -24000
		$baby = $wolf1->breed($wolf2);
		self::assertInstanceOf(Wolf::class, $baby);
		$this->createdEntities[] = $baby;

		self::assertSame(-24000, $baby->getAge());
		self::assertTrue($baby->isBaby());
		self::assertTrue($baby->isTamed());
		self::assertSame($ownerUuid, $baby->getOwnerUUID());
		self::assertSame("Mani", $baby->getOwnerName());
		self::assertTrue(in_array($baby->getCollarColor(), [DyeColor::BLUE, DyeColor::RED], true));

		// Both parents cleared from love mode
		self::assertSame(0, $wolf1->getInLoveTicks());
		self::assertFalse($wolf1->isInLove());
		self::assertSame(0, $wolf2->getInLoveTicks());
		self::assertFalse($wolf2->isInLove());
	}

	public function testBabyFoodInteractionAcceleratesGrowth() : void{
		// 1. Wolf with Meat
		$wolf = $this->createWolf();
		$wolf->setTamed(true);
		$wolf->setAge(-24000);
		self::assertTrue($wolf->isBaby());

		$player = $this->createPlayer("Player", null, VanillaItems::RAW_BEEF()->setCount(5), true);
		$success = $wolf->onInteract($player, Vector3::zero());

		self::assertTrue($success);
		self::assertSame(-21600, $wolf->getAge());
		self::assertSame(4, $player->getInventory()->getItemInHand()->getCount());

		// 2. Cat with Fish
		$cat = $this->createCat();
		$cat->setTamed(true);
		$cat->setAge(-24000);
		self::assertTrue($cat->isBaby());

		$playerCat = $this->createPlayer("PlayerCat", null, VanillaItems::RAW_FISH()->setCount(3), true);
		$catSuccess = $cat->onInteract($playerCat, Vector3::zero());

		self::assertTrue($catSuccess);
		self::assertSame(-21600, $cat->getAge());
		self::assertSame(2, $playerCat->getInventory()->getItemInHand()->getCount());
	}

	public function testUntamedPetDoesNotFollow() : void{
		$world = $this->createMockWorld();
		$player = $this->createPlayer("Player", null, null, true, $world, new Vector3(5.0, 0.0, 0.0));

		$wolf = $this->createWolf($world, new Vector3(0.0, 0.0, 0.0));
		$wolf->setTamed(false);
		$wolf->setMotion(new Vector3(0.5, 0.0, 0.5));

		$wolf->tickFollowMovement();

		// Untamed pet horizontal motion is zeroed and does not pursue player
		self::assertSame(0.0, $wolf->getMotion()->x);
		self::assertSame(0.0, $wolf->getMotion()->z);
	}

	public function testOfflineOwnerHaltsPetMovement() : void{
		$world = $this->createMockWorld();
		$owner = $this->createPlayer("Owner", null, null, true, $world, new Vector3(5.0, 0.0, 0.0));
		$this->worldPlayers[spl_object_id($world)] = []; // Owner not in world

		$wolf = $this->createWolf($world, new Vector3(0.0, 0.0, 0.0));
		$wolf->setTamed(true);
		$wolf->setOwnerUUID($owner->getUniqueId()->toString());
		$wolf->setMotion(new Vector3(0.5, 0.0, 0.5));

		$wolf->tickFollowMovement();

		self::assertSame(0.0, $wolf->getMotion()->x);
		self::assertSame(0.0, $wolf->getMotion()->z);
	}

	public function testCatBreedingInheritsParentVariant() : void{
		$world = $this->createMockWorld();

		$cat1 = $this->createCat($world, new Vector3(0.0, 0.0, 0.0));
		$cat2 = $this->createCat($world, new Vector3(1.0, 0.0, 0.0));

		$ownerUuid = Uuid::uuid4()->toString();
		$cat1->setTamed(true);
		$cat1->setOwnerUUID($ownerUuid);
		$cat1->setCatType(Cat::TYPE_SIAMESE);
		$cat1->setAge(0);
		$cat1->setInLoveTicks(600);

		$cat2->setTamed(true);
		$cat2->setOwnerUUID($ownerUuid);
		$cat2->setCatType(Cat::TYPE_JELLIE);
		$cat2->setAge(0);
		$cat2->setInLoveTicks(600);

		$babyCat = $cat1->breed($cat2);
		self::assertInstanceOf(Cat::class, $babyCat);
		$this->createdEntities[] = $babyCat;

		self::assertTrue(in_array($babyCat->getCatType(), [Cat::TYPE_SIAMESE, Cat::TYPE_JELLIE], true));
		self::assertTrue($babyCat->isBaby());
		self::assertTrue($babyCat->isTamed());
		self::assertSame($ownerUuid, $babyCat->getOwnerUUID());
	}

	public function testIncompatiblePartnersDoNotBreed() : void{
		$world = $this->createMockWorld();

		$wolf1 = $this->createWolf($world, new Vector3(0.0, 0.0, 0.0));
		$wolf2 = $this->createWolf($world, new Vector3(1.0, 0.0, 0.0));

		// Different owners
		$wolf1->setTamed(true);
		$wolf1->setOwnerUUID(Uuid::uuid4()->toString());
		$wolf1->setInLoveTicks(600);

		$wolf2->setTamed(true);
		$wolf2->setOwnerUUID(Uuid::uuid4()->toString());
		$wolf2->setInLoveTicks(600);

		$world->method("getNearbyEntities")->willReturn([$wolf2]);
		$mate = $wolf1->findEligibleMate();

		self::assertNull($mate);
	}

	public function testAgingInEntityBaseTickTransitionsToAdult() : void{
		$wolf = $this->createWolf();
		$wolf->setAge(-10);
		self::assertTrue($wolf->isBaby());

		(new ReflectionClass(Wolf::class))->getMethod("entityBaseTick")->invoke($wolf, 5);
		self::assertSame(-5, $wolf->getAge());
		self::assertTrue($wolf->isBaby());

		(new ReflectionClass(Wolf::class))->getMethod("entityBaseTick")->invoke($wolf, 10);
		self::assertSame(0, $wolf->getAge());
		self::assertFalse($wolf->isBaby());
	}

	public function testDifferentWorldOwnerHaltsPetMovement() : void{
		$world1 = $this->createMockWorld();
		$world2 = $this->createMockWorld();

		$owner = $this->createPlayer("Owner", null, null, true, $world2, new Vector3(5.0, 0.0, 0.0));
		$world1->method("getPlayers")->willReturn([]);

		$wolf = $this->createWolf($world1, new Vector3(0.0, 0.0, 0.0));
		$wolf->setTamed(true);
		$wolf->setOwnerUUID($owner->getUniqueId()->toString());
		$wolf->setMotion(new Vector3(0.5, 0.0, 0.5));

		$wolf->tickFollowMovement();

		self::assertSame(0.0, $wolf->getMotion()->x);
		self::assertSame(0.0, $wolf->getMotion()->z);
	}

	public function testBabyPetCannotBreed() : void{
		$world = $this->createMockWorld();

		$baby = $this->createWolf($world, new Vector3(0.0, 0.0, 0.0));
		$adult = $this->createWolf($world, new Vector3(1.0, 0.0, 0.0));

		$ownerUuid = Uuid::uuid4()->toString();
		$baby->setTamed(true);
		$baby->setOwnerUUID($ownerUuid);
		$baby->setAge(-24000);
		$baby->setInLoveTicks(600);

		$adult->setTamed(true);
		$adult->setOwnerUUID($ownerUuid);
		$adult->setAge(0);
		$adult->setInLoveTicks(600);

		$world->method("getNearbyEntities")->willReturn([$adult]);

		self::assertNull($baby->findEligibleMate());
	}

	public function testSittingPetCannotBreed() : void{
		$world = $this->createMockWorld();

		$wolf1 = $this->createWolf($world, new Vector3(0.0, 0.0, 0.0));
		$wolf2 = $this->createWolf($world, new Vector3(1.0, 0.0, 0.0));

		$ownerUuid = Uuid::uuid4()->toString();
		$wolf1->setTamed(true);
		$wolf1->setOwnerUUID($ownerUuid);
		$wolf1->setSitting(true);
		$wolf1->setAge(0);
		$wolf1->setInLoveTicks(600);

		$wolf2->setTamed(true);
		$wolf2->setOwnerUUID($ownerUuid);
		$wolf2->setSitting(false);
		$wolf2->setAge(0);
		$wolf2->setInLoveTicks(600);

		$world->method("getNearbyEntities")->willReturn([$wolf1]);

		// wolf2 scanning for mate finds wolf1 sitting, so ineligible
		self::assertNull($wolf2->findEligibleMate());
	}

	public function testUnsafeLandingDoesNotTeleport() : void{
		$world = $this->createMockWorld();

		$solidFloor = $this->createMock(Block::class);
		$solidFloor->method("isSolid")->willReturn(true);

		// Everything around owner is solid (suffocation / obstruction)
		$world->method("getBlockAt")->willReturn($solidFloor);

		$owner = $this->createPlayer("Owner", null, null, true, $world, new Vector3(30.0, 10.0, 30.0));

		$wolf = $this->createWolf($world, new Vector3(0.0, 10.0, 0.0));
		$wolf->setTamed(true);
		$wolf->setOwnerUUID($owner->getUniqueId()->toString());
		$wolf->setMotion(new Vector3(0.5, 0.0, 0.5));

		$wolf->tickFollowMovement();

		// Did not teleport because no safe spot exists
		self::assertSame(0.0, $wolf->getLocation()->x);
		self::assertSame(0.0, $wolf->getLocation()->z);
		// Horizontal motion cleared
		self::assertSame(0.0, $wolf->getMotion()->x);
		self::assertSame(0.0, $wolf->getMotion()->z);
	}

	public function testTamedWolfTargetPursuitSetsMotionTowardsTarget() : void{
		$world = $this->createMockWorld();
		$owner = $this->createPlayer("Owner", null, null, true, $world, new Vector3(10.0, 0.0, 0.0));

		$wolf = $this->createWolf($world, new Vector3(0.0, 0.0, 0.0));
		$wolf->setTamed(true);
		$wolf->setOwnerUUID($owner->getUniqueId()->toString());
		$wolf->setSitting(false);

		$enemy = $this->createTargetEntity($world, new Vector3(0.0, 0.0, 5.0));
		$wolf->setTargetEntity($enemy);

		$wolf->tickFollowMovement();

		$motion = $wolf->getMotion();
		// Moves towards target at speed 0.35 in +Z direction rather than towards owner in +X
		self::assertEqualsWithDelta(0.0, $motion->x, 0.001);
		self::assertEqualsWithDelta(0.35, $motion->z, 0.001);
		$horizontalSpeed = sqrt($motion->x ** 2 + $motion->z ** 2);
		self::assertEqualsWithDelta(0.35, $horizontalSpeed, 0.001);

		// Within melee range (<= 1.5 blocks): halts horizontal motion
		(new ReflectionProperty(Entity::class, "location"))->setValue($enemy, new Location(0.0, 0.0, 1.0, $world, 0.0, 0.0));
		$wolf->tickFollowMovement();

		self::assertSame(0.0, $wolf->getMotion()->x);
		self::assertSame(0.0, $wolf->getMotion()->z);

		// When target becomes closed / invalid, clears target and falls back to owner follow
		(new ReflectionProperty(Entity::class, "closed"))->setValue($enemy, true);
		$wolf->tickFollowMovement();
		self::assertNull($wolf->getTargetEntity());
		self::assertEqualsWithDelta(0.25, $wolf->getMotion()->x, 0.001);
		self::assertEqualsWithDelta(0.0, $wolf->getMotion()->z, 0.001);

		// When sitting, target pursuit is suppressed and horizontal motion is cleared
		$wolf->setSitting(true);
		$wolf->setMotion(new Vector3(0.5, 0.0, 0.5));
		$wolf->tickFollowMovement();
		self::assertSame(0.0, $wolf->getMotion()->x);
		self::assertSame(0.0, $wolf->getMotion()->z);
	}
}
