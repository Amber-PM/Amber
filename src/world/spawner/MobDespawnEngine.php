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

use pocketmine\entity\Living;
use pocketmine\player\Player;
use pocketmine\world\World;
use function mt_rand;

final class MobDespawnEngine{

	public const INSTANT_DESPAWN_DISTANCE_SQUARED = 128.0 * 128.0; // 16384.0
	public const RANDOM_DESPAWN_MIN_DISTANCE_SQUARED = 32.0 * 32.0; // 1024.0
	public const MIN_AGE_FOR_RANDOM_DESPAWN_TICKS = 600; // 30 seconds

	public function __construct(private World $world){}

	/**
	 * Runs a despawn check across all living entities in the world.
	 *
	 * @param int $intervalTicks The tick interval this check runs at (default 20 ticks / 1 second).
	 * @return int Number of entities despawned.
	 */
	public function tick(int $intervalTicks = 20) : int{
		$players = [];
		foreach($this->world->getPlayers() as $player){
			if(!$player->isSpectator()){
				$players[] = $player;
			}
		}

		$despawnedCount = 0;
		// Bedrock 1 in 800 chance per tick. For a periodic check every $intervalTicks, chance is 800 / $intervalTicks.
		$randomChanceDenominator = (int) max(1, 800 / max(1, $intervalTicks));

		foreach($this->world->getEntities() as $entity){
			if(!$entity instanceof Living || $entity instanceof Player || $entity->isClosed() || $entity->isFlaggedForDespawn()){
				continue;
			}

			// Nametagged or explicitly persistent mobs never despawn naturally
			if($entity->isPersistent() || $entity->getNameTag() !== ""){
				continue;
			}

			// If no survival/adventure players in world, despawn instantly
			if($players === []){
				$entity->flagForDespawn();
				$despawnedCount++;
				continue;
			}

			$minDistanceSq = null;
			$entityPos = $entity->getPosition();
			foreach($players as $player){
				$distSq = $entityPos->distanceSquared($player->getPosition());
				if($minDistanceSq === null || $distSq < $minDistanceSq){
					$minDistanceSq = $distSq;
				}
			}

			if($minDistanceSq === null){
				continue;
			}

			// > 128 blocks from nearest player: immediate despawn
			if($minDistanceSq > self::INSTANT_DESPAWN_DISTANCE_SQUARED){
				$entity->flagForDespawn();
				$despawnedCount++;
			}elseif($minDistanceSq > self::RANDOM_DESPAWN_MIN_DISTANCE_SQUARED){
				// 32 to 128 blocks: 1 in 800 chance per tick after 30 seconds
				if($entity->ticksLived >= self::MIN_AGE_FOR_RANDOM_DESPAWN_TICKS){
					if(mt_rand(1, $randomChanceDenominator) === 1){
						$entity->flagForDespawn();
						$despawnedCount++;
					}
				}
			}
		}

		return $despawnedCount;
	}
}
