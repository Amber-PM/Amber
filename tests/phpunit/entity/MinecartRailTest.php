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
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\vehicle\ChestMinecart;
use pocketmine\entity\vehicle\HopperMinecart;
use pocketmine\entity\vehicle\Minecart;
use pocketmine\entity\vehicle\TNTMinecart;
use pocketmine\math\Vector3;
use pocketmine\world\World;

final class MinecartRailTest extends TestCase{

	private function createMockPassenger(World $world) : Living{
		return new class(new Location(0, 64, 0, $world, 0, 0)) extends Living{
			public function getName() : string{ return "Zombie"; }
			public static function getNetworkTypeId() : string{ return "minecraft:zombie"; }
			protected function getInitialSizeInfo() : EntitySizeInfo{ return new EntitySizeInfo(1.8, 0.6); }
			protected function onDispose() : void{}
		};
	}

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
		$rider = $this->createMockPassenger($world);

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

	public function testCompositeVehicleDrops() : void{
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);
		$loc = new Location(0, 64, 0, $world, 0, 0);

		$chestCart = new ChestMinecart($loc);
		$chestDrops = $chestCart->getDrops();
		self::assertCount(2, $chestDrops);

		$hopperCart = new HopperMinecart($loc);
		$hopperDrops = $hopperCart->getDrops();
		self::assertCount(2, $hopperDrops);

		$tntCart = new TNTMinecart($loc);
		$tntDrops = $tntCart->getDrops();
		self::assertCount(2, $tntDrops);
	}

	public function testTNTMinecartFuseSerialization() : void{
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);
		$loc = new Location(0, 64, 0, $world, 0, 0);

		$tntCart = new TNTMinecart($loc);
		$tntCart->prime(55);
		$nbt = $tntCart->saveNBT();
		self::assertSame(55, $nbt->getShort(TNTMinecart::TAG_TNT_FUSE));

		$loadedCart = new TNTMinecart($loc, $nbt);
		self::assertTrue($loadedCart->isPrimed());
	}

	public function testChestMinecartCargoNBTSerialization() : void{
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);
		$loc = new Location(0, 64, 0, $world, 0, 0);

		$chestCart = new ChestMinecart($loc);
		$item = \pocketmine\item\VanillaItems::DIAMOND()->setCount(5);
		$chestCart->getInventory()->setItem(3, $item);

		$nbt = $chestCart->saveNBT();
		self::assertTrue($nbt->getTag(ChestMinecart::TAG_ITEMS) !== null);

		$loadedCart = new ChestMinecart($loc, $nbt);
		$loadedItem = $loadedCart->getInventory()->getItem(3);
		self::assertSame($item->getTypeId(), $loadedItem->getTypeId());
		self::assertSame(5, $loadedItem->getCount());
	}
}
