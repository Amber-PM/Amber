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

use pocketmine\world\redstone\RedstoneEngine;

/**
 * A block that gives out redstone power: levers, buttons, plates, torches, repeaters, comparators, wire...
 */
interface RedstoneSource{

	/**
	 * Power sent out through the face $face (the direction from this block to the one receiving it). With
	 * $strongOnly, only power that strongly powers the block on that side, so wire beside that block picks it up.
	 */
	public function getRedstoneOutput(int $face, bool $strongOnly, RedstoneEngine $engine) : int;
}
