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

namespace pocketmine\world\generator\noise;

use pocketmine\utils\Random;

class DensityFunction{

	private Simplex $detailNoise;
	private Simplex $cliffNoise;

	public function __construct(
		int $seed
	){
		$this->detailNoise = new Simplex(new Random($seed ^ 0x5a5a5a5a), 3, 0.5, 1 / 64);
		$this->cliffNoise = new Simplex(new Random($seed ^ 0x33cc33cc), 2, 0.5, 1 / 128);
	}

	/**
	 * Computes the 3D density at world coordinate (x, y, z).
	 *
	 * @param float $surfaceHeight Expected terrain surface height at (x, z)
	 */
	public function computeDensity(int $x, int $y, int $z, float $surfaceHeight) : float{
		// Base linear gradient: positive below surface, negative above surface
		$heightDiff = $surfaceHeight - $y;

		// 3D noise for natural overhangs, rocky cliffs, and varied terrain
		$noise3d = $this->detailNoise->getNoise3D($x / 32.0, $y / 32.0, $z / 32.0) * 4.0;
		$cliff = $this->cliffNoise->getNoise3D($x / 64.0, $y / 64.0, $z / 64.0) * 6.0;

		return $heightDiff + $noise3d + $cliff;
	}
}
