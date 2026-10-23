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

use pocketmine\block\Block;
use pocketmine\block\VanillaBlocks;
use pocketmine\utils\Random;
use pocketmine\world\format\Chunk;
use pocketmine\world\generator\noise\Simplex;
use function abs;
use function min;

class NoiseCaveGenerator implements Carver{

	private Simplex $cheeseNoise;
	private Simplex $spaghettiNoise1;
	private Simplex $spaghettiNoise2;
	private Simplex $noodleNoise;
	private AquiferSampler $aquiferSampler;

	public function __construct(int $seed){
		$this->cheeseNoise = new Simplex(new Random($seed ^ 0x11111111), 3, 0.5, 1 / 96);
		$this->spaghettiNoise1 = new Simplex(new Random($seed ^ 0x22222222), 2, 0.5, 1 / 64);
		$this->spaghettiNoise2 = new Simplex(new Random($seed ^ 0x33333333), 2, 0.5, 1 / 64);
		$this->noodleNoise = new Simplex(new Random($seed ^ 0x44444444), 2, 0.5, 1 / 32);
		$this->aquiferSampler = new AquiferSampler($seed);
	}

	public function carve(Chunk $chunk, int $chunkX, int $chunkZ, array $surfaceHeights) : void{
		$baseX = $chunkX * Chunk::EDGE_LENGTH;
		$baseZ = $chunkZ * Chunk::EDGE_LENGTH;

		$stoneId = VanillaBlocks::STONE()->getStateId();
		$deepslateId = VanillaBlocks::DEEPSLATE()->getStateId();

		for($x = 0; $x < Chunk::EDGE_LENGTH; ++$x){
			$worldX = $baseX + $x;
			for($z = 0; $z < Chunk::EDGE_LENGTH; ++$z){
				$worldZ = $baseZ + $z;
				$colIndex = ($x << 4) | $z;
				$surfaceY = (int) ($surfaceHeights[$colIndex] ?? 64.0);
				$maxCarveY = min($surfaceY - 4, 256);

				// Step Y by 2 with dual-block carving for 2x performance
				for($y = -58; $y <= $maxCarveY; $y += 2){
					$blockId = $chunk->getBlockStateId($x, $y, $z);
					if($blockId !== $stoneId && $blockId !== $deepslateId){
						continue;
					}

					if($this->shouldCarve($worldX, $y, $worldZ)){
						$fluid = $this->aquiferSampler->getFluid($worldX, $y, $worldZ);
						$fluidId = $fluid ?? Block::EMPTY_STATE_ID;

						$chunk->setBlockStateId($x, $y, $z, $fluidId);
						$chunk->setBlockStateId($x, $y - 1, $z, $fluidId);
					}
				}
			}
		}
	}

	public function shouldCarve(int $x, int $y, int $z) : bool{
		// 1. Cheese caves: huge subterranean caverns
		$cheese = $this->cheeseNoise->getNoise3D($x / 64.0, $y / 32.0, $z / 64.0);
		if($cheese < -0.58){
			return true;
		}

		// 2. Spaghetti caves: short-circuit if s1 is outside bounds
		$s1 = $this->spaghettiNoise1->getNoise3D($x / 48.0, $y / 24.0, $z / 48.0);
		$absS1 = abs($s1);
		if($absS1 < 0.08){
			$s2 = $this->spaghettiNoise2->getNoise3D($x / 48.0, $y / 24.0, $z / 48.0);
			if(($absS1 + abs($s2)) < 0.08){
				return true;
			}
		}

		// 3. Noodle caves: narrow capillary tunnels
		$n = $this->noodleNoise->getNoise3D($x / 32.0, $y / 16.0, $z / 32.0);
		if(abs($n) < 0.025){
			return true;
		}

		return false;
	}
}
