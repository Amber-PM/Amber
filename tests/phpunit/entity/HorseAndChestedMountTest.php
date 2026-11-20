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
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\effect\EffectManager;
use pocketmine\inventory\ArmorInventory;
use pocketmine\inventory\PlayerInventory;
use pocketmine\inventory\PlayerOffHandInventory;
use pocketmine\item\Item;
use pocketmine\item\ItemIdentifier;
use pocketmine\item\ItemTypeIds;
use pocketmine\item\VanillaItems;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\player\Player;
use pocketmine\world\World;
use Ramsey\Uuid\Uuid;
use ReflectionClass;
use ReflectionProperty;

class HorseAndChestedMountTest extends TestCase{

	/** @var Entity[] */
	private array $createdEntities = [];

	protected function tearDown() : void{
		foreach($this->createdEntities as $entity){
			if(!$entity->isClosed()){
				$ref = new ReflectionProperty(Entity::class, "closed");
				$ref->setValue($entity, true);
			}
		}
		$this->createdEntities = [];
		parent::tearDown();
	}

	private function createMockWorld(array $entities = []) : World{
		$world = $this->createMock(World::class);
		$world->method('isLoaded')->willReturn(true);
		$world->method('getEntities')->willReturn($entities);
		$world->method('getNearbyEntities')->willReturn($entities);
		$world->method('getBlockAt')->willReturn(VanillaBlocks::AIR());
		return $world;
	}

	private function createHorse(?World $world = null) : Horse{
		$world ??= $this->createMockWorld();
		$location = new Location(100.0, 64.0, 100.0, $world, 0.0, 0.0);
		$nbt = new CompoundTag();
		$horse = new Horse($location, $nbt);

		$ref = new ReflectionClass(Entity::class);
		$lastLocProp = $ref->getProperty('lastLocation');
		$lastLocProp->setValue($horse, clone $location);
		$lastMotProp = $ref->getProperty('lastMotion');
		$lastMotProp->setValue($horse, Vector3::zero());

		$this->createdEntities[] = $horse;
		return $horse;
	}

	private function createDonkey(?World $world = null) : Donkey{
		$world ??= $this->createMockWorld();
		$location = new Location(100.0, 64.0, 100.0, $world, 0.0, 0.0);
		$nbt = new CompoundTag();
		$donkey = new Donkey($location, $nbt);

		$ref = new ReflectionClass(Entity::class);
		$lastLocProp = $ref->getProperty('lastLocation');
		$lastLocProp->setValue($donkey, clone $location);
		$lastMotProp = $ref->getProperty('lastMotion');
		$lastMotProp->setValue($donkey, Vector3::zero());

		$this->createdEntities[] = $donkey;
		return $donkey;
	}

	private function createMule(?World $world = null) : Mule{
		$world ??= $this->createMockWorld();
		$location = new Location(100.0, 64.0, 100.0, $world, 0.0, 0.0);
		$nbt = new CompoundTag();
		$mule = new Mule($location, $nbt);

		$ref = new ReflectionClass(Entity::class);
		$lastLocProp = $ref->getProperty('lastLocation');
		$lastLocProp->setValue($mule, clone $location);
		$lastMotProp = $ref->getProperty('lastMotion');
		$lastMotProp->setValue($mule, Vector3::zero());

		$this->createdEntities[] = $mule;
		return $mule;
	}

	private function createMockPlayer(string $name = "Steve", ?string $uuid = null, ?Item $heldItem = null, bool $survival = true, ?World $world = null) : Player{
		$uuidObj = $uuid !== null ? Uuid::fromString($uuid) : Uuid::uuid4();
		$player = $this->getMockBuilder(Player::class)
			->disableOriginalConstructor()
			->onlyMethods(["getName", "getUniqueId", "isSneaking", "isCreative", "isSpectator", "getInventory", "hasFiniteResources", "getViewers", "canInteract", "broadcastSound", "broadcastAnimation", "setCurrentWindow", "isConnected", "isOnline"])
			->getMock();

		(new ReflectionProperty(Entity::class, "closed"))->setValue($player, false);
		(new ReflectionProperty(Entity::class, "id"))->setValue($player, 100);
		(new ReflectionProperty(Player::class, "logger"))->setValue($player, $this->createMock(Logger::class));
		(new ReflectionProperty(Living::class, "effectManager"))->setValue($player, new EffectManager($player));
		(new ReflectionProperty(Entity::class, "attributeMap"))->setValue($player, new AttributeMap());
		(new ReflectionClass(Player::class))->getMethod("addAttributes")->invoke($player);
		(new ReflectionProperty(Living::class, "armorInventory"))->setValue($player, new ArmorInventory($player));
		(new ReflectionProperty(Entity::class, "networkProperties"))->setValue($player, new EntityMetadataCollection());

		$world ??= $this->createMockWorld();
		$location = new Location(0.0, 0.0, 0.0, $world, 0.0, 0.0);
		(new ReflectionProperty(Entity::class, "location"))->setValue($player, $location);
		(new ReflectionProperty(Entity::class, "lastLocation"))->setValue($player, clone $location);
		(new ReflectionProperty(Entity::class, "motion"))->setValue($player, Vector3::zero());
		(new ReflectionProperty(Entity::class, "lastMotion"))->setValue($player, Vector3::zero());
		(new ReflectionProperty(Entity::class, "boundingBox"))->setValue($player, new AxisAlignedBB(-0.3, 0, -0.3, 0.3, 1.8, 0.3));

		$inventory = new PlayerInventory($player);
		if($heldItem !== null){
			$inventory->setItemInHand($heldItem);
		}
		(new ReflectionProperty(Human::class, "inventory"))->setValue($player, $inventory);
		(new ReflectionProperty(Human::class, "offHandInventory"))->setValue($player, new PlayerOffHandInventory($player));

		$player->method("getName")->willReturn($name);
		$player->method("getUniqueId")->willReturn($uuidObj);
		$player->method("isSneaking")->willReturn(false);
		$player->method("isCreative")->willReturn(!$survival);
		$player->method("isSpectator")->willReturn(false);
		$player->method("getInventory")->willReturn($inventory);
		$player->method("hasFiniteResources")->willReturn($survival);
		$player->method("getViewers")->willReturn([]);
		$player->method("canInteract")->willReturn(true);
		$player->method("setCurrentWindow")->willReturn(true);
		$player->method("isConnected")->willReturn(true);
		$player->method("isOnline")->willReturn(true);

		$this->createdEntities[] = $player;
		return $player;
	}

	public function testHorseDefaultsAndVariants() : void{
		$horse = $this->createHorse();
		self::assertSame(EntityIds::HORSE, Horse::getNetworkTypeId());
		self::assertSame("Horse", $horse->getName());
		self::assertGreaterThanOrEqual(15, $horse->getMaxHealth());
		self::assertLessThanOrEqual(30, $horse->getMaxHealth());
		self::assertGreaterThanOrEqual(0.4, $horse->getJumpStrength());
		self::assertLessThanOrEqual(1.0, $horse->getJumpStrength());
		self::assertFalse($horse->isTamed());
		self::assertFalse($horse->isSaddled());
		self::assertNull($horse->getRider());

		// Coat and markings
		$horse->setVariant(Horse::COAT_BLACK, Horse::MARKING_WHITE);
		self::assertSame(Horse::COAT_BLACK, $horse->getCoat());
		self::assertSame(Horse::MARKING_WHITE, $horse->getMarkings());
	}

	public function testHorseFeedingEffects() : void{
		$horse = $this->createHorse();
		$player = $this->createMockPlayer();

		// Baby horse growth
		$horse->setAge(-24000);
		self::assertTrue($horse->isBaby());

		$player->getInventory()->setItemInHand(VanillaItems::WHEAT());
		self::assertTrue($horse->onInteract($player, new Vector3(0, 0, 0)));
		self::assertSame(-23600, $horse->getAge());

		$player->getInventory()->setItemInHand(VanillaItems::GOLDEN_APPLE());
		self::assertTrue($horse->onInteract($player, new Vector3(0, 0, 0)));
		self::assertSame(-18800, $horse->getAge());

		// Adult horse temper increase
		$horse->setAge(0);
		self::assertSame(0, $horse->getTemper());
		$player->getInventory()->setItemInHand(VanillaItems::SUGAR());
		self::assertTrue($horse->onInteract($player, new Vector3(0, 0, 0)));
		self::assertSame(3, $horse->getTemper());

		$player->getInventory()->setItemInHand(VanillaItems::GOLDEN_CARROT());
		self::assertTrue($horse->onInteract($player, new Vector3(0, 0, 0)));
		self::assertSame(8, $horse->getTemper());
	}

	public function testHorseMountingAndTaming() : void{
		$horse = $this->createHorse();
		$player = $this->createMockPlayer("Rider");

		// Mount untamed horse with empty hand
		$player->getInventory()->setItemInHand(VanillaItems::AIR());
		self::assertTrue($horse->onInteract($player, new Vector3(0, 0, 0)));
		self::assertSame($player, $horse->getRider());

		// Tame horse
		self::assertTrue($horse->tame($player));
		self::assertTrue($horse->isTamed());
		self::assertSame($player->getUniqueId()->toString(), $horse->getOwnerUUID());
		self::assertSame("Rider", $horse->getOwnerName());
	}

	public function testHorseSaddleAndArmor() : void{
		$horse = $this->createHorse();
		$player = $this->createMockPlayer();
		$horse->setTamed(true);

		// Saddle
		$saddle = new Item(new ItemIdentifier(ItemTypeIds::newId()), "Saddle");
		$player->getInventory()->setItemInHand($saddle);
		self::assertTrue($horse->onInteract($player, new Vector3(0, 0, 0)));
		self::assertTrue($horse->isSaddled());

		// Armor
		$armor = new Item(new ItemIdentifier(ItemTypeIds::newId()), "Diamond Horse Armor");
		$player->getInventory()->setItemInHand($armor);
		self::assertTrue($horse->onInteract($player, new Vector3(0, 0, 0)));
		self::assertNotNull($horse->getArmor());
		self::assertSame("Diamond Horse Armor", $horse->getArmor()->getName());

		// Shears removes armor
		$shears = VanillaItems::SHEARS();
		$player->getInventory()->setItemInHand($shears);
		self::assertTrue($horse->onInteract($player, new Vector3(0, 0, 0)));
		self::assertNull($horse->getArmor());

		// Shears removes saddle
		self::assertTrue($horse->onInteract($player, new Vector3(0, 0, 0)));
		self::assertFalse($horse->isSaddled());
	}

	public function testHorseBreeding() : void{
		$horse1 = $this->createHorse();
		$horse1->setTamed(true);
		$horse1->setInLoveTicks(600);

		$horse2 = $this->createHorse();
		$horse2->setTamed(true);
		$horse2->setInLoveTicks(600);

		$baby = $horse1->breed($horse2);
		self::assertNotNull($baby);
		self::assertInstanceOf(Horse::class, $baby);
		self::assertTrue($baby->isBaby());
		self::assertSame(AbstractHorse::BREEDING_COOLDOWN_TICKS, $horse1->getBreedingCooldownTicks());
	}

	public function testHorsePlusDonkeyBreedsMule() : void{
		$horse = $this->createHorse();
		$horse->setTamed(true);
		$horse->setInLoveTicks(600);

		$donkey = $this->createDonkey();
		$donkey->setTamed(true);
		$donkey->setInLoveTicks(600);

		$baby = $horse->breed($donkey);
		self::assertNotNull($baby);
		self::assertInstanceOf(Mule::class, $baby);
		self::assertTrue($baby->isBaby());
		self::assertFalse($baby->canBreed());
	}

	public function testHorseNbtRoundtrip() : void{
		$horse = $this->createHorse();
		$uuid = Uuid::uuid4()->toString();
		$horse->setTamed(true);
		$horse->setOwnerUUID($uuid);
		$horse->setOwnerName("Cowboy");
		$horse->setTemper(75);
		$horse->setSaddled(true);
		$horse->setJumpStrength(0.85);
		$horse->setVariant(Horse::COAT_CHESTNUT, Horse::MARKING_WHITE_DOTS);

		$nbt = $horse->saveNBT();

		$loaded = $this->createHorse();
		$ref = new ReflectionClass(Horse::class);
		$readMethod = $ref->getMethod('readSaveData');
		$readMethod->invoke($loaded, $nbt);

		self::assertTrue($loaded->isTamed());
		self::assertSame($uuid, $loaded->getOwnerUUID());
		self::assertSame("Cowboy", $loaded->getOwnerName());
		self::assertSame(75, $loaded->getTemper());
		self::assertTrue($loaded->isSaddled());
		self::assertEqualsWithDelta(0.85, $loaded->getJumpStrength(), 0.01);
		self::assertSame(Horse::COAT_CHESTNUT, $loaded->getCoat());
		self::assertSame(Horse::MARKING_WHITE_DOTS, $loaded->getMarkings());
	}

	public function testDonkeyAndChestAttachment() : void{
		$donkey = $this->createDonkey();
		self::assertSame(EntityIds::DONKEY, Donkey::getNetworkTypeId());
		self::assertSame("Donkey", $donkey->getName());
		self::assertFalse($donkey->isChested());
		self::assertNull($donkey->getChestInventory());

		$player = $this->createMockPlayer();
		$chest = VanillaBlocks::CHEST()->asItem();

		// Untamed donkey rejects chest
		$player->getInventory()->setItemInHand($chest);
		self::assertFalse($donkey->isChested());

		// Tamed donkey accepts chest
		$donkey->setTamed(true);
		self::assertTrue($donkey->onInteract($player, new Vector3(0, 0, 0)));
		self::assertTrue($donkey->isChested());
		self::assertNotNull($donkey->getChestInventory());
		self::assertSame(15, $donkey->getChestInventory()->getSize());

		// Items can be stored
		$donkey->getChestInventory()->setItem(0, VanillaItems::DIAMOND());
		self::assertSame(ItemTypeIds::DIAMOND, $donkey->getChestInventory()->getItem(0)->getTypeId());

		// Drops include chest and diamond on death
		$drops = $donkey->getDrops();
		$hasChest = false;
		$hasDiamond = false;
		foreach($drops as $drop){
			if($drop->getTypeId() === VanillaBlocks::CHEST()->asItem()->getTypeId()){
				$hasChest = true;
			}
			if($drop->getTypeId() === ItemTypeIds::DIAMOND){
				$hasDiamond = true;
			}
		}
		self::assertTrue($hasChest);
		self::assertTrue($hasDiamond);
	}

	public function testDonkeyBreedingProducesDonkey() : void{
		$donkey1 = $this->createDonkey();
		$donkey1->setTamed(true);
		$donkey1->setInLoveTicks(600);

		$donkey2 = $this->createDonkey();
		$donkey2->setTamed(true);
		$donkey2->setInLoveTicks(600);

		$baby = $donkey1->breed($donkey2);
		self::assertNotNull($baby);
		self::assertInstanceOf(Donkey::class, $baby);
		self::assertTrue($baby->isBaby());
	}

	public function testMuleSterileAndChested() : void{
		$mule = $this->createMule();
		self::assertSame(EntityIds::MULE, Mule::getNetworkTypeId());
		self::assertSame("Mule", $mule->getName());
		self::assertFalse($mule->canBreed());

		$mule->setTamed(true);
		$mule->setChested(true);
		self::assertTrue($mule->isChested());
		self::assertNotNull($mule->getChestInventory());
		self::assertSame(15, $mule->getChestInventory()->getSize());
	}

	public function testChestedHorseNbtRoundtrip() : void{
		$donkey = $this->createDonkey();
		$donkey->setTamed(true);
		$donkey->setChested(true);
		$donkey->getChestInventory()->setItem(3, VanillaItems::EMERALD()->setCount(7));
		$donkey->getChestInventory()->setItem(7, VanillaItems::GOLD_INGOT()->setCount(12));

		$nbt = $donkey->saveNBT();

		$loaded = $this->createDonkey();
		$ref = new ReflectionClass(Donkey::class);
		$readMethod = $ref->getMethod('readSaveData');
		$readMethod->invoke($loaded, $nbt);

		self::assertTrue($loaded->isTamed());
		self::assertTrue($loaded->isChested());
		self::assertNotNull($loaded->getChestInventory());
		self::assertSame(7, $loaded->getChestInventory()->getItem(3)->getCount());
		self::assertSame(ItemTypeIds::EMERALD, $loaded->getChestInventory()->getItem(3)->getTypeId());
		self::assertSame(12, $loaded->getChestInventory()->getItem(7)->getCount());
		self::assertSame(ItemTypeIds::GOLD_INGOT, $loaded->getChestInventory()->getItem(7)->getTypeId());
	}
}
