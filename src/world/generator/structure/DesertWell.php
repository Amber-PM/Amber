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

use pocketmine\block\BlockTypeTags;
use pocketmine\block\VanillaBlocks;
use pocketmine\utils\Random;
use pocketmine\world\ChunkManager;
use function abs;
use function max;

final class DesertWell extends Structure{

	public function getName() : string{
		return "desert_well";
	}

	public function place(ChunkManager $world, int $x, int $z, Random $random) : bool{
		$y = self::flatGroundY($world, $x, $z, 2, 1);
		if($y === null || !$world->getBlockAt($x, $y, $z)->hasTypeTag(BlockTypeTags::SAND)){
			return false;
		}

		$sandstone = VanillaBlocks::SANDSTONE();
		$slab = VanillaBlocks::SANDSTONE_SLAB();
		$water = VanillaBlocks::WATER();

		for($dx = -2; $dx <= 2; ++$dx){
			for($dz = -2; $dz <= 2; ++$dz){
				$edge = max(abs($dx), abs($dz)) === 2;
				$center = $dx === 0 && $dz === 0;

				self::set($world, $x + $dx, $y - 1, $z + $dz, $center ? $water : $sandstone);
				self::support($world, $x + $dx, $y - 1, $z + $dz, $sandstone);
				if($center){
					self::set($world, $x, $y - 2, $z, $sandstone);
				}

				if($center){
					self::set($world, $x, $y, $z, $water);
				}elseif($edge){
					$cardinal = $dx === 0 || $dz === 0;
					self::set($world, $x + $dx, $y, $z + $dz, $cardinal ? $slab : $sandstone);
				}else{
					self::set($world, $x + $dx, $y, $z + $dz, $sandstone);
				}

				for($dy = 1; $dy <= 4; ++$dy){
					$pillar = abs($dx) === 1 && abs($dz) === 1 && $dy <= 2;
					$roof = $dy === 3 && !$edge;
					if($pillar || ($roof && !$center)){
						self::set($world, $x + $dx, $y + $dy, $z + $dz, $roof ? $slab : $sandstone);
					}elseif($roof){
						self::set($world, $x, $y + $dy, $z, $sandstone);
					}else{
						self::set($world, $x + $dx, $y + $dy, $z + $dz, self::air());
					}
				}
			}
		}
		return true;
	}
}
