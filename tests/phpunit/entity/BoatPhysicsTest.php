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
use pocketmine\entity\effect\EffectManager;
use pocketmine\entity\Entity;
use pocketmine\entity\Living;
use pocketmine\entity\Location;
use pocketmine\entity\object\Boat;
use pocketmine\inventory\ArmorInventory;
use pocketmine\item\BoatType;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\player\Player;
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
		(new ReflectionProperty(Entity::class, "hasSpawned"))->setValue($boat, []);
		(new ReflectionProperty(Entity::class, "gravityEnabled"))->setValue($boat, true);
		(new ReflectionProperty(Entity::class, "drag"))->setValue($boat, 0.05);
		(new ReflectionProperty(Entity::class, "gravity"))->setValue($boat, 0.04);

		$server = (new ReflectionClass(\pocketmine\Server::class))->newInstanceWithoutConstructor();
		(new ReflectionProperty(\pocketmine\Server::class, "tickCounter"))->setValue($server, 100);
		$world->method("getServer")->willReturn($server);
		(new ReflectionProperty(Entity::class, "server"))->setValue($boat, $server);

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

	private static int $playerId = 200;

	private function createTestPlayer(World $world) : Player{
		$player = $this->getMockBuilder(Player::class)
			->disableOriginalConstructor()
			->onlyMethods(["isConnected", "teleport", "onDispose"])
			->getMock();
		$player->method("isConnected")->willReturn(false);
		$player->method("teleport")->willReturn(true);

		(new ReflectionProperty(Entity::class, "id"))->setValue($player, ++self::$playerId);
		(new ReflectionProperty(Entity::class, "networkProperties"))->setValue($player, new EntityMetadataCollection());
		(new ReflectionProperty(Entity::class, "closed"))->setValue($player, false);
		(new ReflectionProperty(Player::class, "logger"))->setValue($player, $this->createMock(\Logger::class));
		(new ReflectionProperty(Living::class, "armorInventory"))->setValue($player, new ArmorInventory($player));
		(new ReflectionProperty(Living::class, "effectManager"))->setValue($player, new EffectManager($player));
		$loc = new Location(0.0, 10.0, 0.0, $world, 0.0, 0.0);
		(new ReflectionProperty(Entity::class, "location"))->setValue($player, $loc);
		(new ReflectionProperty(Entity::class, "lastLocation"))->setValue($player, clone $loc);
		$zero = new Vector3(0.0, 0.0, 0.0);
		(new ReflectionProperty(Entity::class, "motion"))->setValue($player, $zero);
		(new ReflectionProperty(Entity::class, "lastMotion"))->setValue($player, clone $zero);
		(new ReflectionProperty(Entity::class, "justCreated"))->setValue($player, false);
		(new ReflectionProperty(Entity::class, "hasSpawned"))->setValue($player, []);
		(new ReflectionProperty(Entity::class, "server"))->setValue($player, $world->getServer());

		return $player;
	}

	public function testRiderInputAcceleratesBoat() : void{
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);
		$world->method("getBlockAt")->willReturnCallback(function(int $x, int $y, int $z){
			if($y >= 10){
				return VanillaBlocks::AIR();
			}
			return VanillaBlocks::WATER();
		});

		$boat = $this->createTestBoat($world, new Vector3(0.0, 0.0, 0.0), 10.0);
		$player = $this->createTestPlayer($world);

		$boat->addRider($player);
		self::assertSame($boat, Boat::getVehicleOf($player));
		self::assertSame($player, $boat->getDriver());

		// Send forward input
		Boat::setRiderInput($player, 0.0, 1.0, true, true, 0.0);

		// Verify paddle time incremented
		self::assertGreaterThan(0.0, $boat->getPaddleTime(0));
		self::assertGreaterThan(0.0, $boat->getPaddleTime(1));

		// Tick movement
		$tryChangeMovement = new ReflectionMethod(Boat::class, "tryChangeMovement");
		$tryChangeMovement->invoke($boat);

		// With yaw 0.0 and forward 1.0, dx = 0, dz = 1.0, motion.z should increase by 0.1
		self::assertGreaterThan(0.05, $boat->getMotion()->z);

		// Clean up vehicle
		$boat->removeRider($player);
		(new ReflectionProperty(Entity::class, "closed"))->setValue($player, true);
		self::assertNull(Boat::getVehicleOf($player));
	}

	public function testRiderSteeringTurnsBoat() : void{
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);
		$world->method("getBlockAt")->willReturnCallback(function(int $x, int $y, int $z){
			return VanillaBlocks::AIR();
		});

		$boat = $this->createTestBoat($world, new Vector3(0.0, 0.0, 0.0), 10.0);
		$player = $this->createTestPlayer($world);

		$boat->addRider($player);

		// Left paddle only should turn yaw by +2.0
		Boat::setRiderInput($player, 0.0, 0.0, true, false);
		$initialYaw = $boat->getLocation()->yaw;

		$tryChangeMovement = new ReflectionMethod(Boat::class, "tryChangeMovement");
		$tryChangeMovement->invoke($boat);

		self::assertEqualsWithDelta(fmod($initialYaw + 2.0, 360), $boat->getLocation()->yaw, 0.01);

		// Clean up
		$boat->removeRider($player);
		(new ReflectionProperty(Entity::class, "closed"))->setValue($player, true);
	}
}
