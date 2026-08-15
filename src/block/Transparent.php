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

/**
 * Transparent blocks do not block any light from propagating through them.
 *
 * Note: This does **not** imply that the block is **visually** transparent. For example, chests allow light to pass
 * through, but the player cannot see through them except at the edges.
 */
class Transparent extends Block{

	public function isTransparent() : bool{
		return true;
	}
}
