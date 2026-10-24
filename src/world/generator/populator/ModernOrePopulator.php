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

namespace pocketmine\world\generator\populator;

use pocketmine\block\Block;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\VanillaBlocks;
use pocketmine\math\VectorMath;
use pocketmine\utils\Random;
use pocketmine\world\ChunkManager;
use pocketmine\world\format\Chunk;
use function array_key_exists;
use function sin;
use function sqrt;
use const M_PI;

final class ModernOrePopulator implements Populator{

	/** @var list<array{stoneOre: Block, deepslateOre: Block, size: int, attempts: int, minY: int, maxY: int, peakY: ?int}> */
	private array $rules = [];

	/** @var array<int, true> */
	private array $stoneReplaceableTypeIds;
	/** @var array<int, true> */
	private array $deepslateReplaceableTypeIds;

	/** @var array<int, true> */
	private array $stoneReplaceableStateIds;
	/** @var array<int, true> */
	private array $deepslateReplaceableStateIds;

	public function __construct(){
		$this->rules = [
			// Coal: Y 0 to 256, peak 96
			[
				"stoneOre" => VanillaBlocks::COAL_ORE(),
				"deepslateOre" => VanillaBlocks::DEEPSLATE_COAL_ORE(),
				"size" => 17,
				"attempts" => 20,
				"minY" => 0,
				"maxY" => 256,
				"peakY" => 96
			],
			// Iron: Y -64 to 72, peak 16
			[
				"stoneOre" => VanillaBlocks::IRON_ORE(),
				"deepslateOre" => VanillaBlocks::DEEPSLATE_IRON_ORE(),
				"size" => 9,
				"attempts" => 15,
				"minY" => -64,
				"maxY" => 72,
				"peakY" => 16
			],
			// Copper: Y -16 to 112, peak 48
			[
				"stoneOre" => VanillaBlocks::COPPER_ORE(),
				"deepslateOre" => VanillaBlocks::DEEPSLATE_COPPER_ORE(),
				"size" => 10,
				"attempts" => 16,
				"minY" => -16,
				"maxY" => 112,
				"peakY" => 48
			],
			// Gold: Y -64 to 32, peak -16
			[
				"stoneOre" => VanillaBlocks::GOLD_ORE(),
				"deepslateOre" => VanillaBlocks::DEEPSLATE_GOLD_ORE(),
				"size" => 9,
				"attempts" => 4,
				"minY" => -64,
				"maxY" => 32,
				"peakY" => -16
			],
			// Redstone: Y -64 to 15, increasing downwards
			[
				"stoneOre" => VanillaBlocks::REDSTONE_ORE(),
				"deepslateOre" => VanillaBlocks::DEEPSLATE_REDSTONE_ORE(),
				"size" => 8,
				"attempts" => 8,
				"minY" => -64,
				"maxY" => 15,
				"peakY" => -64
			],
			// Lapis: Y -64 to 64, peak 0
			[
				"stoneOre" => VanillaBlocks::LAPIS_LAZULI_ORE(),
				"deepslateOre" => VanillaBlocks::DEEPSLATE_LAPIS_LAZULI_ORE(),
				"size" => 7,
				"attempts" => 3,
				"minY" => -64,
				"maxY" => 64,
				"peakY" => 0
			],
			// Diamond: Y -64 to 16, increasing downwards
			[
				"stoneOre" => VanillaBlocks::DIAMOND_ORE(),
				"deepslateOre" => VanillaBlocks::DEEPSLATE_DIAMOND_ORE(),
				"size" => 6,
				"attempts" => 7,
				"minY" => -64,
				"maxY" => 16,
				"peakY" => -64
			],
			// Emerald: Y -16 to 320, peak 232 (triangle distribution in 1.18+)
			[
				"stoneOre" => VanillaBlocks::EMERALD_ORE(),
				"deepslateOre" => VanillaBlocks::DEEPSLATE_EMERALD_ORE(),
				"size" => 3,
				"attempts" => 12,
				"minY" => -16,
				"maxY" => 320,
				"peakY" => 232
			],
			// Dirt in stone: Y 0 to 160
			[
				"stoneOre" => VanillaBlocks::DIRT(),
				"deepslateOre" => VanillaBlocks::DIRT(),
				"size" => 32,
				"attempts" => 10,
				"minY" => 0,
				"maxY" => 160,
				"peakY" => null
			],
			// Gravel: Y -64 to 160
			[
				"stoneOre" => VanillaBlocks::GRAVEL(),
				"deepslateOre" => VanillaBlocks::GRAVEL(),
				"size" => 16,
				"attempts" => 8,
				"minY" => -64,
				"maxY" => 160,
				"peakY" => null
			]
		];

		$this->stoneReplaceableTypeIds = [
			BlockTypeIds::STONE => true,
			BlockTypeIds::GRANITE => true,
			BlockTypeIds::DIORITE => true,
			BlockTypeIds::ANDESITE => true,
		];
		$this->deepslateReplaceableTypeIds = [
			BlockTypeIds::DEEPSLATE => true,
			BlockTypeIds::TUFF => true,
		];

		$this->stoneReplaceableStateIds = [
			VanillaBlocks::STONE()->getStateId() => true,
			VanillaBlocks::GRANITE()->getStateId() => true,
			VanillaBlocks::DIORITE()->getStateId() => true,
			VanillaBlocks::ANDESITE()->getStateId() => true,
		];
		$this->deepslateReplaceableStateIds = [
			VanillaBlocks::DEEPSLATE()->getStateId() => true,
			VanillaBlocks::TUFF()->getStateId() => true,
		];
	}

	public function populate(ChunkManager $world, int $chunkX, int $chunkZ, Random $random) : void{
		$baseX = $chunkX << Chunk::COORD_BIT_SIZE;
		$baseZ = $chunkZ << Chunk::COORD_BIT_SIZE;

		foreach($this->rules as $rule){
			$attempts = $rule["attempts"];
			$size = $rule["size"];
			$minY = $rule["minY"];
			$maxY = $rule["maxY"];
			$peakY = $rule["peakY"];

			for($i = 0; $i < $attempts; ++$i){
				$y = $this->sampleY($random, $minY, $maxY, $peakY);
				$x = $random->nextRange($baseX, $baseX + Chunk::EDGE_LENGTH - 1);
				$z = $random->nextRange($baseZ, $baseZ + Chunk::EDGE_LENGTH - 1);

				$this->placeVein($world, $random, $x, $y, $z, $size, $rule["stoneOre"], $rule["deepslateOre"]);
			}
		}
	}

	private function sampleY(Random $random, int $minY, int $maxY, ?int $peakY) : int{
		if($peakY === null || $minY >= $maxY){
			return $random->nextRange($minY, $maxY);
		}

		if($peakY <= $minY){
			// Skewed down towards minY (e.g. Diamond, Redstone)
			$diff = $maxY - $minY;
			$r = $random->nextFloat();
			return (int) ($minY + $diff * (1.0 - (1.0 - $r) ** 2));
		}

		if($peakY >= $maxY){
			// Skewed up towards maxY
			$diff = $maxY - $minY;
			$r = $random->nextFloat();
			return (int) ($minY + $diff * ($r ** 2));
		}

		// Exact mathematical triangular distribution with mode = peakY
		$u = $random->nextFloat();
		$f = ($peakY - $minY) / ($maxY - $minY);
		if($u < $f){
			return (int) ($minY + sqrt($u * ($maxY - $minY) * ($peakY - $minY)));
		}
		return (int) ($maxY - sqrt((1.0 - $u) * ($maxY - $minY) * ($maxY - $peakY)));
	}

	private function placeVein(
		ChunkManager $world,
		Random $random,
		int $x,
		int $y,
		int $z,
		int $clusterSize,
		Block $stoneOre,
		Block $deepslateOre
	) : void{
		$angle = $random->nextFloat() * M_PI;
		$offset = VectorMath::getDirection2D($angle)->multiply($clusterSize / 8);
		$x1 = $x + 8 + $offset->x;
		$x2 = $x + 8 - $offset->x;
		$z1 = $z + 8 + $offset->y;
		$z2 = $z + 8 - $offset->y;
		$y1 = $y + $random->nextBoundedInt(3) + 2;
		$y2 = $y + $random->nextBoundedInt(3) + 2;

		$stoneOreStateId = $stoneOre->getStateId();
		$deepslateOreStateId = $deepslateOre->getStateId();

		/** @var array<int, ?Chunk> $chunkCache */
		$chunkCache = [];

		for($count = 0; $count <= $clusterSize; ++$count){
			$seedFactor = $count / $clusterSize;
			$cx = $x1 + ($x2 - $x1) * $seedFactor;
			$cy = $y1 + ($y2 - $y1) * $seedFactor;
			$cz = $z1 + ($z2 - $z1) * $seedFactor;
			$size = ((sin($count * (float) M_PI / $clusterSize) + 1.0) * $random->nextFloat() * $clusterSize / 16.0 + 1.0) / 2.0;

			$startX = (int) ($cx - $size);
			$startY = (int) ($cy - $size);
			$startZ = (int) ($cz - $size);
			$endX = (int) ($cx + $size);
			$endY = (int) ($cy + $size);
			$endZ = (int) ($cz + $size);

			for($px = $startX; $px <= $endX; ++$px){
				$sizeX = ($px + 0.5 - $cx) / $size;
				$sizeX2 = $sizeX * $sizeX;
				if($sizeX2 >= 1.0){
					continue;
				}

				for($py = $startY; $py <= $endY; ++$py){
					if($py < -64 || $py > 319){
						continue;
					}
					$sizeY = ($py + 0.5 - $cy) / $size;
					$sizeY2 = $sizeY * $sizeY;
					if($sizeX2 + $sizeY2 >= 1.0){
						continue;
					}

					for($pz = $startZ; $pz <= $endZ; ++$pz){
						$sizeZ = ($pz + 0.5 - $cz) / $size;
						if($sizeX2 + $sizeY2 + $sizeZ * $sizeZ >= 1.0){
							continue;
						}

						$chunkHash = (($px >> 4) << 32) ^ ($pz >> 4);
						if(!array_key_exists($chunkHash, $chunkCache)){
							$chunkCache[$chunkHash] = $world->getChunk($px >> 4, $pz >> 4);
						}
						$targetChunk = $chunkCache[$chunkHash];

						if($targetChunk !== null){
							$stateId = $targetChunk->getBlockStateId($px & 0xf, $py, $pz & 0xf);
							if(isset($this->stoneReplaceableStateIds[$stateId])){
								$targetChunk->setBlockStateId($px & 0xf, $py, $pz & 0xf, $stoneOreStateId);
							}elseif(isset($this->deepslateReplaceableStateIds[$stateId])){
								$targetChunk->setBlockStateId($px & 0xf, $py, $pz & 0xf, $deepslateOreStateId);
							}
						}else{
							$block = $world->getBlockAt($px, $py, $pz);
							$typeId = $block->getTypeId();
							if(isset($this->stoneReplaceableTypeIds[$typeId])){
								$world->setBlockAt($px, $py, $pz, $stoneOre);
							}elseif(isset($this->deepslateReplaceableTypeIds[$typeId])){
								$world->setBlockAt($px, $py, $pz, $deepslateOre);
							}
						}
					}
				}
			}
		}
	}
}
