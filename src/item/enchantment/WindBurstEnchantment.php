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

namespace pocketmine\item\enchantment;

use pocketmine\lang\Translatable;

class WindBurstEnchantment extends Enchantment{

	public function __construct(
		Translatable|string $name,
		int $rarity,
		int $primaryItemFlags,
		int $secondaryItemFlags,
		int $maxLevel = 3
	){
		parent::__construct(
			$name,
			$rarity,
			$primaryItemFlags,
			$secondaryItemFlags,
			$maxLevel,
			fn(int $level) : int => 15 * ($level - 1) + 20,
			50
		);
	}

	public function getBounceBlocks(int $level) : float{
		return $level * 7.0;
	}
}
