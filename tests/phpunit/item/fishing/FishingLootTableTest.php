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
use pocketmine\item\Bow;
use pocketmine\item\Durable;
use pocketmine\item\enchantment\AvailableEnchantmentRegistry;
use pocketmine\item\FishingRod;
use pocketmine\item\Item;
use pocketmine\lang\Translatable;

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

	public function testTreasureDurabilityAndEnchantments() : void{
		for($i = 0; $i < 100; $i++){
			$item = FishingLootTable::rollTreasure();
			if($item instanceof Bow || $item instanceof FishingRod){
				$max = $item->getMaxDurability();
				$damage = $item->getDamage();
				// Remaining fraction is 0% to 25%, meaning damage is 75% to 100% of max durability
				self::assertGreaterThanOrEqual((int) round($max * 0.75), $damage);
				self::assertLessThanOrEqual($max, $damage);
			}
			if($item instanceof Bow){
				foreach($item->getEnchantments() as $enchantmentInstance){
					$type = $enchantmentInstance->getType();
					$typeName = $type->getName();
					$nameStr = $typeName instanceof Translatable ? $typeName->getText() : $typeName;
					self::assertTrue(
						AvailableEnchantmentRegistry::getInstance()->isAvailableForItem($type, $item),
						"Enchantment $nameStr is not applicable to Bow"
					);
				}
			}
		}
	}

	public function testJunkDurability() : void{
		for($i = 0; $i < 100; $i++){
			$item = FishingLootTable::rollJunk();
			if($item instanceof Durable && $item->getMaxDurability() > 0){
				$max = $item->getMaxDurability();
				$damage = $item->getDamage();
				// Remaining fraction is 0% to 90%, meaning damage is 10% to 100% of max durability
				self::assertGreaterThanOrEqual((int) round($max * 0.10), $damage);
				self::assertLessThanOrEqual($max, $damage);
			}
		}
	}
}
