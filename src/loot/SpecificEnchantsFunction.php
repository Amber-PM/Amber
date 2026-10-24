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

final class SpecificEnchantsFunction implements LootFunction{

	/**
	 * @param array<string|int, int> $enchants enchantment_name_or_id => level
	 */
	public function __construct(
		public readonly array $enchants
	){}

	public function apply(Item $item, LootContext $context) : Item{
		foreach($this->enchants as $key => $level){
			$enchantment = null;
			if(is_numeric($key)){
				$enchantment = EnchantmentIdMap::getInstance()->fromId((int) $key);
			}else{
				$enchantment = StringToEnchantmentParser::getInstance()->parse((string) $key);
			}

			if($enchantment !== null){
				$item->addEnchantment(new EnchantmentInstance($enchantment, $level));
			}
		}
		return $item;
	}
}
