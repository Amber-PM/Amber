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

use pocketmine\block\VanillaBlocks;
use pocketmine\utils\Random;
use pocketmine\world\ChunkManager;

/**
 * A few overlapping rough spheres of mossy cobblestone, as in taiga.
 */
final class Boulder extends Structure{

	public function getName() : string{
		return "boulder";
	}

	public function place(ChunkManager $world, int $x, int $z, Random $random) : bool{
		$y = self::groundY($world, $x, $z);
		if($y === null){
			return false;
		}

		$mossy = VanillaBlocks::MOSSY_COBBLESTONE();
		$cobble = VanillaBlocks::COBBLESTONE();
		$cx = $x;
		$cz = $z;
		$cy = $y;
		for($i = 0, $count = 2 + $random->nextBoundedInt(2); $i < $count; ++$i){
			$radius = 1 + $random->nextFloat() * 1.2;
			$r = (int) $radius + 1;
			for($dx = -$r; $dx <= $r; ++$dx){
				for($dy = -$r; $dy <= $r; ++$dy){
					for($dz = -$r; $dz <= $r; ++$dz){
						if($dx ** 2 + $dy ** 2 + $dz ** 2 <= $radius ** 2){
							self::set($world, $cx + $dx, $cy + $dy, $cz + $dz, $random->nextBoundedInt(4) === 0 ? $cobble : $mossy);
						}
					}
				}
			}
			$cx += $random->nextRange(-2, 2);
			$cz += $random->nextRange(-2, 2);
			$cy = (self::groundY($world, $cx, $cz) ?? $cy) + $random->nextRange(-1, 0);
		}
		return true;
	}
}
