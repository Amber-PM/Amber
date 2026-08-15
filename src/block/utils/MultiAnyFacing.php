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

use pocketmine\math\Facing;

interface MultiAnyFacing{

	/**
	 * @return int[]
	 * @see Facing
	 */
	public function getFaces() : array;

	public function hasFace(int $face) : bool;

	/**
	 * @return $this
	 *
	 * @see Facing
	 */
	public function setFace(int $face, bool $value) : self;

	/**
	 * @param int[] $faces
	 *
	 * @return $this
	 *
	 * @see Facing
	 */
	public function setFaces(array $faces) : self;

}
