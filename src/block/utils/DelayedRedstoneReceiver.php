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
 * A redstone receiver that changes some time after its power does (torches, repeaters, comparators, lamps turning
 * off): it asks for the delay with RedstoneEngine::schedule().
 */
interface DelayedRedstoneReceiver extends RedstoneReceiver{

	/** Called when a change scheduled with RedstoneEngine::schedule() is due. */
	public function onRedstoneScheduledUpdate(RedstoneEngine $engine) : void;
}
