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

namespace pocketmine\tests\entity;

use PHPUnit\Framework\TestCase;
use pocketmine\entity\AttributeMap;
use pocketmine\entity\Entity;
use pocketmine\entity\Living;
use pocketmine\entity\Location;
use pocketmine\entity\object\Boat;
use pocketmine\entity\object\ChestBoat;
use pocketmine\inventory\Inventory;
use pocketmine\item\BoatType;
use pocketmine\item\VanillaItems;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\player\Player;
use pocketmine\world\World;
use ReflectionClass;
use ReflectionProperty;

class TestBoatPlayer extends Player{
	public bool $sneaking = false;
	public ?Inventory $currentWindow = null;

	public function isSneaking() : bool{ return $this->sneaking; }
	public function isAlive() : bool{ return true; }
	public function setCurrentWindow(Inventory $inventory) : bool{
		$this->currentWindow = $inventory;
		return true;
	}
	public function isConnected() : bool{ return false; }
	public function teleport(Vector3 $pos, ?float $yaw = null, ?float $pitch = null) : bool{ return true; }
	protected function onDispose() : void{
		// no-op
	}
	public function __destruct(){
		// no-op to prevent uninitialized mock destruction
	}
}

class ChestBoatTest extends TestCase{

	private function createTestChestBoat(BoatType $type = BoatType::OAK, ?World $world = null) : ChestBoat{
		if($world === null){
			$world = $this->createMock(World::class);
			$world->method("isLoaded")->willReturn(true);
		}

		$boat = (new ReflectionClass(ChestBoat::class))->newInstanceWithoutConstructor();

		(new ReflectionProperty(Entity::class, "id"))->setValue($boat, 100);
		(new ReflectionProperty(Entity::class, "closed"))->setValue($boat, false);
		(new ReflectionProperty(Entity::class, "attributeMap"))->setValue($boat, new AttributeMap());
		(new ReflectionProperty(Entity::class, "networkProperties"))->setValue($boat, new EntityMetadataCollection());
		(new ReflectionProperty(Boat::class, "boatType"))->setValue($boat, $type);

		$size = (new ReflectionClass(ChestBoat::class))->getMethod("getInitialSizeInfo")->invoke($boat);
		(new ReflectionProperty(Entity::class, "size"))->setValue($boat, $size);

		$location = new Location(0.0, 10.0, 0.0, $world, 0.0, 0.0);
		(new ReflectionProperty(Entity::class, "location"))->setValue($boat, $location);
		(new ReflectionProperty(Entity::class, "boundingBox"))->setValue($boat, new AxisAlignedBB(-0.7, 10.0, -0.7, 0.7, 10.6, 0.7));
		(new ReflectionProperty(Entity::class, "motion"))->setValue($boat, Vector3::zero());

		(new ReflectionClass(ChestBoat::class))->getMethod("initBoatProperties")->invoke($boat);

		return $boat;
	}

	private static int $riderId = 300;

	private function createTestPlayer(bool $sneaking = false, ?World $world = null) : TestBoatPlayer{
		if($world === null){
			$world = $this->createMock(World::class);
			$world->method("isLoaded")->willReturn(true);
		}

		$player = (new ReflectionClass(TestBoatPlayer::class))->newInstanceWithoutConstructor();
		$player->sneaking = $sneaking;
		(new ReflectionProperty(Entity::class, "id"))->setValue($player, ++self::$riderId);
		(new ReflectionProperty(Entity::class, "closed"))->setValue($player, false);
		(new ReflectionProperty(Entity::class, "networkProperties"))->setValue($player, new EntityMetadataCollection());
		(new ReflectionProperty(Entity::class, "size"))->setValue($player, new \pocketmine\entity\EntitySizeInfo(1.8, 0.6));
		(new ReflectionProperty(Entity::class, "boundingBox"))->setValue($player, new AxisAlignedBB(-0.3, 10.0, -0.3, 0.3, 11.8, 0.3));
		(new ReflectionProperty(Living::class, "armorInventory"))->setValue($player, new \pocketmine\inventory\ArmorInventory($player));
		(new ReflectionProperty(Living::class, "effectManager"))->setValue($player, new \pocketmine\entity\effect\EffectManager($player));

		$loc = new Location(0.0, 10.0, 0.0, $world, 0.0, 0.0);
		(new ReflectionProperty(Entity::class, "location"))->setValue($player, $loc);
		(new ReflectionProperty(Entity::class, "lastLocation"))->setValue($player, clone $loc);
		$zeroMotion = new Vector3(0.0, 0.0, 0.0);
		(new ReflectionProperty(Entity::class, "motion"))->setValue($player, $zeroMotion);
		(new ReflectionProperty(Entity::class, "lastMotion"))->setValue($player, clone $zeroMotion);
		(new ReflectionProperty(Entity::class, "justCreated"))->setValue($player, false);
		(new ReflectionProperty(Entity::class, "hasSpawned"))->setValue($player, []);

		return $player;
	}

	public function testCapacityAndMaxRiders() : void{
		$boat = $this->createTestChestBoat();
		self::assertSame(1, $boat->getMaxRiders());
		self::assertSame("minecraft:chest_boat", ChestBoat::getNetworkTypeId());
		self::assertSame(27, $boat->getInventory()->getSize());

		$player1 = $this->createTestPlayer();
		self::assertTrue($boat->canAddRider($player1));
		self::assertTrue($boat->addRider($player1));
		self::assertTrue($boat->isFull());

		$player2 = $this->createTestPlayer();
		self::assertFalse($boat->canAddRider($player2));
		self::assertFalse($boat->addRider($player2));
	}

	public function testInventoryStorage() : void{
		$boat = $this->createTestChestBoat();
		$diamond = VanillaItems::DIAMOND()->setCount(5);
		$boat->getInventory()->setItem(0, $diamond);

		self::assertTrue($diamond->equalsExact($boat->getInventory()->getItem(0)));
		self::assertTrue($boat->getInventory()->getItem(1)->isNull());

		$boat->getInventory()->clearAll();
		self::assertTrue($boat->getInventory()->getItem(0)->isNull());
	}

	public function testNbtPersistence() : void{
		$boat = $this->createTestChestBoat(BoatType::CHERRY);
		$boat->getInventory()->setItem(0, VanillaItems::DIAMOND()->setCount(3));
		$boat->getInventory()->setItem(15, VanillaItems::GOLD_INGOT()->setCount(7));

		$nbt = CompoundTag::create();
		(new ReflectionClass(ChestBoat::class))->getMethod("writeSaveData")->invoke($boat, $nbt);

		$itemsTag = $nbt->getListTag(ChestBoat::TAG_ITEMS);
		self::assertNotNull($itemsTag);
		self::assertCount(2, $itemsTag);

		$loadedBoat = $this->createTestChestBoat(BoatType::OAK);
		(new ReflectionClass(ChestBoat::class))->getMethod("readSaveData")->invoke($loadedBoat, $nbt);

		self::assertSame(BoatType::CHERRY, $loadedBoat->getBoatType());
		self::assertSame(3, $loadedBoat->getInventory()->getItem(0)->getCount());
		self::assertTrue(VanillaItems::DIAMOND()->equals($loadedBoat->getInventory()->getItem(0)));
		self::assertSame(7, $loadedBoat->getInventory()->getItem(15)->getCount());
		self::assertTrue(VanillaItems::GOLD_INGOT()->equals($loadedBoat->getInventory()->getItem(15)));
		self::assertTrue($loadedBoat->getInventory()->getItem(1)->isNull());
	}

	public function testDestructionDropsInventoryAndBoatItem() : void{
		$droppedItems = [];
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);
		$world->method("dropItem")->willReturnCallback(function(Vector3 $pos, \pocketmine\item\Item $item) use (&$droppedItems){
			$droppedItems[] = $item;
			return null;
		});

		$boat = $this->createTestChestBoat(BoatType::MANGROVE, $world);
		$boat->getInventory()->setItem(0, VanillaItems::APPLE()->setCount(2));
		$boat->getInventory()->setItem(5, VanillaItems::EMERALD()->setCount(10));

		(new ReflectionClass(ChestBoat::class))->getMethod("destroyBoat")->invoke($boat);

		self::assertTrue($boat->isClosed());
		self::assertCount(3, $droppedItems); // 2 inventory slots + 1 chest boat item

		$itemTypes = array_map(fn($item) => $item->getName(), $droppedItems);
		self::assertContains("Apple", $itemTypes);
		self::assertContains("Emerald", $itemTypes);
		self::assertContains("Mangrove Boat with Chest", $itemTypes);
	}

	public function testInteractions() : void{
		$boat = $this->createTestChestBoat();

		// 1. Normal interact when empty mounts as driver
		$player = $this->createTestPlayer(false);
		$interacted = $boat->onInteract($player, Vector3::zero());
		self::assertTrue($interacted);
		self::assertSame($player, $boat->getDriver());
		self::assertNull($player->currentWindow);

		// 2. Normal interact when full (has driver) opens inventory for outside player
		$outsidePlayer = $this->createTestPlayer(false);
		$interacted2 = $boat->onInteract($outsidePlayer, Vector3::zero());
		self::assertTrue($interacted2);
		self::assertSame($boat->getInventory(), $outsidePlayer->currentWindow);

		// 3. Sneak interact while riding dismounts
		$boat2 = $this->createTestChestBoat();
		$driver = $this->createTestPlayer(true);
		$boat2->addRider($driver);
		self::assertSame($driver, $boat2->getDriver());

		$dismountResult = $boat2->onInteract($driver, Vector3::zero());
		self::assertTrue($dismountResult);
		self::assertNull($boat2->getDriver());

		// 4. Sneak interact from outside opens inventory
		$sneakingOutsidePlayer = $this->createTestPlayer(true);
		$interacted3 = $boat2->onInteract($sneakingOutsidePlayer, Vector3::zero());
		self::assertTrue($interacted3);
		self::assertSame($boat2->getInventory(), $sneakingOutsidePlayer->currentWindow);
	}

	public function testCreativeAttackDropsStoredItemsWithoutDroppingBoatItem() : void{
		$droppedItems = [];
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);
		$world->method("dropItem")->willReturnCallback(function(Vector3 $pos, \pocketmine\item\Item $item) use (&$droppedItems){
			$droppedItems[] = $item;
			return null;
		});

		$player = $this->createMock(Player::class);
		$player->method("isCreative")->willReturn(true);
		(new ReflectionProperty(Entity::class, "closed"))->setValue($player, true);
		(new ReflectionProperty(Player::class, "logger"))->setValue($player, $this->createMock(\Logger::class));

		$boat = $this->createTestChestBoat(BoatType::OAK, $world);
		$boat->getInventory()->setItem(0, VanillaItems::DIAMOND()->setCount(12));

		$attackEvent = $this->createMock(\pocketmine\event\entity\EntityDamageByEntityEvent::class);
		$attackEvent->method("getDamager")->willReturn($player);
		$attackEvent->method("isCancelled")->willReturn(false);

		$boat->attack($attackEvent);

		self::assertTrue($boat->isClosed());
		self::assertCount(1, $droppedItems);
		self::assertSame("Diamond", $droppedItems[0]->getName());
		self::assertSame(12, $droppedItems[0]->getCount());
	}
}
