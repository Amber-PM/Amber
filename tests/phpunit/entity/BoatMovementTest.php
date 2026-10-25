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

use PHPUnit\Framework\TestCase;
use pocketmine\entity\vehicle\Boat;
use pocketmine\entity\vehicle\BoatType;
use pocketmine\entity\vehicle\ChestBoat;
use pocketmine\entity\vehicle\Vehicle;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\World;

final class BoatMovementTest extends TestCase{

	private function createMockPassenger(World $world) : Living{
		return new class(new Location(0, 64, 0, $world, 0, 0)) extends Living{
			public function getName() : string{ return "Zombie"; }
			public static function getNetworkTypeId() : string{ return "minecraft:zombie"; }
			protected function getInitialSizeInfo() : EntitySizeInfo{ return new EntitySizeInfo(1.8, 0.6); }
			protected function onDispose() : void{}
		};
	}

	public function testBoatDualPassengersAndRider() : void{
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);
		$loc = new Location(10, 64, 10, $world, 0, 0);

		$boat = new Boat($loc, BoatType::OAK);

		$p1 = $this->createMockPassenger($world);
		$p2 = $this->createMockPassenger($world);
		$p3 = $this->createMockPassenger($world);

		self::assertTrue($boat->canAddPassenger($p1));
		$boat->addPassenger($p1);

		self::assertSame($p1, $boat->getRider());
		self::assertTrue($boat->isPassenger($p1));

		self::assertTrue($boat->canAddPassenger($p2));
		$boat->addPassenger($p2);

		self::assertCount(2, $boat->getPassengers());
		// Third passenger should be rejected
		self::assertFalse($boat->canAddPassenger($p3));
		self::assertFalse($boat->addPassenger($p3));

		// Remove rider
		$boat->removePassenger($p1);
		self::assertFalse($boat->isPassenger($p1));
		self::assertTrue($boat->isPassenger($p2));
	}

	public function testBambooBoatIsRaft() : void{
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);
		$loc = new Location(0, 64, 0, $world, 0, 0);

		$oakBoat = new Boat($loc, BoatType::OAK);
		$bambooBoat = new Boat($loc, BoatType::BAMBOO);

		self::assertFalse($oakBoat->getBoatType()->isRaft());
		self::assertTrue($bambooBoat->getBoatType()->isRaft());
	}

	public function testChestBoatCapacityAndInventory() : void{
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);
		$loc = new Location(0, 64, 0, $world, 0, 0);

		$chestBoat = new ChestBoat($loc, BoatType::CHERRY);

		self::assertSame(1, $chestBoat->getMaxPassengers());
		self::assertSame(27, $chestBoat->getInventory()->getSize());
	}

	public function testPaddleRowingAcceleratesBoat() : void{
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);
		$loc = new Location(0, 64, 0, $world, 0, 0);

		$boat = new Boat($loc, BoatType::SPRUCE);
		$boat->setMotion(new Vector3(0, 0, 0));

		$rider = $this->createMockPassenger($world);
		$boat->addPassenger($rider);

		$boat->onPaddleTick(true, true);

		self::assertGreaterThan(0.01, $boat->getMotion()->length());
	}

	public function testBoatDamageAndCreativeInstantBreak() : void{
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);
		$loc = new Location(0, 64, 0, $world, 0, 0);

		$boat = new Boat($loc, BoatType::OAK);

		// Damage without breaking
		$ev = new \pocketmine\event\entity\EntityDamageEvent($boat, \pocketmine\event\entity\EntityDamageEvent::CAUSE_ENTITY_ATTACK, 10.0);
		$boat->attack($ev);
		self::assertSame(30.0, $boat->getHealth());
		self::assertFalse($boat->isFlaggedForDespawn());

		// Creative player instant break
		$player = new class(new Location(0, 64, 0, $world, 0, 0)) extends Player{
			public function __construct(Location $location){
				$this->id = Entity::nextRuntimeId();
				$this->location = $location;
				$this->networkProperties = new \pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection();
			}
			public function isCreative(bool $isSpectator = false) : bool{ return true; }
			public function hasFiniteResources() : bool{ return false; }
			protected function onDispose() : void{}
		};

		$creativeEv = $this->createMock(\pocketmine\event\entity\EntityDamageByEntityEvent::class);
		$creativeEv->method("getDamager")->willReturn($player);
		$creativeEv->method("isCancelled")->willReturn(false);
		$creativeEv->method("getFinalDamage")->willReturn(1.0);

		$boat->attack($creativeEv);
		self::assertTrue($boat->isFlaggedForDespawn());
	}

	public function testAllBoatAndChestBoatVariantsDropItems() : void{
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);
		$loc = new Location(0, 64, 0, $world, 0, 0);

		foreach(BoatType::cases() as $type){
			$boat = new Boat($loc, $type);
			$drop = $boat->getDropItem();
			self::assertNotNull($drop, "Boat drop for type " . $type->value . " should not be null");

			$chestBoat = new ChestBoat($loc, $type);
			$chestDrop = $chestBoat->getDropItem();
			self::assertNotNull($chestDrop, "ChestBoat drop for type " . $type->value . " should not be null");
		}
	}

	public function testBoatTypeNBTSerialization() : void{
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);
		$loc = new Location(0, 64, 0, $world, 0, 0);

		$boat = new Boat($loc, BoatType::CHERRY);
		$nbt = $boat->saveNBT();
		self::assertSame("cherry", $nbt->getString(Boat::TAG_TYPE));

		$loadedBoat = new Boat($loc, BoatType::OAK, $nbt);
		self::assertSame(BoatType::CHERRY, $loadedBoat->getBoatType());
	}

	public function testVehicleWeakMapPassengerDismountOnNewVehicle() : void{
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);
		$loc = new Location(0, 64, 0, $world, 0, 0);

		$boat1 = new Boat($loc, BoatType::OAK);
		$boat2 = new Boat($loc, BoatType::BIRCH);

		$passenger = $this->createMockPassenger($world);

		self::assertTrue($boat1->addPassenger($passenger));
		self::assertTrue($boat1->isPassenger($passenger));
		self::assertSame($boat1, Vehicle::getVehicleOf($passenger));

		// Adding to second vehicle must dismount from first
		self::assertTrue($boat2->addPassenger($passenger));
		self::assertFalse($boat1->isPassenger($passenger));
		self::assertTrue($boat2->isPassenger($passenger));
		self::assertSame($boat2, Vehicle::getVehicleOf($passenger));
	}
}
