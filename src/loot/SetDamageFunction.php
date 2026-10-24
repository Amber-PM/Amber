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

use pocketmine\item\Durable;
use pocketmine\item\Item;
use function max;
use function min;
use function mt_getrandmax;
use function mt_rand;

final class SetDamageFunction implements LootFunction{
	public function __construct(
		public readonly float $minDamageRatio,
		public readonly float $maxDamageRatio
	){}

	public function apply(Item $item, LootContext $context) : Item{
		if($item instanceof Durable){
			$maxDmg = $item->getMaxDurability();
			$ratio = ($this->minDamageRatio === $this->maxDamageRatio)
				? $this->minDamageRatio
				: ($this->minDamageRatio + ((mt_rand() / mt_getrandmax()) * ($this->maxDamageRatio - $this->minDamageRatio)));
			$damage = (int) ($maxDmg * (1.0 - $ratio));
			$item->setDamage(max(0, min($damage, $maxDmg)));
		}
		return $item;
	}
}
