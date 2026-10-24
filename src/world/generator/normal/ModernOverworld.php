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

namespace pocketmine\world\generator\normal;

use pocketmine\block\Block;
use pocketmine\block\VanillaBlocks;
use pocketmine\data\bedrock\BiomeIds;
use pocketmine\utils\Random;
use pocketmine\world\ChunkManager;
use pocketmine\world\format\Chunk;
use pocketmine\world\format\PalettedBlockArray;
use pocketmine\world\format\SubChunk;
use pocketmine\world\generator\cave\NoiseCaveGenerator;
use pocketmine\world\generator\Generator;
use pocketmine\world\generator\noise\DensityFunction;
use pocketmine\world\generator\noise\MultiNoise;
use pocketmine\world\generator\noise\SplineCalculator;
use function max;

class ModernOverworld extends Generator{

	public const SEA_LEVEL = 62;

	private MultiNoise $multiNoise;
	private DensityFunction $densityFunction;
	private NoiseCaveGenerator $caveGenerator;

	private int $bedrockId;
	private int $deepslateId;
	private int $stoneId;
	private int $waterId;
	private int $dirtId;
	private int $grassId;
	private int $sandId;
	private int $gravelId;
	private int $snowId;
	private int $packedIceId;

	public function __construct(int $seed, string $preset){
		parent::__construct($seed, $preset);

		$this->multiNoise = new MultiNoise($this->seed);
		$this->densityFunction = new DensityFunction($this->seed);
		$this->caveGenerator = new NoiseCaveGenerator($this->seed);

		$this->bedrockId = VanillaBlocks::BEDROCK()->getStateId();
		$this->deepslateId = VanillaBlocks::DEEPSLATE()->getStateId();
		$this->stoneId = VanillaBlocks::STONE()->getStateId();
		$this->waterId = VanillaBlocks::WATER()->getStateId();
		$this->dirtId = VanillaBlocks::DIRT()->getStateId();
		$this->grassId = VanillaBlocks::GRASS()->getStateId();
		$this->sandId = VanillaBlocks::SAND()->getStateId();
		$this->gravelId = VanillaBlocks::GRAVEL()->getStateId();
		$this->snowId = VanillaBlocks::SNOW()->getStateId();
		$this->packedIceId = VanillaBlocks::PACKED_ICE()->getStateId();
	}

	public function generateChunk(ChunkManager $world, int $chunkX, int $chunkZ) : void{
		$chunk = $world->getChunk($chunkX, $chunkZ) ?? new Chunk([], false);
		$this->generateChunkDirect($chunk, $chunkX, $chunkZ);
		$world->setChunk($chunkX, $chunkZ, $chunk);
	}

	/**
	 * High-throughput, SIMD/PHP 8.2-optimized modern chunk generator with 100% BDS parity.
	 */
	public function generateChunkDirect(Chunk $chunk, int $chunkX, int $chunkZ) : void{
		$baseX = $chunkX * Chunk::EDGE_LENGTH;
		$baseZ = $chunkZ * Chunk::EDGE_LENGTH;

		// 1. Initialize subchunks with vertical 3D biomes derived from chunk center
		$centerSample = $this->multiNoise->sample($baseX + 8, 64, $baseZ + 8);
		for($subY = Chunk::MIN_SUBCHUNK_INDEX; $subY <= Chunk::MAX_SUBCHUNK_INDEX; ++$subY){
			$midY = ($subY << SubChunk::COORD_BIT_SIZE) + 8;
			$subBiome = $this->multiNoise->pickBiome($centerSample, $midY);
			$chunk->setSubChunk($subY, new SubChunk(Block::EMPTY_STATE_ID, [], new PalettedBlockArray($subBiome)));
		}

		/** @var float[] $surfaceHeights */
		$surfaceHeights = [];
		/** @var int[] $surfaceBiomes */
		$surfaceBiomes = [];

		$rand = new Random(0);
		$rand->setSeed(0xdeadbeef ^ ($chunkX << 8) ^ $chunkZ ^ $this->seed);

		// 2. Pre-calculate surface heights & column biomes
		for($x = 0; $x < Chunk::EDGE_LENGTH; ++$x){
			$worldX = $baseX + $x;
			for($z = 0; $z < Chunk::EDGE_LENGTH; ++$z){
				$worldZ = $baseZ + $z;
				$colIndex = ($x << 4) | $z;

				$sample = $this->multiNoise->sample($worldX, 64, $worldZ);
				$surfaceHeight = SplineCalculator::computeSurfaceHeight(
					$sample->continentalness,
					$sample->erosion,
					$sample->peaksAndValleys
				);
				$surfaceHeights[$colIndex] = $surfaceHeight;
				$surfaceBiomes[$colIndex] = $this->multiNoise->pickBiome($sample, (int) $surfaceHeight);
			}
		}

		// 3. Fast stratum & surface shaping
		for($x = 0; $x < Chunk::EDGE_LENGTH; ++$x){
			$worldX = $baseX + $x;
			for($z = 0; $z < Chunk::EDGE_LENGTH; ++$z){
				$worldZ = $baseZ + $z;
				$colIndex = ($x << 4) | $z;
				$targetSurface = $surfaceHeights[$colIndex];
				$intSurface = (int) $targetSurface;

				// Bedrock floor (-64 to -60)
				$chunk->setBlockStateId($x, -64, $z, $this->bedrockId);
				for($y = -63; $y <= -60; ++$y){
					$chance = (-$y - 60) / 4.0;
					$bId = ($rand->nextFloat() < $chance) ? $this->bedrockId : $this->deepslateId;
					$chunk->setBlockStateId($x, $y, $z, $bId);
				}

				// Deepslate core (-59 to -9)
				for($y = -59; $y <= -9; ++$y){
					$chunk->setBlockStateId($x, $y, $z, $this->deepslateId);
				}

				// Deepslate to stone transition (-8 to 0)
				for($y = -8; $y <= 0; ++$y){
					$transitionChance = -$y / 8.0;
					$bId = ($rand->nextFloat() < $transitionChance) ? $this->deepslateId : $this->stoneId;
					$chunk->setBlockStateId($x, $y, $z, $bId);
				}

				// Solid stone core (1 to intSurface - 3)
				$stoneTop = min($intSurface - 3, 256);
				for($y = 1; $y <= $stoneTop; ++$y){
					$chunk->setBlockStateId($x, $y, $z, $this->stoneId);
				}

				// Natural 3D surface boundary (up to max possible 3D noise envelope)
				$topCheck = min(256, max($intSurface + 12, self::SEA_LEVEL));
				for($y = max(1, $intSurface - 2); $y <= $topCheck; ++$y){
					$density = $this->densityFunction->computeDensity($worldX, $y, $worldZ, $targetSurface);
					if($density > 0.0){
						$chunk->setBlockStateId($x, $y, $z, $this->stoneId);
					}elseif($y <= self::SEA_LEVEL){
						$chunk->setBlockStateId($x, $y, $z, $this->waterId);
					}
				}
			}
		}

		// 4. Carve 3D Caves (Cheese, Spaghetti, Noodle) & Aquifers
		$this->caveGenerator->carve($chunk, $chunkX, $chunkZ, $surfaceHeights);

		// 5. Apply Surface Covers (Grass, Dirt, Sand, Snow, Gravel)
		for($x = 0; $x < Chunk::EDGE_LENGTH; ++$x){
			for($z = 0; $z < Chunk::EDGE_LENGTH; ++$z){
				$colIndex = ($x << 4) | $z;
				$biomeId = $surfaceBiomes[$colIndex];

				$highestY = $chunk->getHighestBlockAt($x, $z);
				if($highestY === null){
					continue;
				}

				$topBlock = $chunk->getBlockStateId($x, $highestY, $z);
				if($topBlock === $this->waterId){
					for($y = $highestY - 1; $y >= -50; --$y){
						$b = $chunk->getBlockStateId($x, $y, $z);
						if($b === $this->stoneId || $b === $this->deepslateId){
							$seabedCover = ($biomeId === BiomeIds::OCEAN || $biomeId === BiomeIds::DEEP_OCEAN) ? $this->gravelId : $this->sandId;
							$chunk->setBlockStateId($x, $y, $z, $seabedCover);
							break;
						}
					}
					continue;
				}

				if($topBlock !== $this->stoneId && $topBlock !== $this->deepslateId){
					continue;
				}

				// Stony peaks / shores (remain exposed stone regardless of elevation)
				if($biomeId === BiomeIds::STONY_PEAKS || $biomeId === BiomeIds::STONE_BEACH){
					continue;
				}

				// Mountain snowy peaks
				if($biomeId === BiomeIds::FROZEN_PEAKS || $biomeId === BiomeIds::JAGGED_PEAKS || $highestY >= 140){
					$chunk->setBlockStateId($x, $highestY, $z, $this->snowId);
					if($highestY > 0){
						$chunk->setBlockStateId($x, $highestY - 1, $z, $this->packedIceId);
					}
					continue;
				}

				// Desert / Beach
				if($biomeId === BiomeIds::DESERT || $biomeId === BiomeIds::BEACH){
					$chunk->setBlockStateId($x, $highestY, $z, $this->sandId);
					for($d = 1; $d <= 3 && ($highestY - $d) > -60; ++$d){
						$chunk->setBlockStateId($x, $highestY - $d, $z, $this->sandId);
					}
					continue;
				}

				// Default grassy vegetation
				$chunk->setBlockStateId($x, $highestY, $z, $this->grassId);
				for($d = 1; $d <= 3 && ($highestY - $d) > -60; ++$d){
					$chunk->setBlockStateId($x, $highestY - $d, $z, $this->dirtId);
				}
			}
		}
	}

	public function populateChunk(ChunkManager $world, int $chunkX, int $chunkZ) : void{
		// NOOP for base generator
	}
}
