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

namespace pocketmine\world\generator\structure;

use pocketmine\block\Block;
use pocketmine\block\VanillaBlocks;
use pocketmine\utils\Random;
use pocketmine\world\ChunkManager;
use function abs;
use function max;

/**
 * The broken stone brick walls of a small building, with a cobblestone floor and a few cobwebs.
 */
final class Ruins extends Structure{
	private const HALF = 3;

	public function getName() : string{
		return "ruins";
	}

	public function place(ChunkManager $world, int $x, int $z, Random $random) : bool{
		$y = self::flatGroundY($world, $x, $z, self::HALF, 1);
		if($y === null){
			return false;
		}

		$doorSide = $random->nextBoundedInt(4);
		for($dx = -self::HALF; $dx <= self::HALF; ++$dx){
			for($dz = -self::HALF; $dz <= self::HALF; ++$dz){
				$wall = max(abs($dx), abs($dz)) === self::HALF;
				self::set($world, $x + $dx, $y, $z + $dz, $random->nextBoundedInt(5) === 0 ? VanillaBlocks::GRAVEL() : VanillaBlocks::COBBLESTONE());
				self::support($world, $x + $dx, $y, $z + $dz, VanillaBlocks::COBBLESTONE());

				$height = 0;
				if($wall && !$this->isDoor($dx, $dz, $doorSide)){
					$corner = abs($dx) === self::HALF && abs($dz) === self::HALF;
					$height = $corner ? 3 + $random->nextBoundedInt(2) : $random->nextBoundedInt(4);
				}
				for($dy = 1; $dy <= 4; ++$dy){
					if($dy <= $height){
						self::set($world, $x + $dx, $y + $dy, $z + $dz, $this->brick($random));
					}elseif(!$wall && $dy === 1 && $random->nextBoundedInt(14) === 0){
						self::set($world, $x + $dx, $y + $dy, $z + $dz, VanillaBlocks::COBWEB());
					}else{
						self::set($world, $x + $dx, $y + $dy, $z + $dz, self::air());
					}
				}
			}
		}
		return true;
	}

	private function isDoor(int $dx, int $dz, int $side) : bool{
		return match($side){
			0 => $dz === -self::HALF && $dx === 0,
			1 => $dz === self::HALF && $dx === 0,
			2 => $dx === -self::HALF && $dz === 0,
			default => $dx === self::HALF && $dz === 0,
		};
	}

	private function brick(Random $random) : Block{
		return match($random->nextBoundedInt(6)){
			0, 1 => VanillaBlocks::MOSSY_STONE_BRICKS(),
			2 => VanillaBlocks::CRACKED_STONE_BRICKS(),
			default => VanillaBlocks::STONE_BRICKS(),
		};
	}
}
