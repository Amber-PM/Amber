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

namespace pocketmine\world\spawner;

use pocketmine\block\tile\MonsterSpawner as TileMonsterSpawner;
use pocketmine\block\tile\Tile;
use pocketmine\entity\EntityFactory;
use pocketmine\entity\Living;
use pocketmine\entity\Location;
use pocketmine\math\Vector3;
use pocketmine\world\particle\FlameParticle;
use pocketmine\world\particle\SmokeParticle;
use pocketmine\world\World;
use function count;
use function mt_rand;

final class MobSpawnerTicker{

	/** @var array<int, TileMonsterSpawner> blockHash => Tile */
	private array $spawners = [];
	/** @var array<int, array<int, bool>> chunkHash => [blockHash => true] */
	private array $spawnersByChunk = [];
	private ?int $lastTick = null;

	public function __construct(private World $world){}

	public function onTileAdded(Tile $tile) : void{
		if($tile instanceof TileMonsterSpawner){
			$pos = $tile->getPosition();
			$hash = World::blockHash($pos->getFloorX(), $pos->getFloorY(), $pos->getFloorZ());
			$chunk = World::chunkHash($pos->getFloorX() >> 4, $pos->getFloorZ() >> 4);

			$this->spawners[$hash] = $tile;
			$this->spawnersByChunk[$chunk][$hash] = true;
		}
	}

	public function onTileRemoved(Tile $tile) : void{
		if($tile instanceof TileMonsterSpawner){
			$pos = $tile->getPosition();
			$hash = World::blockHash($pos->getFloorX(), $pos->getFloorY(), $pos->getFloorZ());
			$chunk = World::chunkHash($pos->getFloorX() >> 4, $pos->getFloorZ() >> 4);

			$this->forgetPosition($hash, $chunk);
		}
	}

	private function forgetPosition(int $hash, int $chunk) : void{
		unset($this->spawners[$hash], $this->spawnersByChunk[$chunk][$hash]);
		if(($this->spawnersByChunk[$chunk] ?? []) === []){
			unset($this->spawnersByChunk[$chunk]);
		}
	}

	public function onChunkUnloaded(int $chunkX, int $chunkZ) : void{
		$chunk = World::chunkHash($chunkX, $chunkZ);
		foreach($this->spawnersByChunk[$chunk] ?? [] as $hash => $_){
			unset($this->spawners[$hash]);
		}
		unset($this->spawnersByChunk[$chunk]);
	}

	public function clear() : void{
		$this->spawners = [];
		$this->spawnersByChunk = [];
		$this->lastTick = null;
	}

	public function getSpawnerCount() : int{
		return count($this->spawners);
	}

	public function tick(int $currentTick) : void{
		if($this->lastTick === $currentTick){
			return;
		}
		$this->lastTick = $currentTick;

		if($this->spawners === []){
			return;
		}

		foreach($this->spawners as $hash => $tile){
			if($tile->isClosed()){
				World::getBlockXYZ($hash, $x, $y, $z);
				$this->forgetPosition($hash, World::chunkHash($x >> 4, $z >> 4));
				continue;
			}

			$pos = $tile->getPosition();
			$chunkX = $pos->getFloorX() >> 4;
			$chunkZ = $pos->getFloorZ() >> 4;
			if(!$this->world->isChunkLoaded($chunkX, $chunkZ)){
				$this->forgetPosition($hash, World::chunkHash($chunkX, $chunkZ));
				continue;
			}

			// Active only when player is within required player range (16 blocks in BDS)
			if(!$tile->hasNearbyPlayer()){
				continue;
			}

			// Active particle effect: flame particles around the spawner cage
			if(($currentTick % 4) === 0){
				$particlePos = new Vector3(
					$pos->x + 0.5 + (mt_rand(-5, 5) / 10.0),
					$pos->y + 0.5 + (mt_rand(-5, 5) / 10.0),
					$pos->z + 0.5 + (mt_rand(-5, 5) / 10.0)
				);
				$this->world->addParticle($particlePos, new FlameParticle());
			}

			if(!$tile->tickSpawnDelay(1)){
				continue;
			}

			// Attempt spawn
			$this->attemptSpawn($tile);
			$tile->resetSpawnDelay();
		}
	}

	public function attemptSpawn(TileMonsterSpawner $tile) : int{
		$pos = $tile->getPosition();
		$entityId = $tile->getEntityId();
		if($entityId === ":" || $entityId === ""){
			return 0;
		}

		// Count existing nearby entities within spawner bounds
		$bb = $tile->getNearbyEntityBoundingBox();
		$nearbyCount = 0;
		foreach($this->world->getNearbyEntities($bb) as $entity){
			if($entity instanceof Living && !$entity->isClosed()){
				$nearbyCount++;
			}
		}

		if($nearbyCount >= $tile->getMaxNearbyEntities()){
			return 0;
		}

		$spawnedCount = 0;
		$range = $tile->getSpawnRange();
		$spawnAttempts = $tile->getSpawnCount();

		for($i = 0; $i < $spawnAttempts; ++$i){
			$targetX = $pos->getFloorX() + mt_rand(-$range, $range);
			$targetY = $pos->getFloorY() + mt_rand(-1, 1);
			$targetZ = $pos->getFloorZ() + mt_rand(-$range, $range);

			if($targetY < $this->world->getMinY() || $targetY + 1 >= $this->world->getMaxY()){
				continue;
			}

			$ground = $this->world->getBlockAt($targetX, $targetY - 1, $targetZ);
			$feet = $this->world->getBlockAt($targetX, $targetY, $targetZ);
			$head = $this->world->getBlockAt($targetX, $targetY + 1, $targetZ);

			if(!$ground->isSolid() || $feet->isSolid() || $head->isSolid()){
				continue;
			}

			// Monster spawner light check: light level must be <= 7 in dungeon
			if($this->world->getBlockLightAt($targetX, $targetY, $targetZ) > 7){
				continue;
			}

			$yaw = (float) mt_rand(0, 360);
			$location = new Location($targetX + 0.5, $targetY, $targetZ + 0.5, $this->world, $yaw, 0.0);

			$entity = EntityFactory::getInstance()->create($entityId, $location);
			if($entity !== null){
				$entity->spawnToAll();
				$this->world->addParticle($location, new SmokeParticle());
				$this->world->addParticle($location, new FlameParticle());
				$spawnedCount++;
				if(++$nearbyCount >= $tile->getMaxNearbyEntities()){
					break;
				}
			}
		}

		return $spawnedCount;
	}
}
