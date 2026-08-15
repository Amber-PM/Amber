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

namespace pocketmine\event\world;

use pocketmine\world\format\Chunk;
use pocketmine\world\World;

/**
 * Called when a Chunk is loaded or newly created by the world generator.
 */
class ChunkLoadEvent extends ChunkEvent{
	public function __construct(
		World $world,
		int $chunkX,
		int $chunkZ,
		Chunk $chunk,
		private bool $newChunk
	){
		parent::__construct($world, $chunkX, $chunkZ, $chunk);
	}

	/**
	 * Returns whether the chunk is newly generated.
	 * If false, the chunk was loaded from storage.
	 */
	public function isNewChunk() : bool{
		return $this->newChunk;
	}
}
