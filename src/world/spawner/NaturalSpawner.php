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

use pocketmine\block\VanillaBlocks;
use pocketmine\entity\EntityFactory;
use pocketmine\entity\Living;
use pocketmine\entity\Location;
use pocketmine\player\Player;
use pocketmine\world\World;
use function cos;
use function count;
use function deg2rad;
use function floor;
use function max;
use function mt_rand;
use function sin;

final class NaturalSpawner{

	public const MONSTER_SPAWN_INTERVAL_TICKS = 20; // 1 second
	public const ANIMAL_SPAWN_INTERVAL_TICKS = 400; // 20 seconds
	public const MIN_PLAYER_DISTANCE_SQUARED = 24.0 * 24.0; // 576.0 (exclusion radius)
	public const MAX_PLAYER_DISTANCE = 64; // horizontal search radius

	private MobDespawnEngine $despawnEngine;
	private int $monsterCapMultiplier = 70;
	private int $animalCapMultiplier = 10;
	private bool $enabled = true;

	public function __construct(private World $world){
		$this->despawnEngine = new MobDespawnEngine($world);
	}

	public function isEnabled() : bool{
		return $this->enabled;
	}

	public function setEnabled(bool $enabled = true) : void{
		$this->enabled = $enabled;
	}

	public function getDespawnEngine() : MobDespawnEngine{
		return $this->despawnEngine;
	}

	public function tick(int $currentTick) : void{
		if(!$this->enabled){
			return;
		}

		// Despawn sweep every 20 ticks
		if(($currentTick % self::MONSTER_SPAWN_INTERVAL_TICKS) === 0){
			$this->despawnEngine->tick(self::MONSTER_SPAWN_INTERVAL_TICKS);

			if($this->world->getDifficulty() !== World::DIFFICULTY_PEACEFUL){
				$this->spawnMonsters();
			}
		}

		// Animal spawn cycle every 400 ticks
		if(($currentTick % self::ANIMAL_SPAWN_INTERVAL_TICKS) === 0){
			$this->spawnAnimals();
		}
	}

	public function countEntities(string ...$identifiers) : int{
		$count = 0;
		$idSet = array_flip($identifiers);
		foreach($this->world->getEntities() as $entity){
			if(!$entity instanceof Living || $entity->isClosed() || $entity->isFlaggedForDespawn()){
				continue;
			}
			$saveId = null;
			try{
				$saveId = EntityFactory::getInstance()->getSaveId($entity::class);
			}catch(\InvalidArgumentException){
				// Not a registered factory entity
			}
			if($saveId !== null && isset($idSet[$saveId])){
				$count++;
			}
		}
		return $count;
	}

	public function spawnMonsters() : int{
		$players = [];
		foreach($this->world->getPlayers() as $player){
			if(!$player->isSpectator()){
				$players[] = $player;
			}
		}

		if($players === []){
			return 0;
		}

		$cap = $this->monsterCapMultiplier * count($players);
		$monsterIds = [
			"minecraft:zombie",
			"minecraft:skeleton",
			"minecraft:creeper",
			"minecraft:spider",
			"minecraft:enderman"
		];
		$currentCount = $this->countEntities(...$monsterIds);
		if($currentCount >= $cap){
			return 0;
		}

		$spawnedTotal = 0;
		foreach($players as $player){
			$cand = $this->findCandidatePosition($player);
			if($cand === null){
				continue;
			}
			[$x, $y, $z] = $cand;

			// Bedrock 1.18+ rule: monster requires block light 0
			if($this->world->getBlockLightAt($x, $y, $z) !== 0){
				continue;
			}

			// In daytime, check that sky light is shaded/reduced
			if($this->world->getRealBlockSkyLightAt($x, $y, $z) > 7 && ($this->world->getTime() % 24000) < 12000){
				continue;
			}

			// Select mob type by vanilla weight
			$mobId = $this->pickRandomMonster();
			$herdSize = mt_rand(1, 3);

			for($h = 0; $h < $herdSize; ++$h){
				$hx = $x + mt_rand(-2, 2);
				$hz = $z + mt_rand(-2, 2);
				$hy = $y;

				if(!$this->isValidSpawnLocation($hx, $hy, $hz, $players)){
					continue;
				}

				$location = new Location($hx + 0.5, $hy, $hz + 0.5, $this->world, (float) mt_rand(0, 360), 0.0);
				$entity = EntityFactory::getInstance()->create($mobId, $location);
				if($entity !== null){
					$entity->spawnToAll();
					$spawnedTotal++;
					if(++$currentCount >= $cap){
						return $spawnedTotal;
					}
				}
			}
		}

		return $spawnedTotal;
	}

	public function spawnAnimals() : int{
		$players = [];
		foreach($this->world->getPlayers() as $player){
			if(!$player->isSpectator()){
				$players[] = $player;
			}
		}

		if($players === []){
			return 0;
		}

		$cap = $this->animalCapMultiplier * count($players);
		$animalIds = [
			"minecraft:cow",
			"minecraft:sheep",
			"minecraft:pig",
			"minecraft:chicken"
		];
		$currentCount = $this->countEntities(...$animalIds);
		if($currentCount >= $cap){
			return 0;
		}

		$spawnedTotal = 0;
		foreach($players as $player){
			$cand = $this->findCandidatePosition($player);
			if($cand === null){
				continue;
			}
			[$x, $y, $z] = $cand;

			// Animals must spawn on grass block
			$ground = $this->world->getBlockAt($x, $y - 1, $z);
			if($ground->getTypeId() !== VanillaBlocks::GRASS()->getTypeId()){
				continue;
			}

			// Animals require sky light >= 9
			if($this->world->getRealBlockSkyLightAt($x, $y, $z) < 9){
				continue;
			}

			$animalId = $this->pickRandomAnimal();
			$herdSize = mt_rand(2, 4);

			for($h = 0; $h < $herdSize; ++$h){
				$hx = $x + mt_rand(-2, 2);
				$hz = $z + mt_rand(-2, 2);
				$hy = $y;

				if(!$this->isValidSpawnLocation($hx, $hy, $hz, $players)){
					continue;
				}

				if($this->world->getBlockAt($hx, $hy - 1, $hz)->getTypeId() !== VanillaBlocks::GRASS()->getTypeId()){
					continue;
				}

				$location = new Location($hx + 0.5, $hy, $hz + 0.5, $this->world, (float) mt_rand(0, 360), 0.0);
				$entity = EntityFactory::getInstance()->create($animalId, $location);
				if($entity !== null){
					$entity->spawnToAll();
					$spawnedTotal++;
					if(++$currentCount >= $cap){
						return $spawnedTotal;
					}
				}
			}
		}

		return $spawnedTotal;
	}

	/**
	 * @return array{int, int, int}|null
	 */
	public function findCandidatePosition(Player $player) : ?array{
		$playerPos = $player->getPosition();
		$angle = deg2rad((float) mt_rand(0, 359));
		$dist = (float) mt_rand(24, self::MAX_PLAYER_DISTANCE);

		$candX = (int) floor($playerPos->x + cos($angle) * $dist);
		$candZ = (int) floor($playerPos->z + sin($angle) * $dist);

		$chunkX = $candX >> 4;
		$chunkZ = $candZ >> 4;
		if(!$this->world->isChunkLoaded($chunkX, $chunkZ)){
			return null;
		}

		$highestY = $this->world->getHighestBlockAt($candX, $candZ);
		if($highestY === null || $highestY >= $this->world->getMaxY() - 2){
			return null;
		}

		$candY = $highestY + 1;
		if(!$this->isValidSpawnLocation($candX, $candY, $candZ, [$player])){
			return null;
		}

		return [$candX, $candY, $candZ];
	}

	/**
	 * @param Player[] $players
	 */
	public function isValidSpawnLocation(int $x, int $y, int $z, array $players) : bool{
		if($y < $this->world->getMinY() || $y + 1 >= $this->world->getMaxY()){
			return false;
		}

		$ground = $this->world->getBlockAt($x, $y - 1, $z);
		$feet = $this->world->getBlockAt($x, $y, $z);
		$head = $this->world->getBlockAt($x, $y + 1, $z);

		if(!$ground->isSolid() || $feet->isSolid() || $head->isSolid()){
			return false;
		}

		// Ensure no player is within 24 blocks
		foreach($players as $player){
			$pPos = $player->getPosition();
			$dx = $pPos->x - ($x + 0.5);
			$dy = $pPos->y - $y;
			$dz = $pPos->z - ($z + 0.5);
			if(($dx * $dx + $dy * $dy + $dz * $dz) < self::MIN_PLAYER_DISTANCE_SQUARED){
				return false;
			}
		}

		return true;
	}

	private function pickRandomMonster() : string{
		// BDS default weights: Zombie 100, Skeleton 100, Creeper 100, Spider 100, Enderman 10
		$rand = mt_rand(1, 410);
		if($rand <= 100){
			return "minecraft:zombie";
		}
		if($rand <= 200){
			return "minecraft:skeleton";
		}
		if($rand <= 300){
			return "minecraft:creeper";
		}
		if($rand <= 400){
			return "minecraft:spider";
		}
		return "minecraft:enderman";
	}

	private function pickRandomAnimal() : string{
		// BDS default weights: Sheep 12, Cow 10, Pig 10, Chicken 10
		$rand = mt_rand(1, 42);
		if($rand <= 12){
			return "minecraft:sheep";
		}
		if($rand <= 22){
			return "minecraft:cow";
		}
		if($rand <= 32){
			return "minecraft:pig";
		}
		return "minecraft:chicken";
	}
}
