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

use pocketmine\block\utils\Colored;
use pocketmine\block\utils\ColoredTrait;

class DyedCandle extends Candle implements Colored{
	use ColoredTrait;

	protected function getCandleIfCompatibleType(Block $block) : ?Candle{
		$result = parent::getCandleIfCompatibleType($block);
		//different coloured candles can't be combined in the same block
		return $result instanceof DyedCandle && $result->color === $this->color ? $result : null;
	}
}
