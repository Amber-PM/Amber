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
use pocketmine\item\BoatType;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\network\mcpe\protocol\types\entity\Vec3MetadataProperty;
use pocketmine\world\World;
use ReflectionClass;
use ReflectionProperty;

class TestRiderEntity extends Living{
	public static function getNetworkTypeId() : string{ return "minecraft:zombie"; }
	public function getName() : string{ return "TestRider"; }
	public function isAlive() : bool{ return true; }
	protected function getInitialSizeInfo() : \pocketmine\entity\EntitySizeInfo{ return new \pocketmine\entity\EntitySizeInfo(1.8, 0.6); }
}

class BoatSeatingTest extends TestCase{

	private function createTestBoat(BoatType $type = BoatType::OAK) : Boat{
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);

		$boat = (new ReflectionClass(Boat::class))->newInstanceWithoutConstructor();

		(new ReflectionProperty(Entity::class, "attributeMap"))->setValue($boat, new AttributeMap());
		(new ReflectionProperty(Entity::class, "networkProperties"))->setValue($boat, new EntityMetadataCollection());
		(new ReflectionProperty(Boat::class, "boatType"))->setValue($boat, $type);

		$size = (new ReflectionClass(Boat::class))->getMethod("getInitialSizeInfo")->invoke($boat);
		(new ReflectionProperty(Entity::class, "size"))->setValue($boat, $size);

		$location = new Location(0.0, 10.0, 0.0, $world, 0.0, 0.0);
		(new ReflectionProperty(Entity::class, "location"))->setValue($boat, $location);
		(new ReflectionProperty(Entity::class, "boundingBox"))->setValue($boat, new AxisAlignedBB(-0.7, 10.0, -0.7, 0.7, 10.6, 0.7));
		(new ReflectionProperty(Entity::class, "motion"))->setValue($boat, Vector3::zero());

		(new ReflectionClass(Boat::class))->getMethod("initBoatProperties")->invoke($boat);
		(new ReflectionProperty(Entity::class, "id"))->setValue($boat, 100);

		return $boat;
	}

	private static int $riderId = 200;

	private function createTestRider(?World $world = null) : Living{
		$rider = (new ReflectionClass(TestRiderEntity::class))->newInstanceWithoutConstructor();
		(new ReflectionProperty(Entity::class, "id"))->setValue($rider, ++self::$riderId);
		(new ReflectionProperty(Entity::class, "networkProperties"))->setValue($rider, new EntityMetadataCollection());
		(new ReflectionProperty(Entity::class, "closed"))->setValue($rider, false);
		(new ReflectionProperty(Entity::class, "size"))->setValue($rider, new \pocketmine\entity\EntitySizeInfo(1.8, 0.6));
		(new ReflectionProperty(Entity::class, "boundingBox"))->setValue($rider, new AxisAlignedBB(-0.3, 10.0, -0.3, 0.3, 11.8, 0.3));
		(new ReflectionProperty(Living::class, "armorInventory"))->setValue($rider, new \pocketmine\inventory\ArmorInventory($rider));
		(new ReflectionProperty(Living::class, "effectManager"))->setValue($rider, new \pocketmine\entity\effect\EffectManager($rider));
		if($world === null){
			$world = $this->createMock(World::class);
			$world->method("isLoaded")->willReturn(true);
		}
		$loc = new Location(0.0, 10.0, 0.0, $world, 0.0, 0.0);
		(new ReflectionProperty(Entity::class, "location"))->setValue($rider, $loc);
		(new ReflectionProperty(Entity::class, "lastLocation"))->setValue($rider, clone $loc);
		$zeroMotion = new Vector3(0.0, 0.0, 0.0);
		(new ReflectionProperty(Entity::class, "motion"))->setValue($rider, $zeroMotion);
		(new ReflectionProperty(Entity::class, "lastMotion"))->setValue($rider, clone $zeroMotion);
		(new ReflectionProperty(Entity::class, "justCreated"))->setValue($rider, false);
		(new ReflectionProperty(Entity::class, "hasSpawned"))->setValue($rider, []);
		return $rider;
	}

	private function getGenericFlag(EntityMetadataCollection $flags, int $flagId) : bool{
		$propertyId = $flagId >= 64 ? EntityMetadataProperties::FLAGS2 : EntityMetadataProperties::FLAGS;
		$realFlagId = $flagId % 64;
		$prop = $flags->getAll()[$propertyId] ?? null;
		if($prop instanceof \pocketmine\network\mcpe\protocol\types\entity\LongMetadataProperty){
			return (($prop->getValue() >> $realFlagId) & 1) !== 0;
		}
		return false;
	}

	public function testCapacityAndMounting() : void{
		$boat = $this->createTestBoat();
		self::assertSame(2, $boat->getMaxRiders());
		self::assertFalse($boat->isFull());
		self::assertNull($boat->getDriver());
		self::assertNull($boat->getPassenger());

		$driver = $this->createTestRider();
		self::assertTrue($boat->canAddRider($driver));
		self::assertTrue($boat->addRider($driver));

		self::assertSame($driver, $boat->getDriver());
		self::assertNull($boat->getPassenger());
		self::assertFalse($boat->isFull());
		self::assertTrue($this->getGenericFlag($driver->getNetworkProperties(), EntityMetadataFlags::RIDING));

		$seat0Pos = $driver->getNetworkProperties()->getAll()[EntityMetadataProperties::RIDER_SEAT_POSITION];
		self::assertInstanceOf(Vec3MetadataProperty::class, $seat0Pos);

		$passenger = $this->createTestRider();
		self::assertTrue($boat->canAddRider($passenger));
		self::assertTrue($boat->addRider($passenger));

		self::assertSame($passenger, $boat->getPassenger());
		self::assertTrue($boat->isFull());

		$extraRider = $this->createTestRider();
		self::assertFalse($boat->canAddRider($extraRider));
		self::assertFalse($boat->addRider($extraRider));
	}

	public function testDismount() : void{
		$boat = $this->createTestBoat();
		$driver = $this->createTestRider();
		$passenger = $this->createTestRider();

		$boat->addRider($driver);
		$boat->addRider($passenger);
		self::assertTrue($boat->isFull());

		self::assertTrue($boat->removeRider($driver));
		self::assertNull($boat->getDriver());
		self::assertSame($passenger, $boat->getPassenger());
		self::assertFalse($boat->isFull());
		self::assertFalse($this->getGenericFlag($driver->getNetworkProperties(), EntityMetadataFlags::RIDING));

		// Now seat 0 is open, new rider should take seat 0
		$newDriver = $this->createTestRider();
		self::assertTrue($boat->addRider($newDriver));
		self::assertSame($newDriver, $boat->getDriver());
		self::assertSame($passenger, $boat->getPassenger());
	}

	public function testEjectRiders() : void{
		$boat = $this->createTestBoat();
		$driver = $this->createTestRider();
		$passenger = $this->createTestRider();

		$boat->addRider($driver);
		$boat->addRider($passenger);

		$boat->ejectRiders();
		self::assertEmpty($boat->getRiders());
		self::assertFalse($this->getGenericFlag($driver->getNetworkProperties(), EntityMetadataFlags::RIDING));
		self::assertFalse($this->getGenericFlag($passenger->getNetworkProperties(), EntityMetadataFlags::RIDING));
	}

	public function testVehicleTracking() : void{
		$boat = $this->createTestBoat();
		$driver = $this->createTestRider();

		self::assertNull(Boat::getVehicleOf($driver));
		$boat->addRider($driver);
		self::assertSame($boat, Boat::getVehicleOf($driver));
		self::assertFalse($boat->canAddRider($driver));

		$boat->removeRider($driver);
		self::assertNull(Boat::getVehicleOf($driver));

		$boat->addRider($driver);
		self::assertSame($boat, Boat::getVehicleOf($driver));
		$boat->ejectRiders();
		self::assertNull(Boat::getVehicleOf($driver));
	}

	public function testUpdateRidersCarriesNonPlayerEntity() : void{
		$boat = $this->createTestBoat();
		$passenger = $this->createTestRider($boat->getWorld());

		$boat->addRider($passenger);
		self::assertSame($passenger, $boat->getDriver());

		// Move boat to a new position
		$newLoc = new Location(2.0, 10.0, 3.0, $boat->getWorld(), 0.0, 0.0);
		(new ReflectionProperty(Entity::class, "location"))->setValue($boat, $newLoc);

		(new ReflectionClass(Boat::class))->getMethod("updateRiders")->invoke($boat);

		// Non-player rider should be teleported near boat's new location + seat offset
		$seat0Pos = $boat->getSeatPosition(0);
		self::assertEqualsWithDelta(2.0 + $seat0Pos->x, $passenger->getLocation()->x, 0.1);
		self::assertEqualsWithDelta(10.0 + $seat0Pos->y, $passenger->getLocation()->y, 0.1);
		self::assertEqualsWithDelta(3.0 + $seat0Pos->z, $passenger->getLocation()->z, 0.1);
	}

	public function testUpdateRidersRemovesDeadOrClosedRiders() : void{
		$boat = $this->createTestBoat();
		$driver = $this->createTestRider($boat->getWorld());
		$boat->addRider($driver);

		(new ReflectionProperty(Entity::class, "closed"))->setValue($driver, true);

		(new ReflectionClass(Boat::class))->getMethod("updateRiders")->invoke($boat);

		self::assertEmpty($boat->getRiders());
		self::assertNull(Boat::getVehicleOf($driver));
	}
}
