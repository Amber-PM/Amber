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

namespace pocketmine\item\fishing;

use PHPUnit\Framework\TestCase;
use pocketmine\item\Item;

final class FishingLootTableTest extends TestCase{

	public function testRollFish() : void{
		for($i = 0; $i < 50; $i++){
			$item = FishingLootTable::rollFish();
			self::assertInstanceOf(Item::class, $item);
			self::assertGreaterThan(0, $item->getCount());
			self::assertFalse($item->isNull());
		}
	}

	public function testRollTreasure() : void{
		for($i = 0; $i < 50; $i++){
			$item = FishingLootTable::rollTreasure();
			self::assertInstanceOf(Item::class, $item);
			self::assertGreaterThan(0, $item->getCount());
			self::assertFalse($item->isNull());
		}
	}

	public function testRollJunk() : void{
		for($i = 0; $i < 50; $i++){
			$item = FishingLootTable::rollJunk();
			self::assertInstanceOf(Item::class, $item);
			self::assertGreaterThan(0, $item->getCount());
			self::assertFalse($item->isNull());
		}
	}

	public function testRollLootWithLuck() : void{
		for($i = 0; $i < 50; $i++){
			$item = FishingLootTable::rollLoot(3);
			self::assertInstanceOf(Item::class, $item);
			self::assertGreaterThan(0, $item->getCount());
			self::assertFalse($item->isNull());
		}
	}
}
