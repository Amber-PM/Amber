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
use function max;
use function min;
use function mt_rand;

final class SetCountFunction implements LootFunction{
	public function __construct(
		public readonly int $min,
		public readonly int $max,
		public readonly bool $add = false
	){}

	public function apply(Item $item, LootContext $context) : Item{
		$count = ($this->min === $this->max) ? $this->min : mt_rand($this->min, $this->max);
		if($this->add){
			$count += $item->getCount();
		}
		$item->setCount(max(0, min($count, $item->getMaxStackSize())));
		return $item;
	}
}
