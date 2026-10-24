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

enum StructureRotation : int{
	case NONE = 0;
	case CLOCKWISE_90 = 1;
	case CLOCKWISE_180 = 2;
	case COUNTERCLOCKWISE_90 = 3;

	/**
	 * Rotates 2D (X, Z) coordinate within bounds [0, sizeX - 1] x [0, sizeZ - 1].
	 *
	 * @return array{int, int} [rotatedX, rotatedZ]
	 */
	public function rotateCoordinate(int $x, int $z, int $sizeX, int $sizeZ) : array{
		return match($this){
			self::NONE => [$x, $z],
			self::CLOCKWISE_90 => [$sizeZ - 1 - $z, $x],
			self::CLOCKWISE_180 => [$sizeX - 1 - $x, $sizeZ - 1 - $z],
			self::COUNTERCLOCKWISE_90 => [$z, $sizeX - 1 - $x],
		};
	}

	/**
	 * @return array{int, int} [rotatedSizeX, rotatedSizeZ]
	 */
	public function getRotatedSize(int $sizeX, int $sizeZ) : array{
		return match($this){
			self::NONE, self::CLOCKWISE_180 => [$sizeX, $sizeZ],
			self::CLOCKWISE_90, self::COUNTERCLOCKWISE_90 => [$sizeZ, $sizeX],
		};
	}

	public function rotateFacing(int $facing) : int{
		if($facing === Facing::UP || $facing === Facing::DOWN){
			return $facing;
		}

		return match($this){
			self::NONE => $facing,
			self::CLOCKWISE_90 => Facing::rotateY($facing, true),
			self::CLOCKWISE_180 => Facing::opposite($facing),
			self::COUNTERCLOCKWISE_90 => Facing::rotateY($facing, false),
		};
	}
}
