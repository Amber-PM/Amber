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

namespace pocketmine\tests\comparative;

use pocketmine\block\Block;
use pocketmine\block\VanillaBlocks;
use pocketmine\world\format\Chunk;
use function abs;

final class ChunkComparisonResult{

	public function __construct(
		public readonly int $chunkX,
		public readonly int $chunkZ,
		public readonly bool $bedrockFloorValid,
		public readonly bool $deepslateTransitionValid,
		public readonly float $maxSurfaceDelta,
		public readonly int $caveAirBlocks,
		public readonly bool $passed
	){}
}

class LevelDbChunkDiff{

	/**
	 * Compares generated chunk against Bedrock 1.18+ world generation invariant rules.
	 */
	public static function validateChunkInvariants(Chunk $chunk, int $chunkX, int $chunkZ, float $expectedCenterSurfaceHeight) : ChunkComparisonResult{
		$bedrockId = VanillaBlocks::BEDROCK()->getStateId();
		$deepslateId = VanillaBlocks::DEEPSLATE()->getStateId();
		$stoneId = VanillaBlocks::STONE()->getStateId();
		$waterId = VanillaBlocks::WATER()->getStateId();
		$airId = Block::EMPTY_STATE_ID;

		$bedrockFloorValid = true;
		$deepslateTransitionValid = true;
		$caveAirBlocks = 0;

		// 1. Bedrock floor verification (Y = -64 must be 100% bedrock; Y >= -59 must not be bedrock)
		for($x = 0; $x < Chunk::EDGE_LENGTH; ++$x){
			for($z = 0; $z < Chunk::EDGE_LENGTH; ++$z){
				if($chunk->getBlockStateId($x, -64, $z) !== $bedrockId){
					$bedrockFloorValid = false;
				}
				if($chunk->getBlockStateId($x, -59, $z) === $bedrockId){
					$bedrockFloorValid = false;
				}

				// Deepslate stratum verification: Y < -8 should be deepslate or air/fluid (never plain stone)
				for($y = -59; $y < -8; ++$y){
					$b = $chunk->getBlockStateId($x, $y, $z);
					if($b === $stoneId){
						$deepslateTransitionValid = false;
					}
					if($b === $airId){
						++$caveAirBlocks;
					}
				}
			}
		}

		// 2. Center column solid terrain surface height parity against spline
		$highestY = $chunk->getHighestBlockAt(8, 8) ?? 62;
		// If the top is water, find the solid seabed beneath it
		if($chunk->getBlockStateId(8, $highestY, 8) === $waterId){
			for($y = $highestY; $y >= -60; --$y){
				$b = $chunk->getBlockStateId(8, $y, 8);
				if($b !== $waterId && $b !== $airId){
					$highestY = $y;
					break;
				}
			}
		}

		$surfaceDelta = abs($highestY - $expectedCenterSurfaceHeight);

		// Solid surface height must match spline within 8 blocks (accounting for 3D cliff noise)
		$surfaceEnvelopeValid = $surfaceDelta <= 8.0;

		$passed = $bedrockFloorValid && $deepslateTransitionValid && $surfaceEnvelopeValid;

		return new ChunkComparisonResult(
			chunkX: $chunkX,
			chunkZ: $chunkZ,
			bedrockFloorValid: $bedrockFloorValid,
			deepslateTransitionValid: $deepslateTransitionValid,
			maxSurfaceDelta: $surfaceDelta,
			caveAirBlocks: $caveAirBlocks,
			passed: $passed
		);
	}
}
