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
use pocketmine\math\Axis;
use pocketmine\utils\Random;
use pocketmine\world\ChunkManager;
use function max;

/**
 * A buried skeleton of bone blocks (a spine with ribs and a skull), sometimes with coal ore in it.
 */
final class Fossil extends Structure{

	public function getName() : string{
		return "fossil";
	}

	public function place(ChunkManager $world, int $x, int $z, Random $random) : bool{
		$surface = self::groundY($world, $x, $z);
		if($surface === null){
			return false;
		}
		$y = $surface - 12 - $random->nextBoundedInt(16);
		$y = max($y, $world->getMinY() + 8);
		if($y + 3 >= $surface){
			return false;
		}

		$coal = $random->nextBoundedInt(3) === 0;
		$half = 3 + $random->nextBoundedInt(3);
		$spine = VanillaBlocks::BONE_BLOCK()->setAxis(Axis::X);
		$rib = VanillaBlocks::BONE_BLOCK();

		for($dx = -$half; $dx <= $half; ++$dx){
			$this->bone($world, $x + $dx, $y, $z, $spine, $coal, $random);
			if(($dx + $half) % 2 === 1 && $dx < $half - 1){
				foreach([-1, 1] as $side){
					foreach([[1, 1], [2, 1], [3, 0], [3, -1]] as [$out, $dy]){
						$this->bone($world, $x + $dx, $y + $dy, $z + $side * $out, $rib, $coal, $random);
					}
				}
			}
		}

		//skull
		for($dx = 1; $dx <= 2; ++$dx){
			for($dy = 0; $dy <= 1; ++$dy){
				for($dz = -1; $dz <= 0; ++$dz){
					$this->bone($world, $x + $half + $dx, $y + $dy, $z + $dz, $rib, $coal, $random);
				}
			}
		}
		return true;
	}

	private function bone(ChunkManager $world, int $x, int $y, int $z, Block $bone, bool $coal, Random $random) : void{
		self::replaceSolid($world, $x, $y, $z, $coal && $random->nextBoundedInt(8) === 0 ? VanillaBlocks::COAL_ORE() : $bone);
	}
}
