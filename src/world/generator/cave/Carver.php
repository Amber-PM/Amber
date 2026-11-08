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

namespace pocketmine\world\generator\cave;

use pocketmine\world\format\Chunk;

interface Carver{

	/**
	 * Carves subterranean cave systems, tunnels, or ravines into the given chunk.
	 *
	 * @param float[] $surfaceHeights Array of 256 surface elevation heights for column (x, z)
	 */
	public function carve(Chunk $chunk, int $chunkX, int $chunkZ, array $surfaceHeights) : void;
}
