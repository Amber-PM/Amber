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

namespace pocketmine\world\structure;

use pocketmine\math\Facing;

enum StructureMirror : int{
	case NONE = 0;
	case LEFT_RIGHT = 1; // Invert Z axis (mirror across X plane)
	case FRONT_BACK = 2; // Invert X axis (mirror across Z plane)

	/**
	 * @return array{int, int} [mirroredX, mirroredZ]
	 */
	public function mirrorCoordinate(int $x, int $z, int $sizeX, int $sizeZ) : array{
		return match($this){
			self::NONE => [$x, $z],
			self::LEFT_RIGHT => [$x, $sizeZ - 1 - $z],
			self::FRONT_BACK => [$sizeX - 1 - $x, $z],
		};
	}

	public function mirrorFacing(int $facing) : int{
		return match($this){
			self::NONE => $facing,
			self::LEFT_RIGHT => match($facing){
				Facing::NORTH => Facing::SOUTH,
				Facing::SOUTH => Facing::NORTH,
				default => $facing,
			},
			self::FRONT_BACK => match($facing){
				Facing::WEST => Facing::EAST,
				Facing::EAST => Facing::WEST,
				default => $facing,
			},
		};
	}
}
