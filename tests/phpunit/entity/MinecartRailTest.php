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
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\vehicle\ChestMinecart;
use pocketmine\entity\vehicle\HopperMinecart;
use pocketmine\entity\vehicle\Minecart;
use pocketmine\entity\vehicle\TNTMinecart;
use pocketmine\math\Vector3;
use pocketmine\world\World;

final class MinecartRailTest extends TestCase{

	public function testPoweredRailAcceleratesAndBrakesMinecart() : void{
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);
		$loc = new Location(0, 64, 0, $world, 0, 0);

		$cart = new Minecart($loc);
		$cart->setMotion(new Vector3(0.1, 0, 0));

		// 1. Powered rail with redstone power ON
		$poweredRailOn = VanillaBlocks::POWERED_RAIL()->setPowered(true);
		$cart->onRailTick($poweredRailOn);
		self::assertGreaterThan(0.1, $cart->getMotion()->x);

		// 2. Powered rail with redstone power OFF (braking)
		$poweredRailOff = VanillaBlocks::POWERED_RAIL()->setPowered(false);
		$speedBefore = $cart->getMotion()->x;
		$cart->onRailTick($poweredRailOff);
		self::assertLessThan($speedBefore, $cart->getMotion()->x);
	}

	public function testActivatorRailDismountsRider() : void{
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);
		$loc = new Location(0, 64, 0, $world, 0, 0);

		$cart = new Minecart($loc);
		$rider = $this->createMock(Living::class);
		$rider->method("isAlive")->willReturn(true);

		$cart->addPassenger($rider);
		self::assertSame($rider, $cart->getRider());

		// Trigger active activator rail
		$activatorRail = VanillaBlocks::ACTIVATOR_RAIL()->setPowered(true);
		$cart->onRailTick($activatorRail);

		self::assertNull($cart->getRider());
	}

	public function testTNTMinecartPrimesOnActivatorRail() : void{
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);
		$loc = new Location(0, 64, 0, $world, 0, 0);

		$tntCart = new TNTMinecart($loc);
		self::assertFalse($tntCart->isPrimed());

		$activatorRail = VanillaBlocks::ACTIVATOR_RAIL()->setPowered(true);
		$tntCart->onRailTick($activatorRail);

		self::assertTrue($tntCart->isPrimed());
	}

	public function testSpecialMinecartInventories() : void{
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);
		$loc = new Location(0, 64, 0, $world, 0, 0);

		$chestCart = new ChestMinecart($loc);
		self::assertSame(27, $chestCart->getInventory()->getSize());
		self::assertSame(0, $chestCart->getMaxPassengers());

		$hopperCart = new HopperMinecart($loc);
		self::assertSame(5, $hopperCart->getInventory()->getSize());
		self::assertSame(0, $hopperCart->getMaxPassengers());
	}
}
