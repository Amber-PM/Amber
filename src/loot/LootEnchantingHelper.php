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

use pocketmine\item\enchantment\EnchantingHelper;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\Item;
use function count;

/**
 * @internal
 */
final class LootEnchantingHelper{

	private function __construct(){
		//NOOP
	}

	/**
	 * Enchants the item like EnchantingHelper (books become enchanted books), keeping the
	 * custom name and lore set by earlier loot functions.
	 *
	 * @param EnchantmentInstance[] $enchantments
	 */
	public static function enchantItem(Item $item, array $enchantments) : Item{
		$result = EnchantingHelper::enchantItem($item, $enchantments);
		if($item->hasCustomName()){
			$result->setCustomName($item->getCustomName());
		}
		if(count($item->getLore()) > 0){
			$result->setLore($item->getLore());
		}
		return $result;
	}
}
