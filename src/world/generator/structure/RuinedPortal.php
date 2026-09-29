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
 * A broken obsidian portal frame on a patch of netherrack, with a block of gold somewhere on it.
 */
final class RuinedPortal extends Structure{
	private const PATCH_RADIUS = 3;

	public function getName() : string{
		return "ruined_portal";
	}

	public function place(ChunkManager $world, int $x, int $z, Random $random) : bool{
		$y = self::flatGroundY($world, $x, $z, self::PATCH_RADIUS, 2);
		if($y === null){
			return false;
		}

		$netherrack = VanillaBlocks::NETHERRACK();
		$magma = VanillaBlocks::MAGMA();
		for($dx = -self::PATCH_RADIUS; $dx <= self::PATCH_RADIUS; ++$dx){
			for($dz = -self::PATCH_RADIUS; $dz <= self::PATCH_RADIUS; ++$dz){
				if($dx ** 2 + $dz ** 2 > self::PATCH_RADIUS ** 2 + 1 || $random->nextBoundedInt(10) < 3){
					continue;
				}
				$ground = self::groundY($world, $x + $dx, $z + $dz);
				if($ground !== null){
					self::set($world, $x + $dx, $ground, $z + $dz, $random->nextBoundedInt(8) === 0 ? $magma : $netherrack);
				}
			}
		}

		//4 wide, 5 tall, along the x axis; the portal's inside is 2x3
		$obsidian = VanillaBlocks::OBSIDIAN();
		$crying = VanillaBlocks::CRYING_OBSIDIAN();
		for($dx = -1; $dx <= 2; ++$dx){
			for($dy = 0; $dy <= 4; ++$dy){
				$frame = $dx === -1 || $dx === 2 || $dy === 0 || $dy === 4;
				if(!$frame){
					self::set($world, $x + $dx, $y + $dy, $z, self::air());
					continue;
				}
				//ruined: the top is more likely to be missing than the bottom
				if($random->nextBoundedInt(10) < 1 + $dy){
					if($dy > 0){
						self::set($world, $x + $dx, $y + $dy, $z, self::air());
					}
					continue;
				}
				self::set($world, $x + $dx, $y + $dy, $z, $random->nextBoundedInt(6) === 0 ? $crying : $obsidian);
				if($dy === 0){
					self::support($world, $x + $dx, $y, $z, $netherrack);
				}
			}
		}

		$chestY = self::groundY($world, $x - 2, $z + 1);
		if($chestY !== null){
			self::set($world, $x - 2, $chestY + 1, $z + 1, VanillaBlocks::CHEST());
		}

		$goldX = $x + $random->nextRange(-2, 2);
		$goldZ = $z + ($random->nextBoolean() ? 2 : -2);
		$goldY = self::groundY($world, $goldX, $goldZ);
		if($goldY !== null){
			self::set($world, $goldX, $goldY + 1, $goldZ, VanillaBlocks::GOLD());
		}
		return true;
	}
}
