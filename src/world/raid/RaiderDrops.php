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

namespace pocketmine\world\raid;

use pocketmine\item\Durable;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\enchantment\VanillaEnchantments;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use function mt_rand;

/**
 * Handles raider drop generation according to BDS loot_tables/entities/raider_drops.json.
 */
final class RaiderDrops{

	private function __construct(){
		//NOOP
	}

	/**
	 * @return Item[]
	 */
	public static function getDrops(int $lootingLvl = 0, int $difficulty = 2) : array{
		$drops = [];

		// Pool 1: 0-1 emerald + looting
		$baseEmeralds = mt_rand(0, 1 + $lootingLvl);
		if($baseEmeralds > 0){
			$drops[] = VanillaItems::EMERALD()->setCount($baseEmeralds);
		}

		// Pool 2: Difficulty roll (easy/normal: 65%, hard: 80%)
		$chance = $difficulty >= 3 ? 80 : 65;
		if(mt_rand(1, 100) <= $chance){
			$roll = mt_rand(1, 100);
			if($roll <= 40){
				// 0-1 emeralds
				$count = mt_rand(0, 1 + $lootingLvl);
				if($count > 0){
					$drops[] = VanillaItems::EMERALD()->setCount($count);
				}
			}elseif($roll <= 60){
				// 2-3 emeralds
				$drops[] = VanillaItems::EMERALD()->setCount(mt_rand(2, 3 + $lootingLvl));
			}elseif($roll <= 68){
				// 4-5 emeralds
				$drops[] = VanillaItems::EMERALD()->setCount(mt_rand(4, 5 + $lootingLvl));
			}elseif($roll <= 76){
				// Enchanted Book (treasure/level 30)
				$book = VanillaItems::ENCHANTED_BOOK();
				$enchants = [
					VanillaEnchantments::PROTECTION(),
					VanillaEnchantments::SHARPNESS(),
					VanillaEnchantments::EFFICIENCY(),
					VanillaEnchantments::UNBREAKING(),
					VanillaEnchantments::POWER(),
					VanillaEnchantments::MENDING()
				];
				$selected = $enchants[mt_rand(0, count($enchants) - 1)];
				$book->addEnchantment(new EnchantmentInstance($selected, mt_rand(1, $selected->getMaxLevel())));
				$drops[] = $book;
			}else{
				// Iron gear (tool or armor) with durability damage
				$gearChoices = [
					VanillaItems::IRON_PICKAXE(),
					VanillaItems::IRON_AXE(),
					VanillaItems::IRON_SHOVEL(),
					VanillaItems::IRON_SWORD(),
					VanillaItems::IRON_HELMET(),
					VanillaItems::IRON_CHESTPLATE(),
					VanillaItems::IRON_LEGGINGS(),
					VanillaItems::IRON_BOOTS()
				];
				$gear = $gearChoices[mt_rand(0, count($gearChoices) - 1)];
				if($gear instanceof Durable){
					$maxDura = $gear->getMaxDurability();
					$damagePct = mt_rand(30, 90) / 100.0;
					$gear->setDamage((int) ($maxDura * $damagePct));
				}
				if(mt_rand(1, 2) === 1){
					$gearEnchants = [
						VanillaEnchantments::UNBREAKING(),
						VanillaEnchantments::EFFICIENCY(),
						VanillaEnchantments::PROTECTION(),
						VanillaEnchantments::SHARPNESS()
					];
					$e = $gearEnchants[mt_rand(0, count($gearEnchants) - 1)];
					$gear->addEnchantment(new EnchantmentInstance($e, mt_rand(1, $e->getMaxLevel())));
				}
				$drops[] = $gear;
			}
		}

		return $drops;
	}
}
