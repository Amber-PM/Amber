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

namespace pocketmine\world\hopper;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use pocketmine\block\Block;
use pocketmine\block\inventory\DispenserInventory;
use pocketmine\block\inventory\DropperInventory;
use pocketmine\block\inventory\HopperInventory;
use pocketmine\block\tile\Dispenser as TileDispenser;
use pocketmine\block\tile\Dropper as TileDropper;
use pocketmine\block\tile\Hopper as HopperTile;
use pocketmine\block\tile\Tile;
use pocketmine\block\VanillaBlocks;
use pocketmine\item\VanillaItems;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\world\Position;
use pocketmine\world\World;

final class HopperTickerTest extends TestCase{

	/**
	 * @param array<string, Tile> $tiles
	 * @return World&MockObject
	 */
	private function createWorldMock(array &$tiles, ?Block $hopperBlock = null) : World&MockObject{
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);
		$world->method("isChunkLoaded")->willReturn(true);
		$world->method("getNearbyEntities")->willReturn([]);

		$world->method("getTileAt")->willReturnCallback(function(int $x, int $y, int $z) use (&$tiles) : ?Tile{
			return $tiles["$x,$y,$z"] ?? null;
		});

		$world->method("getBlockAt")->willReturnCallback(function(int $x, int $y, int $z) use ($hopperBlock) : mixed{
			if($hopperBlock !== null && $x === 10 && $y === 20 && $z === 30){
				return $hopperBlock;
			}
			return VanillaBlocks::AIR();
		});

		return $world;
	}

	public function testHopperPushesIntoDispenser() : void{
		$hopperPos = new Vector3(10, 20, 30);
		$dispenserPos = $hopperPos->getSide(Facing::EAST);

		$hopperBlock = VanillaBlocks::HOPPER();
		$hopperBlock->setFacing(Facing::EAST);

		$tiles = [];
		$world = $this->createWorldMock($tiles, $hopperBlock);
		$hopperBlock->position($world, 10, 20, 30);

		$hopperInv = new HopperInventory(new Position(10, 20, 30, $world));
		$hopperInv->setItem(0, VanillaItems::DIAMOND()->setCount(5));

		$dispenserInv = new DispenserInventory(new Position((int) $dispenserPos->x, (int) $dispenserPos->y, (int) $dispenserPos->z, $world));

		$hopperTile = $this->createMock(HopperTile::class);
		$hopperTile->method("getPosition")->willReturn(new Position(10, 20, 30, $world));
		$hopperTile->method("getInventory")->willReturn($hopperInv);

		$dispenserTile = $this->createMock(TileDispenser::class);
		$dispenserTile->method("getPosition")->willReturn(new Position((int) $dispenserPos->x, (int) $dispenserPos->y, (int) $dispenserPos->z, $world));
		$dispenserTile->method("getInventory")->willReturn($dispenserInv);

		$tiles["10,20,30"] = $hopperTile;
		$tiles["11,20,30"] = $dispenserTile;

		$ticker = new HopperTicker($world);
		$ticker->onTileAdded($hopperTile);

		$ticker->tick(8);

		self::assertSame(1, $ticker->getMovedCount());
		self::assertSame(4, $hopperInv->getItem(0)->getCount());
		self::assertSame(1, $dispenserInv->getItem(0)->getCount());
		self::assertSame(VanillaItems::DIAMOND()->getTypeId(), $dispenserInv->getItem(0)->getTypeId());
	}

	public function testHopperPushesIntoDropper() : void{
		$hopperPos = new Vector3(10, 20, 30);
		$dropperPos = $hopperPos->getSide(Facing::EAST);

		$hopperBlock = VanillaBlocks::HOPPER();
		$hopperBlock->setFacing(Facing::EAST);

		$tiles = [];
		$world = $this->createWorldMock($tiles, $hopperBlock);
		$hopperBlock->position($world, 10, 20, 30);

		$hopperInv = new HopperInventory(new Position(10, 20, 30, $world));
		$hopperInv->setItem(0, VanillaItems::EMERALD()->setCount(3));

		$dropperInv = new DropperInventory(new Position((int) $dropperPos->x, (int) $dropperPos->y, (int) $dropperPos->z, $world));

		$hopperTile = $this->createMock(HopperTile::class);
		$hopperTile->method("getPosition")->willReturn(new Position(10, 20, 30, $world));
		$hopperTile->method("getInventory")->willReturn($hopperInv);

		$dropperTile = $this->createMock(TileDropper::class);
		$dropperTile->method("getPosition")->willReturn(new Position((int) $dropperPos->x, (int) $dropperPos->y, (int) $dropperPos->z, $world));
		$dropperTile->method("getInventory")->willReturn($dropperInv);

		$tiles["10,20,30"] = $hopperTile;
		$tiles["11,20,30"] = $dropperTile;

		$ticker = new HopperTicker($world);
		$ticker->onTileAdded($hopperTile);

		$ticker->tick(8);

		self::assertSame(1, $ticker->getMovedCount());
		self::assertSame(2, $hopperInv->getItem(0)->getCount());
		self::assertSame(1, $dropperInv->getItem(0)->getCount());
		self::assertSame(VanillaItems::EMERALD()->getTypeId(), $dropperInv->getItem(0)->getTypeId());
	}

	public function testHopperExtractsFromDispenserAbove() : void{
		$hopperBlock = VanillaBlocks::HOPPER();
		$hopperBlock->setFacing(Facing::DOWN);

		$tiles = [];
		$world = $this->createWorldMock($tiles, $hopperBlock);
		$hopperBlock->position($world, 10, 20, 30);

		$hopperInv = new HopperInventory(new Position(10, 20, 30, $world));

		$dispenserInv = new DispenserInventory(new Position(10, 21, 30, $world));
		$dispenserInv->setItem(2, VanillaItems::IRON_INGOT()->setCount(7));

		$hopperTile = $this->createMock(HopperTile::class);
		$hopperTile->method("getPosition")->willReturn(new Position(10, 20, 30, $world));
		$hopperTile->method("getInventory")->willReturn($hopperInv);

		$dispenserTile = $this->createMock(TileDispenser::class);
		$dispenserTile->method("getPosition")->willReturn(new Position(10, 21, 30, $world));
		$dispenserTile->method("getInventory")->willReturn($dispenserInv);

		$tiles["10,20,30"] = $hopperTile;
		$tiles["10,21,30"] = $dispenserTile;

		$ticker = new HopperTicker($world);
		$ticker->onTileAdded($hopperTile);

		$ticker->tick(8);

		self::assertSame(1, $ticker->getMovedCount());
		self::assertSame(6, $dispenserInv->getItem(2)->getCount());
		self::assertSame(1, $hopperInv->getItem(0)->getCount());
		self::assertSame(VanillaItems::IRON_INGOT()->getTypeId(), $hopperInv->getItem(0)->getTypeId());
	}

	public function testHopperExtractsFromDropperAbove() : void{
		$hopperBlock = VanillaBlocks::HOPPER();
		$hopperBlock->setFacing(Facing::DOWN);

		$tiles = [];
		$world = $this->createWorldMock($tiles, $hopperBlock);
		$hopperBlock->position($world, 10, 20, 30);

		$hopperInv = new HopperInventory(new Position(10, 20, 30, $world));

		$dropperInv = new DropperInventory(new Position(10, 21, 30, $world));
		$dropperInv->setItem(4, VanillaItems::GOLD_INGOT()->setCount(4));

		$hopperTile = $this->createMock(HopperTile::class);
		$hopperTile->method("getPosition")->willReturn(new Position(10, 20, 30, $world));
		$hopperTile->method("getInventory")->willReturn($hopperInv);

		$dropperTile = $this->createMock(TileDropper::class);
		$dropperTile->method("getPosition")->willReturn(new Position(10, 21, 30, $world));
		$dropperTile->method("getInventory")->willReturn($dropperInv);

		$tiles["10,20,30"] = $hopperTile;
		$tiles["10,21,30"] = $dropperTile;

		$ticker = new HopperTicker($world);
		$ticker->onTileAdded($hopperTile);

		$ticker->tick(8);

		self::assertSame(1, $ticker->getMovedCount());
		self::assertSame(3, $dropperInv->getItem(4)->getCount());
		self::assertSame(1, $hopperInv->getItem(0)->getCount());
		self::assertSame(VanillaItems::GOLD_INGOT()->getTypeId(), $hopperInv->getItem(0)->getTypeId());
	}

	public function testHopperDoesNotPushIntoFullDispenser() : void{
		$hopperPos = new Vector3(10, 20, 30);
		$dispenserPos = $hopperPos->getSide(Facing::EAST);

		$hopperBlock = VanillaBlocks::HOPPER();
		$hopperBlock->setFacing(Facing::EAST);

		$tiles = [];
		$world = $this->createWorldMock($tiles, $hopperBlock);
		$hopperBlock->position($world, 10, 20, 30);

		$hopperInv = new HopperInventory(new Position(10, 20, 30, $world));
		$hopperInv->setItem(0, VanillaItems::DIAMOND()->setCount(5));

		$dispenserInv = new DispenserInventory(new Position((int) $dispenserPos->x, (int) $dispenserPos->y, (int) $dispenserPos->z, $world));
		for($i = 0; $i < 9; ++$i){
			$dispenserInv->setItem($i, VanillaBlocks::DIRT()->asItem()->setCount(64));
		}

		$hopperTile = $this->createMock(HopperTile::class);
		$hopperTile->method("getPosition")->willReturn(new Position(10, 20, 30, $world));
		$hopperTile->method("getInventory")->willReturn($hopperInv);

		$dispenserTile = $this->createMock(TileDispenser::class);
		$dispenserTile->method("getPosition")->willReturn(new Position((int) $dispenserPos->x, (int) $dispenserPos->y, (int) $dispenserPos->z, $world));
		$dispenserTile->method("getInventory")->willReturn($dispenserInv);

		$tiles["10,20,30"] = $hopperTile;
		$tiles["11,20,30"] = $dispenserTile;

		$ticker = new HopperTicker($world);
		$ticker->onTileAdded($hopperTile);

		$ticker->tick(8);

		self::assertSame(0, $ticker->getMovedCount());
		self::assertSame(5, $hopperInv->getItem(0)->getCount());
	}

	public function testHopperDoesNotPushIntoFullDropper() : void{
		$hopperPos = new Vector3(10, 20, 30);
		$dropperPos = $hopperPos->getSide(Facing::EAST);

		$hopperBlock = VanillaBlocks::HOPPER();
		$hopperBlock->setFacing(Facing::EAST);

		$tiles = [];
		$world = $this->createWorldMock($tiles, $hopperBlock);
		$hopperBlock->position($world, 10, 20, 30);

		$hopperInv = new HopperInventory(new Position(10, 20, 30, $world));
		$hopperInv->setItem(0, VanillaItems::DIAMOND()->setCount(5));

		$dropperInv = new DropperInventory(new Position((int) $dropperPos->x, (int) $dropperPos->y, (int) $dropperPos->z, $world));
		for($i = 0; $i < 9; ++$i){
			$dropperInv->setItem($i, VanillaBlocks::DIRT()->asItem()->setCount(64));
		}

		$hopperTile = $this->createMock(HopperTile::class);
		$hopperTile->method("getPosition")->willReturn(new Position(10, 20, 30, $world));
		$hopperTile->method("getInventory")->willReturn($hopperInv);

		$dropperTile = $this->createMock(TileDropper::class);
		$dropperTile->method("getPosition")->willReturn(new Position((int) $dropperPos->x, (int) $dropperPos->y, (int) $dropperPos->z, $world));
		$dropperTile->method("getInventory")->willReturn($dropperInv);

		$tiles["10,20,30"] = $hopperTile;
		$tiles["11,20,30"] = $dropperTile;

		$ticker = new HopperTicker($world);
		$ticker->onTileAdded($hopperTile);

		$ticker->tick(8);

		self::assertSame(0, $ticker->getMovedCount());
		self::assertSame(5, $hopperInv->getItem(0)->getCount());
	}

	public function testHopperExtractsNothingFromEmptyDispenser() : void{
		$hopperBlock = VanillaBlocks::HOPPER();
		$hopperBlock->setFacing(Facing::DOWN);

		$tiles = [];
		$world = $this->createWorldMock($tiles, $hopperBlock);
		$hopperBlock->position($world, 10, 20, 30);

		$hopperInv = new HopperInventory(new Position(10, 20, 30, $world));
		$dispenserInv = new DispenserInventory(new Position(10, 21, 30, $world));

		$hopperTile = $this->createMock(HopperTile::class);
		$hopperTile->method("getPosition")->willReturn(new Position(10, 20, 30, $world));
		$hopperTile->method("getInventory")->willReturn($hopperInv);

		$dispenserTile = $this->createMock(TileDispenser::class);
		$dispenserTile->method("getPosition")->willReturn(new Position(10, 21, 30, $world));
		$dispenserTile->method("getInventory")->willReturn($dispenserInv);

		$tiles["10,20,30"] = $hopperTile;
		$tiles["10,21,30"] = $dispenserTile;

		$ticker = new HopperTicker($world);
		$ticker->onTileAdded($hopperTile);

		$ticker->tick(8);

		self::assertSame(0, $ticker->getMovedCount());
		self::assertTrue($hopperInv->getItem(0)->isNull());
	}

	public function testHopperExtractsNothingFromEmptyDropper() : void{
		$hopperBlock = VanillaBlocks::HOPPER();
		$hopperBlock->setFacing(Facing::DOWN);

		$tiles = [];
		$world = $this->createWorldMock($tiles, $hopperBlock);
		$hopperBlock->position($world, 10, 20, 30);

		$hopperInv = new HopperInventory(new Position(10, 20, 30, $world));
		$dropperInv = new DropperInventory(new Position(10, 21, 30, $world));

		$hopperTile = $this->createMock(HopperTile::class);
		$hopperTile->method("getPosition")->willReturn(new Position(10, 20, 30, $world));
		$hopperTile->method("getInventory")->willReturn($hopperInv);

		$dropperTile = $this->createMock(TileDropper::class);
		$dropperTile->method("getPosition")->willReturn(new Position(10, 21, 30, $world));
		$dropperTile->method("getInventory")->willReturn($dropperInv);

		$tiles["10,20,30"] = $hopperTile;
		$tiles["10,21,30"] = $dropperTile;

		$ticker = new HopperTicker($world);
		$ticker->onTileAdded($hopperTile);

		$ticker->tick(8);

		self::assertSame(0, $ticker->getMovedCount());
		self::assertTrue($hopperInv->getItem(0)->isNull());
	}
}
