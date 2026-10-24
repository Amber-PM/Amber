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

use pocketmine\inventory\Inventory;
use pocketmine\item\Item;
use function array_pop;
use function count;
use function range;
use function shuffle;

final class LootTable{

	/**
	 * @param LootPool[] $pools
	 */
	public function __construct(
		public readonly string $id,
		public readonly array $pools
	){}

	/**
	 * @return Item[]
	 */
	public function roll(LootContext $context) : array{
		$items = [];
		foreach($this->pools as $pool){
			foreach($pool->roll($context) as $item){
				$items[] = $item;
			}
		}
		return $items;
	}

	public function fillInventory(Inventory $inventory, LootContext $context) : void{
		$items = $this->roll($context);
		if(count($items) === 0){
			return;
		}

		// Only fill empty slots: items inserted before generation (e.g. by hoppers) must never be overwritten
		$availableSlots = [];
		foreach(range(0, $inventory->getSize() - 1) as $slot){
			if($inventory->isSlotEmpty($slot)){
				$availableSlots[] = $slot;
			}
		}
		shuffle($availableSlots);

		foreach($items as $item){
			if(count($availableSlots) === 0){
				break;
			}
			$slot = array_pop($availableSlots);
			$inventory->setItem($slot, $item);
		}
	}
}
