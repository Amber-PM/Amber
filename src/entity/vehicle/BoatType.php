<?php

/*
 *
 *     _             _               
 *    / \\   _ __ ___ | |__   ___ _ __ 
 *   / _ \\ | '_ ` _ \\| '_ \\ / _ \\ '__|
 *  / ___ \\| | | | | | |_) |  __/ |   
 * /_/   \\_\\_| |_| |_|_.__/ \\___|_|   
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

namespace pocketmine\entity\vehicle;

enum BoatType : string{
	case OAK = "oak";
	case SPRUCE = "spruce";
	case BIRCH = "birch";
	case JUNGLE = "jungle";
	case ACACIA = "acacia";
	case DARK_OAK = "dark_oak";
	case MANGROVE = "mangrove";
	case CHERRY = "cherry";
	case PALE_OAK = "pale_oak";
	case BAMBOO = "bamboo";

	public function isRaft() : bool{
		return $this === self::BAMBOO;
	}
}
