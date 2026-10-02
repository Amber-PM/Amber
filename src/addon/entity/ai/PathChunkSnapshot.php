<?php

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketMine Team
 * @link http://www.pocketmine.net/
 *
 *
 */

declare(strict_types=1);

namespace pocketmine\addon\entity\ai;

use pocketmine\world\format\Chunk;
use pocketmine\world\format\io\FastChunkSerializer;
use pocketmine\world\World;
use function igbinary_serialize;
use function igbinary_unserialize;
use function is_array;
use function is_int;
use function is_string;

/**
 * A copy of the chunks around a path search, for running the search in an async worker.
 */
final class PathChunkSnapshot{
	/**
	 * @param Chunk[] $chunks
	 * @phpstan-param array<int, Chunk> $chunks chunk hash => chunk
	 */
	public function __construct(
		private array $chunks,
		private int $minY,
		private int $maxY
	){}

	public function getMinY() : int{ return $this->minY; }

	public function getMaxY() : int{ return $this->maxY; }

	public function getChunk(int $chunkX, int $chunkZ) : ?Chunk{
		return $this->chunks[World::chunkHash($chunkX, $chunkZ)] ?? null;
	}

	/**
	 * Copies the loaded chunks overlapping the given block area, with the path class of every block state in them.
	 *
	 * @return string payload for fromPayload()
	 */
	public static function capture(World $world, int $minX, int $minZ, int $maxX, int $maxZ) : string{
		$chunks = [];
		$states = [];
		for($chunkX = $minX >> Chunk::COORD_BIT_SIZE; $chunkX <= $maxX >> Chunk::COORD_BIT_SIZE; ++$chunkX){
			for($chunkZ = $minZ >> Chunk::COORD_BIT_SIZE; $chunkZ <= $maxZ >> Chunk::COORD_BIT_SIZE; ++$chunkZ){
				$chunk = $world->getChunk($chunkX, $chunkZ);
				if($chunk === null){
					continue;
				}
				$chunks[World::chunkHash($chunkX, $chunkZ)] = FastChunkSerializer::serializeTerrain($chunk);
				foreach($chunk->getSubChunks() as $subChunk){
					foreach($subChunk->getBlockLayers() as $layer){
						foreach($layer->getPalette() as $state){
							$states[$state] ??= Pathfinder::classOf($state);
						}
					}
				}
			}
		}
		return (string) igbinary_serialize([$chunks, $states, $world->getMinY(), $world->getMaxY()]);
	}

	/**
	 * Rebuilds the snapshot in the thread running the search. Block state classes computed by the thread that
	 * took the snapshot are used, since only it knows every block (add-on blocks are registered there only).
	 */
	public static function fromPayload(string $payload) : self{
		$decoded = igbinary_unserialize($payload);
		if(!is_array($decoded) || !is_array($decoded[0] ?? null) || !is_array($decoded[1] ?? null) || !is_int($decoded[2] ?? null) || !is_int($decoded[3] ?? null)){
			throw new \InvalidArgumentException("Invalid path snapshot payload");
		}
		$chunks = [];
		foreach($decoded[0] as $hash => $data){
			if(!is_int($hash) || !is_string($data)){
				throw new \InvalidArgumentException("Invalid path snapshot payload");
			}
			$chunks[$hash] = FastChunkSerializer::deserializeTerrain($data);
		}
		foreach($decoded[1] as $state => $class){
			if(!is_int($state) || !is_int($class)){
				throw new \InvalidArgumentException("Invalid path snapshot payload");
			}
			Pathfinder::setClassOf($state, $class);
		}
		return new self($chunks, $decoded[2], $decoded[3]);
	}
}
