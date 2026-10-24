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

use pocketmine\data\bedrock\EnchantmentIdMap;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\enchantment\StringToEnchantmentParser;
use pocketmine\item\Item;
use function is_numeric;
use function max;
use function min;
use function mt_rand;

final class SpecificEnchantsFunction implements LootFunction{

	/**
	 * @param array<string|int, array{int, int}> $enchants enchantment_name_or_id => [minLevel, maxLevel]
	 */
	public function __construct(
		public readonly array $enchants
	){}

	public function apply(Item $item, LootContext $context) : Item{
		$instances = [];
		foreach($this->enchants as $key => [$minLevel, $maxLevel]){
			$enchantment = null;
			if(is_numeric($key)){
				$enchantment = EnchantmentIdMap::getInstance()->fromId((int) $key);
			}else{
				$enchantment = StringToEnchantmentParser::getInstance()->parse((string) $key);
			}
			if($enchantment !== null){
				$level = $minLevel === $maxLevel ? $minLevel : mt_rand($minLevel, $maxLevel);
				// Max enchantment levels are hard-coded in Bedrock and can't be overwritten by loot tables
				$level = max(1, min($level, $enchantment->getMaxLevel()));
				$instances[] = new EnchantmentInstance($enchantment, $level);
			}
		}

		if($instances === []){
			return $item;
		}

		// Turns a plain book into an enchanted book, keeping name and lore
		return LootEnchantingHelper::enchantItem($item, $instances);
	}
}
