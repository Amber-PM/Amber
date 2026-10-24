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

namespace pocketmine\loot;

use pocketmine\item\enchantment\AvailableEnchantmentRegistry;
use pocketmine\item\enchantment\EnchantingHelper;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\Item;
use pocketmine\item\ItemTypeIds;
use function array_rand;
use function array_values;
use function count;
use function mt_rand;

final class EnchantRandomlyFunction implements LootFunction{

	public function apply(Item $item, LootContext $context) : Item{
		$registry = AvailableEnchantmentRegistry::getInstance();
		if($item->getTypeId() === ItemTypeIds::BOOK || $item->getTypeId() === ItemTypeIds::ENCHANTED_BOOK){
			$candidates = array_values($registry->getAll());
		}else{
			$candidates = array_values($registry->getAllEnchantmentsForItem($item));
		}

		if(count($candidates) === 0){
			return $item;
		}

		$enchant = $candidates[array_rand($candidates)];
		$level = mt_rand(1, $enchant->getMaxLevel());

		return EnchantingHelper::enchantItem($item, [new EnchantmentInstance($enchant, $level)]);
	}
}
