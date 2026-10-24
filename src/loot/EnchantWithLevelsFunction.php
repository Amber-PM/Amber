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
use pocketmine\item\enchantment\Enchantment;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\enchantment\VanillaEnchantments;
use pocketmine\item\Item;
use pocketmine\item\ItemTypeIds;
use function array_filter;
use function array_rand;
use function array_values;
use function count;
use function defined;
use function max;
use function min;
use function mt_rand;
use function round;

final class EnchantWithLevelsFunction implements LootFunction{

	public function __construct(
		public readonly int $minLevels,
		public readonly int $maxLevels,
		public readonly bool $treasure = false
	){}

	public function apply(Item $item, LootContext $context) : Item{
		$level = mt_rand(min($this->minLevels, $this->maxLevels), max($this->minLevels, $this->maxLevels));
		$level = max(1, $level);

		$registry = AvailableEnchantmentRegistry::getInstance();
		if($item->getTypeId() === ItemTypeIds::BOOK || $item->getTypeId() === ItemTypeIds::ENCHANTED_BOOK){
			$candidates = array_values($registry->getAll());
		}else{
			$candidates = array_values($registry->getAllEnchantmentsForItem($item));
		}

		if(count($candidates) === 0){
			return $item;
		}

		if(!$this->treasure){
			$candidates = array_values(array_filter($candidates, fn(Enchantment $e) => !EnchantRandomlyFunction::isTreasure($e)));
		}

		if(count($candidates) === 0){
			return $item;
		}

		// Filter candidates by enchanting power matching the rolled level
		$filteredCandidates = [];
		foreach($candidates as $candidate){
			for($l = $candidate->getMaxLevel(); $l >= 1; --$l){
				if($level >= $candidate->getMinEnchantingPower($l) && $level <= $candidate->getMaxEnchantingPower($l)){
					$filteredCandidates[] = [$candidate, $l];
					break;
				}
			}
		}

		if(count($filteredCandidates) === 0){
			$candidate = $candidates[array_rand($candidates)];
			$tier = (int) max(1, min($candidate->getMaxLevel(), (int) round(($level / 30.0) * $candidate->getMaxLevel())));
			return LootEnchantingHelper::enchantItem($item, [new EnchantmentInstance($candidate, $tier)]);
		}

		/** @var array{Enchantment, int} $selected */
		$selected = $filteredCandidates[array_rand($filteredCandidates)];
		return LootEnchantingHelper::enchantItem($item, [new EnchantmentInstance($selected[0], $selected[1])]);
	}
}
