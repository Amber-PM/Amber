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
 * A block that reacts to redstone power: lamps, doors, TNT, torches, repeaters, wire...
 */
interface RedstoneReceiver{

	/** Called by the world's redstone engine when the power around the block may have changed. */
	public function onRedstoneUpdate(RedstoneEngine $engine) : void;
}
