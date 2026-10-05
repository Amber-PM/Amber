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

namespace pocketmine\block;

use PHPUnit\Framework\TestCase;
use pocketmine\block\inventory\DispenserInventory;
use pocketmine\block\inventory\DropperInventory;
use pocketmine\block\tile\Dispenser as TileDispenser;
use pocketmine\block\tile\Dropper as TileDropper;
use pocketmine\item\VanillaItems;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\world\Position;
use pocketmine\world\redstone\RedstoneEngine;
use pocketmine\world\World;

final class DispenserRedstoneTest extends TestCase{

	/**
	 * @param array<string, Block> $blocks
	 * @return array{RedstoneEngine, World&\PHPUnit\Framework\MockObject\MockObject}
	 */
	private function createEnvironment(array &$blocks) : array{
		$world = $this->getMockBuilder(World::class)->disableOriginalConstructor()->onlyMethods([
			"getBlockAt",
			"setBlockAt",
			"setBlock",
			"getTile",
			"isInWorld",
			"isChunkLoaded",
			"isLoaded",
			"dropItem",
			"notifyNeighbourBlockUpdate"
		])->getMock();

		$world->method("isInWorld")->willReturn(true);
		$world->method("isChunkLoaded")->willReturn(true);
		$world->method("isLoaded")->willReturn(true);
		$world->method("dropItem")->willReturn(null);

		$engine = new RedstoneEngine($world, 100000);

		$world->method("getBlockAt")->willReturnCallback(function(int $x, int $y, int $z) use (&$blocks, $world) : Block{
			$key = "$x:$y:$z";
			if(isset($blocks[$key])){
				return $blocks[$key];
			}
			$air = clone VanillaBlocks::AIR();
			$air->position($world, $x, $y, $z);
			return $air;
		});

		$world->method("setBlock")->willReturnCallback(function(Vector3 $pos, Block $b) use (&$blocks) : bool{
			$key = "{$pos->x}:{$pos->y}:{$pos->z}";
			$blocks[$key] = $b;
			return true;
		});

		return [$engine, $world];
	}

	public function testDispenserRisingEdgeTrigger() : void{
		$blocks = [];
		[$engine, $world] = $this->createEnvironment($blocks);

		$dispenser = VanillaBlocks::DISPENSER();
		$dispenser->setFacing(Facing::NORTH);
		$pos = new Position(10, 20, 30, $world);
		$dispenser->position($world, 10, 20, 30);
		$blocks["10:20:30"] = $dispenser;

		$inventory = new DispenserInventory($pos);
		$inventory->setItem(0, VanillaItems::DIAMOND()->setCount(5));

		$tile = $this->createMock(TileDispenser::class);
		$tile->method("getInventory")->willReturn($inventory);

		$world->method("getTile")->willReturnCallback(function(Vector3 $p) use ($pos, $tile) : ?TileDispenser{
			if($p->equals($pos)){
				return $tile;
			}
			return null;
		});

		// 1. Initial unpowered state
		self::assertFalse($dispenser->isPowered());
		$dispenser->onRedstoneUpdate($engine);
		self::assertFalse($dispenser->isPowered());
		self::assertSame(5, $inventory->getItem(0)->getCount());

		// 2. Rising edge: Redstone block placed adjacent at (10, 21, 30)
		$redstoneBlock = VanillaBlocks::REDSTONE();
		$redstoneBlock->position($world, 10, 21, 30);
		$blocks["10:21:30"] = $redstoneBlock;

		$dispenser->onRedstoneUpdate($engine);
		self::assertTrue($dispenser->isPowered());
		self::assertSame(4, $inventory->getItem(0)->getCount());

		// 3. High sustain: redstone block still present, does not re-trigger
		$dispenser->onRedstoneUpdate($engine);
		self::assertSame(4, $inventory->getItem(0)->getCount());

		// 4. Falling edge: redstone block removed (air)
		unset($blocks["10:21:30"]);
		$dispenser->onRedstoneUpdate($engine);
		self::assertFalse($dispenser->isPowered());
		self::assertSame(4, $inventory->getItem(0)->getCount());

		// 5. Rising edge again: triggers second dispense
		$blocks["10:21:30"] = $redstoneBlock;
		$dispenser->onRedstoneUpdate($engine);
		self::assertTrue($dispenser->isPowered());
		self::assertSame(3, $inventory->getItem(0)->getCount());
	}

	public function testDropperRisingEdgeTrigger() : void{
		$blocks = [];
		[$engine, $world] = $this->createEnvironment($blocks);

		$dropper = VanillaBlocks::DROPPER();
		$dropper->setFacing(Facing::NORTH);
		$pos = new Position(10, 20, 30, $world);
		$dropper->position($world, 10, 20, 30);
		$blocks["10:20:30"] = $dropper;

		$inventory = new DropperInventory($pos);
		$inventory->setItem(0, VanillaItems::EMERALD()->setCount(3));

		$tile = $this->createMock(TileDropper::class);
		$tile->method("getInventory")->willReturn($inventory);

		$world->method("getTile")->willReturnCallback(function(Vector3 $p) use ($pos, $tile) : ?TileDropper{
			if($p->equals($pos)){
				return $tile;
			}
			return null;
		});

		// 1. Initial unpowered state
		self::assertFalse($dropper->isPowered());
		$dropper->onRedstoneUpdate($engine);
		self::assertFalse($dropper->isPowered());
		self::assertSame(3, $inventory->getItem(0)->getCount());

		// 2. Rising edge: Redstone block placed adjacent at (10, 21, 30)
		$redstoneBlock = VanillaBlocks::REDSTONE();
		$redstoneBlock->position($world, 10, 21, 30);
		$blocks["10:21:30"] = $redstoneBlock;

		$dropper->onRedstoneUpdate($engine);
		self::assertTrue($dropper->isPowered());
		self::assertSame(2, $inventory->getItem(0)->getCount());

		// 3. High sustain: redstone block still present, does not re-trigger
		$dropper->onRedstoneUpdate($engine);
		self::assertSame(2, $inventory->getItem(0)->getCount());

		// 4. Falling edge: redstone block removed
		unset($blocks["10:21:30"]);
		$dropper->onRedstoneUpdate($engine);
		self::assertFalse($dropper->isPowered());
		self::assertSame(2, $inventory->getItem(0)->getCount());

		// 5. Rising edge again: triggers second drop
		$blocks["10:21:30"] = $redstoneBlock;
		$dropper->onRedstoneUpdate($engine);
		self::assertTrue($dropper->isPowered());
		self::assertSame(1, $inventory->getItem(0)->getCount());
	}

	public function testDispenserRedstonePowerWithSingleBoneMealFacingCrop() : void{
		$blocks = [];
		[$engine, $world] = $this->createEnvironment($blocks);

		$pos = new Vector3(10, 20, 30);
		$dispenser = VanillaBlocks::DISPENSER();
		$dispenser->setFacing(Facing::NORTH);
		$dispenser->position($world, 10, 20, 30);
		$blocks["10:20:30"] = $dispenser;

		$wheat = VanillaBlocks::WHEAT();
		$wheat->position($world, 10, 20, 29);
		$blocks["10:20:29"] = $wheat;

		$inventory = new DispenserInventory(new Position(10, 20, 30, $world));
		$inventory->setItem(0, VanillaItems::BONE_MEAL()); // Single-item stack (count 1)
		self::assertSame(1, $inventory->getItem(0)->getCount());

		$tile = $this->createMock(TileDispenser::class);
		$tile->method("getInventory")->willReturn($inventory);

		$world->method("getTile")->willReturnCallback(function(Vector3 $p) use ($pos, $tile) : ?TileDispenser{
			if($p->equals($pos)){
				return $tile;
			}
			return null;
		});

		// Rising edge: Redstone block placed adjacent at (10, 21, 30)
		$redstoneBlock = VanillaBlocks::REDSTONE();
		$redstoneBlock->position($world, 10, 21, 30);
		$blocks["10:21:30"] = $redstoneBlock;

		// Powering the dispenser must not throw InvalidArgumentException out of the redstone tick
		$dispenser->onRedstoneUpdate($engine);

		self::assertTrue($dispenser->isPowered());
		self::assertTrue($inventory->getItem(0)->isNull());
		self::assertInstanceOf(Crops::class, $blocks["10:20:29"]);
		self::assertGreaterThan(0, $blocks["10:20:29"]->getAge());
	}
}
