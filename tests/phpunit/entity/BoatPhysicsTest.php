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
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\AttributeMap;
use pocketmine\entity\Entity;
use pocketmine\entity\Location;
use pocketmine\entity\object\Boat;
use pocketmine\item\BoatType;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\world\World;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

class BoatPhysicsTest extends TestCase{

	private function createTestBoat(World $world, Vector3 $initialMotion, float $y = 10.0) : Boat{
		$boat = (new ReflectionClass(Boat::class))->newInstanceWithoutConstructor();

		(new ReflectionProperty(Entity::class, "id"))->setValue($boat, 100);
		(new ReflectionProperty(Entity::class, "closed"))->setValue($boat, false);
		(new ReflectionProperty(Entity::class, "attributeMap"))->setValue($boat, new AttributeMap());
		(new ReflectionProperty(Entity::class, "networkProperties"))->setValue($boat, new EntityMetadataCollection());
		(new ReflectionProperty(Boat::class, "boatType"))->setValue($boat, BoatType::OAK);

		$size = (new ReflectionClass(Boat::class))->getMethod("getInitialSizeInfo")->invoke($boat);
		(new ReflectionProperty(Entity::class, "size"))->setValue($boat, $size);

		$location = new Location(0.0, $y, 0.0, $world, 0.0, 0.0);
		(new ReflectionProperty(Entity::class, "location"))->setValue($boat, $location);
		(new ReflectionProperty(Entity::class, "boundingBox"))->setValue($boat, new AxisAlignedBB(-0.7, $y, -0.7, 0.7, $y + 0.6, 0.7));
		(new ReflectionProperty(Entity::class, "motion"))->setValue($boat, clone $initialMotion);
		(new ReflectionProperty(Entity::class, "lastMotion"))->setValue($boat, clone $initialMotion);
		(new ReflectionProperty(Entity::class, "justCreated"))->setValue($boat, false);
		(new ReflectionProperty(Entity::class, "gravityEnabled"))->setValue($boat, true);
		(new ReflectionProperty(Entity::class, "drag"))->setValue($boat, 0.05);
		(new ReflectionProperty(Entity::class, "gravity"))->setValue($boat, 0.04);

		(new ReflectionClass(Boat::class))->getMethod("initBoatProperties")->invoke($boat);

		return $boat;
	}

	public function testSubmergedBuoyancy() : void{
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);
		$world->method("getBlockAt")->willReturnCallback(function(int $x, int $y, int $z){
			if($y >= 10){
				return VanillaBlocks::WATER();
			}
			return VanillaBlocks::WATER();
		});

		// Boat is submerged at y = 10.0 (water surface is 11.0)
		$boat = $this->createTestBoat($world, new Vector3(0.0, 0.0, 0.0), 10.0);
		(new ReflectionProperty(Entity::class, "fallDistance"))->setValue($boat, 5.0);

		$tryChangeMovement = new ReflectionMethod(Boat::class, "tryChangeMovement");
		$tryChangeMovement->invoke($boat);

		// Upward buoyant motion should be positive
		self::assertGreaterThan(0.0, $boat->getMotion()->y);
		self::assertSame(0.0, $boat->getFallDistance());
		self::assertTrue($boat->isOnGround());
	}

	public function testBubbleColumnWhirlpoolDownward() : void{
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);
		$world->method("getBlockAt")->willReturnCallback(function(int $x, int $y, int $z){
			if($y === 10){
				return VanillaBlocks::WATER();
			}
			if($y === 8){
				return VanillaBlocks::MAGMA();
			}
			return VanillaBlocks::WATER();
		});

		$boat = $this->createTestBoat($world, new Vector3(0.0, 0.0, 0.0), 10.0);
		$tryChangeMovement = new ReflectionMethod(Boat::class, "tryChangeMovement");
		$tryChangeMovement->invoke($boat);

		self::assertEqualsWithDelta(-0.3, $boat->getMotion()->y, 0.001);
		self::assertGreaterThan(0, $boat->getBubbleTime());
	}

	public function testBubbleColumnUpward() : void{
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);
		$world->method("getBlockAt")->willReturnCallback(function(int $x, int $y, int $z){
			if($y === 10){
				return VanillaBlocks::WATER();
			}
			if($y === 7){
				return VanillaBlocks::SOUL_SAND();
			}
			return VanillaBlocks::WATER();
		});

		$boat = $this->createTestBoat($world, new Vector3(0.0, 0.0, 0.0), 10.0);
		$tryChangeMovement = new ReflectionMethod(Boat::class, "tryChangeMovement");
		$tryChangeMovement->invoke($boat);

		self::assertEqualsWithDelta(0.25, $boat->getMotion()->y, 0.001);
		self::assertGreaterThan(0, $boat->getBubbleTime());
	}

	public function testIceGlidingFriction() : void{
		// 1. Blue Ice: friction = 0.989
		$worldBlueIce = $this->createMock(World::class);
		$worldBlueIce->method("isLoaded")->willReturn(true);
		$worldBlueIce->method("getBlockAt")->willReturnCallback(function(int $x, int $y, int $z){
			if($y === 10){
				return VanillaBlocks::AIR();
			}
			return VanillaBlocks::BLUE_ICE();
		});

		$boatBlueIce = $this->createTestBoat($worldBlueIce, new Vector3(1.0, 0.0, 0.0), 10.0);
		(new ReflectionProperty(Entity::class, "onGround"))->setValue($boatBlueIce, true);

		$tryChangeMovement = new ReflectionMethod(Boat::class, "tryChangeMovement");
		$tryChangeMovement->invoke($boatBlueIce);
		self::assertEqualsWithDelta(0.989, $boatBlueIce->getMotion()->x, 0.001);

		// 2. Packed Ice: friction = 0.98
		$worldPackedIce = $this->createMock(World::class);
		$worldPackedIce->method("isLoaded")->willReturn(true);
		$worldPackedIce->method("getBlockAt")->willReturnCallback(function(int $x, int $y, int $z){
			if($y === 10){
				return VanillaBlocks::AIR();
			}
			return VanillaBlocks::PACKED_ICE();
		});

		$boatPackedIce = $this->createTestBoat($worldPackedIce, new Vector3(1.0, 0.0, 0.0), 10.0);
		(new ReflectionProperty(Entity::class, "onGround"))->setValue($boatPackedIce, true);
		$tryChangeMovement->invoke($boatPackedIce);
		self::assertEqualsWithDelta(0.98, $boatPackedIce->getMotion()->x, 0.001);

		// 3. Land (Stone): friction = 0.6
		$worldStone = $this->createMock(World::class);
		$worldStone->method("isLoaded")->willReturn(true);
		$worldStone->method("getBlockAt")->willReturnCallback(function(int $x, int $y, int $z){
			if($y === 10){
				return VanillaBlocks::AIR();
			}
			return VanillaBlocks::STONE();
		});

		$boatStone = $this->createTestBoat($worldStone, new Vector3(1.0, 0.0, 0.0), 10.0);
		(new ReflectionProperty(Entity::class, "onGround"))->setValue($boatStone, true);
		$tryChangeMovement->invoke($boatStone);
		self::assertEqualsWithDelta(0.6, $boatStone->getMotion()->x, 0.001);
	}

	public function testPaddleAnimationAdvance() : void{
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);

		$boat = $this->createTestBoat($world, new Vector3(0.5, 0.0, 0.0), 10.0);
		self::assertEqualsWithDelta(0.0, $boat->getPaddleTime(0), 0.001);
		self::assertEqualsWithDelta(0.0, $boat->getPaddleTime(1), 0.001);

		$entityBaseTick = new ReflectionMethod(Boat::class, "entityBaseTick");
		$entityBaseTick->invoke($boat, 1);

		self::assertGreaterThan(0.0, $boat->getPaddleTime(0));
		self::assertGreaterThan(0.0, $boat->getPaddleTime(1));

		$props = $boat->getNetworkProperties()->getAll();
		self::assertArrayHasKey(EntityMetadataProperties::PADDLE_TIME_LEFT, $props);
		self::assertArrayHasKey(EntityMetadataProperties::PADDLE_TIME_RIGHT, $props);
	}
}
