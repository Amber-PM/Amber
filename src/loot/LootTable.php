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

use pocketmine\item\Item;

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
}
