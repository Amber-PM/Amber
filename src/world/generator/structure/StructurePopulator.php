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

use pocketmine\data\bedrock\BiomeIds;
use pocketmine\utils\Random;
use pocketmine\world\ChunkManager;
use pocketmine\world\format\Chunk;
use pocketmine\world\generator\populator\Populator;
use function in_array;
use function intdiv;

/**
 * Places structures as world population runs. The world is split into square regions of `spacing` chunks per
 * structure type; each region has one candidate chunk, picked from the world seed, and the structure is placed there
 * if the biome and ground suit it. The result depends only on the seed, whatever order chunks are populated in.
 */
final class StructurePopulator implements Populator{
	/**
	 * @var array<int, array{Structure, int, int, list<int>|null}> structure, spacing, separation, biomes (null: any
	 *                                                              land biome)
	 */
	private array $placements = [];

	public function __construct(private int $seed){}

	/**
	 * The structures of the default generator, keyed by name.
	 *
	 * @return array<string, Structure>
	 */
	public static function defaultStructures() : array{
		$structures = [];
		foreach([new DesertWell(), new Igloo(), new Fossil(), new RuinedPortal(), new Boulder(), new Ruins()] as $structure){
			$structures[$structure->getName()] = $structure;
		}
		return $structures;
	}

	public static function createDefault(int $seed) : self{
		$structures = self::defaultStructures();
		$populator = new self($seed);
		$populator->add($structures["desert_well"], 12, 4, [BiomeIds::DESERT]);
		$populator->add($structures["igloo"], 16, 6, [BiomeIds::ICE_PLAINS]);
		$populator->add($structures["fossil"], 20, 8, null);
		$populator->add($structures["ruined_portal"], 24, 8, null);
		$populator->add($structures["boulder"], 6, 2, [BiomeIds::TAIGA, BiomeIds::EXTREME_HILLS, BiomeIds::EXTREME_HILLS_EDGE]);
		$populator->add($structures["ruins"], 18, 6, [BiomeIds::PLAINS, BiomeIds::FOREST, BiomeIds::BIRCH_FOREST]);
		return $populator;
	}

	/**
	 * @param int            $spacing    size of a region, in chunks
	 * @param int            $separation minimum chunks between the structures of neighbouring regions
	 * @param list<int>|null $biomes     biomes the structure may be placed in (null: any biome but oceans and rivers)
	 */
	public function add(Structure $structure, int $spacing, int $separation, ?array $biomes) : void{
		if($spacing < 1 || $separation < 0 || $separation >= $spacing){
			throw new \InvalidArgumentException("Spacing must be positive and larger than separation");
		}
		$this->placements[] = [$structure, $spacing, $separation, $biomes];
	}

	public function populate(ChunkManager $world, int $chunkX, int $chunkZ, Random $random) : void{
		$chunk = $world->getChunk($chunkX, $chunkZ);
		if($chunk === null){
			return;
		}
		$centerX = ($chunkX << Chunk::COORD_BIT_SIZE) + 8;
		$centerZ = ($chunkZ << Chunk::COORD_BIT_SIZE) + 8;
		//the default generator gives every Y the same biome
		$biome = $chunk->getBiomeId(8, 7, 8);

		foreach($this->placements as $index => [$structure, $spacing, $separation, $biomes]){
			if(!$this->isCandidate($index, $spacing, $separation, $chunkX, $chunkZ)){
				continue;
			}
			if($biomes !== null ? !in_array($biome, $biomes, true) : in_array($biome, [BiomeIds::OCEAN, BiomeIds::RIVER], true)){
				continue;
			}
			$placeRandom = new Random($this->regionSeed($index, $chunkX, $chunkZ));
			$structure->place($world, $centerX + $placeRandom->nextRange(-3, 3), $centerZ + $placeRandom->nextRange(-3, 3), $placeRandom);
		}
	}

	/**
	 * The structures this chunk is the candidate chunk for. Whether each was actually placed also depends on the
	 * biome and the ground.
	 *
	 * @return list<Structure>
	 */
	public function getCandidates(int $chunkX, int $chunkZ) : array{
		$candidates = [];
		foreach($this->placements as $index => [$structure, $spacing, $separation]){
			if($this->isCandidate($index, $spacing, $separation, $chunkX, $chunkZ)){
				$candidates[] = $structure;
			}
		}
		return $candidates;
	}

	/** Whether this chunk is the candidate chunk of its region for the placement. */
	public function isCandidate(int $index, int $spacing, int $separation, int $chunkX, int $chunkZ) : bool{
		$regionX = self::floorDiv($chunkX, $spacing);
		$regionZ = self::floorDiv($chunkZ, $spacing);
		$random = new Random($this->regionSeed($index, $regionX, $regionZ));
		$range = $spacing - $separation;
		return $chunkX === $regionX * $spacing + $random->nextBoundedInt($range) &&
			$chunkZ === $regionZ * $spacing + $random->nextBoundedInt($range);
	}

	private function regionSeed(int $index, int $x, int $z) : int{
		//kept well inside 64 bits: an overflowing multiplication would turn into a float
		return ((($x & 0xffffff) * 341873128) ^ (($z & 0xffffff) * 132897987) ^ ($index * 0x5DEECE66D) ^ $this->seed) & 0x7fffffffffff;
	}

	private static function floorDiv(int $a, int $b) : int{
		$q = intdiv($a, $b);
		return ($a % $b !== 0 && ($a < 0) !== ($b < 0)) ? $q - 1 : $q;
	}
}
