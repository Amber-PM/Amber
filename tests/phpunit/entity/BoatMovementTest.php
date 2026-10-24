<?php

/*
 *
 *     _             _               
 *    / \\   _ __ ___ | |__   ___ _ __ 
 *   / _ \\ | '_ ` _ \\| '_ \\ / _ \\ '__|
 *  / ___ \\| | | | | | |_) |  __/ |   
 * /_/   \\_\\_| |_| |_|_.__/ \\___|_|   
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
use pocketmine\math\Vector3;
use pocketmine\world\World;

final class BoatMovementTest extends TestCase{

	public function testBoatDualPassengersAndRider() : void{
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);
		$loc = new Location(10, 64, 10, $world, 0, 0);

		$boat = new Boat($loc, BoatType::OAK);

		$p1 = $this->createMock(Living::class);
		$p1->method("isAlive")->willReturn(true);
		$p1->method("isClosed")->willReturn(false);

		$p2 = $this->createMock(Living::class);
		$p2->method("isAlive")->willReturn(true);
		$p2->method("isClosed")->willReturn(false);

		$p3 = $this->createMock(Living::class);
		$p3->method("isAlive")->willReturn(true);
		$p3->method("isClosed")->willReturn(false);

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

		$rider = $this->createMock(Living::class);
		$rider->method("isAlive")->willReturn(true);
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
		$player = $this->createMock(\pocketmine\player\Player::class);
		$player->method("isCreative")->willReturn(true);

		$creativeEv = new \pocketmine\event\entity\EntityDamageByEntityEvent($player, $boat, \pocketmine\event\entity\EntityDamageEvent::CAUSE_ENTITY_ATTACK, 1.0);
		$boat->attack($creativeEv);
		self::assertTrue($boat->isFlaggedForDespawn());
	}
}
