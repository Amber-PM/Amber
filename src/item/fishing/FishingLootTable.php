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

use pocketmine\block\VanillaBlocks;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\enchantment\VanillaEnchantments;
use pocketmine\item\Item;
use pocketmine\item\PotionType;
use pocketmine\item\VanillaItems;
use function array_values;
use function count;
use function max;
use function mt_rand;
use function shuffle;

final class FishingLootTable{

	public const CATEGORY_FISH = 0;
	public const CATEGORY_TREASURE = 1;
	public const CATEGORY_JUNK = 2;

	private function __construct(){
		// NOOP
	}

	public static function rollLoot(int $luckOfSeaLevel = 0) : Item{
		// BDS fishing.json:
		// fish: weight 85, quality -1
		// treasure: weight 5, quality 2
		// junk: weight 10, quality -2
		$fishWeight = max(1, 85 - $luckOfSeaLevel);
		$treasureWeight = 5 + (2 * $luckOfSeaLevel);
		$junkWeight = max(1, 10 - (2 * $luckOfSeaLevel));

		$total = $fishWeight + $treasureWeight + $junkWeight;
		$roll = mt_rand(1, $total);

		if($roll <= $fishWeight){
			return self::rollFish();
		}

		if($roll <= $fishWeight + $treasureWeight){
			return self::rollTreasure();
		}

		return self::rollJunk();
	}

	public static function rollFish() : Item{
		// BDS fish.json:
		// raw fish: 60%
		// raw salmon: 25%
		// clownfish: 2%
		// pufferfish: 13%
		$roll = mt_rand(1, 100);
		if($roll <= 60){
			return VanillaItems::RAW_FISH();
		}
		if($roll <= 85){
			return VanillaItems::RAW_SALMON();
		}
		if($roll <= 87){
			return VanillaItems::CLOWNFISH();
		}
		return VanillaItems::PUFFERFISH();
	}

	public static function rollTreasure() : Item{
		// BDS treasure.json:
		// nautilus_shell: 5
		// name_tag: 5
		// bow: 5 (damage 0-25%, enchanted level 30)
		// fishing_rod: 5 (damage 0-25%, enchanted level 30)
		// book: 6 (enchanted level 30)
		$roll = mt_rand(1, 26);
		if($roll <= 5){
			return VanillaItems::NAUTILUS_SHELL();
		}
		if($roll <= 10){
			return VanillaItems::NAME_TAG();
		}
		if($roll <= 15){
			$bow = VanillaItems::BOW();
			$bow->setDamage((int) ((mt_rand(0, 25) / 100.0) * $bow->getMaxDurability()));
			self::applyRandomEnchantments($bow);
			return $bow;
		}
		if($roll <= 20){
			$rod = VanillaItems::FISHING_ROD();
			$rod->setDamage((int) ((mt_rand(0, 25) / 100.0) * $rod->getMaxDurability()));
			self::applyRandomEnchantments($rod);
			return $rod;
		}

		$book = VanillaItems::ENCHANTED_BOOK();
		self::applyRandomEnchantments($book);
		return $book;
	}

	public static function rollJunk() : Item{
		// BDS junk.json:
		// leather_boots: 10 (0-90% damage)
		// waterlily: 17
		// ink_sac: 1 (count 10)
		// leather: 10
		// bone: 10
		// potion (water bottle): 10
		// string: 5
		// fishing_rod: 2 (0-90% damage)
		// bowl: 10
		// stick: 5
		// tripwire_hook: 10
		// rotten_flesh: 10
		$roll = mt_rand(1, 100);
		if($roll <= 10){
			$boots = VanillaItems::LEATHER_BOOTS();
			$boots->setDamage((int) ((mt_rand(0, 90) / 100.0) * $boots->getMaxDurability()));
			return $boots;
		}
		if($roll <= 27){
			return VanillaBlocks::LILY_PAD()->asItem();
		}
		if($roll <= 28){
			return VanillaItems::INK_SAC()->setCount(10);
		}
		if($roll <= 38){
			return VanillaItems::LEATHER();
		}
		if($roll <= 48){
			return VanillaItems::BONE();
		}
		if($roll <= 58){
			return VanillaItems::POTION()->setType(PotionType::WATER);
		}
		if($roll <= 63){
			return VanillaItems::STRING();
		}
		if($roll <= 65){
			$rod = VanillaItems::FISHING_ROD();
			$rod->setDamage((int) ((mt_rand(0, 90) / 100.0) * $rod->getMaxDurability()));
			return $rod;
		}
		if($roll <= 75){
			return VanillaItems::BOWL();
		}
		if($roll <= 80){
			return VanillaItems::STICK();
		}
		if($roll <= 90){
			return VanillaBlocks::TRIPWIRE_HOOK()->asItem();
		}
		return VanillaItems::ROTTEN_FLESH();
	}

	private static function applyRandomEnchantments(Item $item) : void{
		$all = array_values(VanillaEnchantments::getAll());
		if(count($all) > 0){
			shuffle($all);
			$count = mt_rand(1, 3);
			for($i = 0; $i < $count && $i < count($all); $i++){
				$e = $all[$i];
				$item->addEnchantment(new EnchantmentInstance($e, mt_rand(1, $e->getMaxLevel())));
			}
		}
	}
}
