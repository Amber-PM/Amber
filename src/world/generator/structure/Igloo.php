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
use function abs;

final class Igloo extends Structure{
	private const RADIUS = 3;
	private const HEIGHT = 3.6;

	public function getName() : string{
		return "igloo";
	}

	public function place(ChunkManager $world, int $x, int $z, Random $random) : bool{
		$y = self::flatGroundY($world, $x, $z, self::RADIUS + 1, 1);
		if($y === null){
			return false;
		}

		$snow = VanillaBlocks::SNOW();
		$ice = VanillaBlocks::ICE();

		for($dx = -self::RADIUS; $dx <= self::RADIUS; ++$dx){
			for($dz = -self::RADIUS; $dz <= self::RADIUS; ++$dz){
				$horizontal = ($dx ** 2 + $dz ** 2) / (self::RADIUS + 0.5) ** 2;
				if($horizontal > 1){
					continue;
				}
				self::set($world, $x + $dx, $y, $z + $dz, $snow);
				self::support($world, $x + $dx, $y, $z + $dz, $snow);

				for($dy = 1; $dy <= 4; ++$dy){
					$outer = $horizontal + ($dy ** 2) / self::HEIGHT ** 2;
					$inner = ($dx ** 2 + $dz ** 2) / (self::RADIUS - 0.5) ** 2 + ($dy ** 2) / (self::HEIGHT - 1) ** 2;
					if($outer > 1){
						self::set($world, $x + $dx, $y + $dy, $z + $dz, self::air());
					}elseif($inner <= 1){
						self::set($world, $x + $dx, $y + $dy, $z + $dz, self::air());
					}else{
						$window = $dy === 2 && $dz === 0 && abs($dx) === self::RADIUS - 1;
						self::set($world, $x + $dx, $y + $dy, $z + $dz, $window ? $ice : $snow);
					}
				}
			}
		}

		//entrance tunnel on the south side
		$door = self::RADIUS;
		foreach([$door, $door + 1] as $dz){
			foreach([-1, 1] as $dx){
				self::set($world, $x + $dx, $y + 1, $z + $dz, $snow);
				self::set($world, $x + $dx, $y + 2, $z + $dz, $snow);
			}
			self::set($world, $x, $y, $z + $dz, $snow);
			self::support($world, $x, $y, $z + $dz, $snow);
			self::set($world, $x, $y + 1, $z + $dz, self::air());
			self::set($world, $x, $y + 2, $z + $dz, self::air());
			self::set($world, $x, $y + 3, $z + $dz, $snow);
		}
		self::set($world, $x, $y + 2, $z + $door - 1, self::air());
		self::set($world, $x, $y + 3, $z + $door - 1, $snow);

		$carpet = VanillaBlocks::CARPET();
		for($dx = -1; $dx <= 1; ++$dx){
			for($dz = -1; $dz <= 1; ++$dz){
				self::set($world, $x + $dx, $y + 1, $z + $dz, $carpet);
			}
		}
		self::set($world, $x - 2, $y + 1, $z - 1, VanillaBlocks::LANTERN());
		return true;
	}
}
