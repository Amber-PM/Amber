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

namespace pocketmine\world;

/**
 * If you want to keep chunks loaded, implement this interface and register it into World.
 *
 * @see World::registerChunkLoader()
 * @see World::unregisterChunkLoader()
 *
 * WARNING: When moving this object around in the world or destroying it,
 * be sure to unregister the loader from chunks you're not using, otherwise you'll leak memory.
 */
interface ChunkLoader{

}
