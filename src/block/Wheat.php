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

namespace pocketmine\block;

use pocketmine\block\utils\FortuneDropHelper;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;

class Wheat extends Crops{

	public function getDropsForCompatibleTool(Item $item) : array{
		if($this->age >= self::MAX_AGE){
			return [
				VanillaItems::WHEAT(),
				VanillaItems::WHEAT_SEEDS()->setCount(FortuneDropHelper::binomial($item, 0))
			];
		}else{
			return [
				VanillaItems::WHEAT_SEEDS()
			];
		}
	}

	public function asItem() : Item{
		return VanillaItems::WHEAT_SEEDS();
	}
}
