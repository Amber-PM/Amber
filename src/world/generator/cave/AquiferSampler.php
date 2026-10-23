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

use pocketmine\block\VanillaBlocks;
use pocketmine\utils\Random;
use pocketmine\world\generator\noise\Simplex;

class AquiferSampler{

	public const SEA_LEVEL = 62;
	public const LAVA_LEVEL = -54;

	private Simplex $aquiferNoise;

	public function __construct(int $seed){
		$this->aquiferNoise = new Simplex(new Random($seed ^ 0x0f0f0f0f), 2, 0.5, 1 / 128);
	}

	/**
	 * Returns the fluid block state ID to flood this subterranean void with, or null for empty air.
	 */
	public function getFluid(int $x, int $y, int $z) : ?int{
		if($y <= self::LAVA_LEVEL){
			return VanillaBlocks::LAVA()->getStateId();
		}

		// Flooded aquifer bodies below sea level
		if($y <= self::SEA_LEVEL){
			$noise = $this->aquiferNoise->getNoise3D($x / 48.0, $y / 24.0, $z / 48.0);
			if($noise > 0.42){
				return VanillaBlocks::WATER()->getStateId();
			}
		}

		return null;
	}
}
