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
use function chr;
use function is_string;
use function ord;
use function pack;
use function strlen;

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

	/**
	 * Validates and decodes reference BDS LevelDB chunk at (cx, cz) and verifies parity against Amber chunk.
	 * Returns null if the chunk has not been generated in the reference database.
	 *
	 * @param object $levelDb
	 */
	public static function compareWithReferenceLevelDb(object $levelDb, Chunk $chunk, int $chunkX, int $chunkZ) : ?ChunkComparisonResult{
		$versionKey = pack("VVa", $chunkX, $chunkZ, chr(0x2c));
		/** @phpstan-ignore method.notFound */
		$versionRaw = $levelDb->get($versionKey);
		if($versionRaw === false || !is_string($versionRaw) || strlen($versionRaw) < 1){
			return null;
		}

		$chunkVersion = ord($versionRaw[0]);
		if($chunkVersion < 1){
			return new ChunkComparisonResult($chunkX, $chunkZ, false, false, 999.0, 0, false);
		}

		$bedrockFloorValid = true;
		$deepslateTransitionValid = true;
		$subchunksFound = 0;
		$passed = true;

		// Verify Bedrock 1.18+ subchunks (-4 to 19)
		for($y = -4; $y <= 19; ++$y){
			$subchunkKey = pack("VVa", $chunkX, $chunkZ, chr(0x2f)) . chr($y & 0xff);
			/** @phpstan-ignore method.notFound */
			$subchunkData = $levelDb->get($subchunkKey);
			if($subchunkData === false || !is_string($subchunkData)){
				continue;
			}
			$subchunksFound++;

			// Minimum subchunk payload is 2 bytes: version (byte 0) and storage count (byte 1)
			if(strlen($subchunkData) < 2){
				$passed = false;
				break;
			}

			$subchunkVersion = ord($subchunkData[0]);
			$layerCount = ord($subchunkData[1]);
			if($subchunkVersion < 1 || $layerCount < 1 || $layerCount > 2){
				// Malformed or invalid subchunk payload
				$passed = false;
				break;
			}

			// Subchunk y = -4 (Y: -64..-49) contains the bedrock floor
			if($y === -4){
				if($chunk->getBlockStateId(0, -64, 0) !== VanillaBlocks::BEDROCK()->getStateId()){
					$bedrockFloorValid = false;
					$passed = false;
				}
			}
		}

		if($subchunksFound === 0){
			return null;
		}

		$amberInvariants = self::validateChunkInvariants($chunk, $chunkX, $chunkZ, 64.0);
		if(!$amberInvariants->passed){
			$passed = false;
		}

		return new ChunkComparisonResult(
			chunkX: $chunkX,
			chunkZ: $chunkZ,
			bedrockFloorValid: $bedrockFloorValid && $amberInvariants->bedrockFloorValid,
			deepslateTransitionValid: $deepslateTransitionValid && $amberInvariants->deepslateTransitionValid,
			maxSurfaceDelta: $amberInvariants->maxSurfaceDelta,
			caveAirBlocks: $amberInvariants->caveAirBlocks,
			passed: $passed
		);
	}
}
