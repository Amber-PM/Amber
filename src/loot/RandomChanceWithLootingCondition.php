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

use function mt_getrandmax;
use function mt_rand;

final class RandomChanceWithLootingCondition implements LootCondition{
	public function __construct(
		public readonly float $chance,
		public readonly float $lootingMultiplier
	){}

	public function test(LootContext $context) : bool{
		$effectiveChance = $this->chance + ($context->getLootingLevel() * $this->lootingMultiplier);
		return (mt_rand() / mt_getrandmax()) < $effectiveChance;
	}
}
