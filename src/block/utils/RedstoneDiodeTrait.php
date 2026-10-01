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

namespace pocketmine\block\utils;

use pocketmine\block\Redstone;
use pocketmine\block\RedstoneComparator;
use pocketmine\block\RedstoneRepeater;
use pocketmine\block\RedstoneWire;
use pocketmine\math\Facing;
use pocketmine\world\redstone\RedstoneEngine;

/**
 * Inputs of repeaters and comparators, which take power from behind (the side they face) and send it out in front.
 */
trait RedstoneDiodeTrait{

	/** Power coming in from behind. */
	protected function getRedstoneInput(RedstoneEngine $engine) : int{
		return $engine->getPowerFrom($this->position->getSide($this->facing), Facing::opposite($this->facing));
	}

	/** Power coming in from the side $side: only wire, repeaters, comparators and redstone blocks count. */
	protected function getRedstoneSideInput(RedstoneEngine $engine, int $side) : int{
		$block = $this->getSide($side);
		if($block instanceof RedstoneWire || $block instanceof RedstoneRepeater || $block instanceof RedstoneComparator || $block instanceof Redstone){
			return $engine->getOutput($block, Facing::opposite($side), false);
		}
		return 0;
	}
}
