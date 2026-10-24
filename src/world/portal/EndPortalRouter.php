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

namespace pocketmine\world\portal;

use pocketmine\block\VanillaBlocks;
use pocketmine\entity\Entity;
use pocketmine\player\Player;
use pocketmine\world\Position;
use pocketmine\world\World;

class EndPortalRouter{

	public const END_SPAWN_X = 100.5;
	public const END_SPAWN_Y = 49.0;
	public const END_SPAWN_Z = 0.5;

	public static function generateEndSpawnPlatform(World $endWorld) : Position{
		$obsidian = VanillaBlocks::OBSIDIAN();
		$air = VanillaBlocks::AIR();

		// 5x5 obsidian platform at Y=48
		for($x = 98; $x <= 102; ++$x){
			for($z = -2; $z <= 2; ++$z){
				$endWorld->setBlockAt($x, 48, $z, $obsidian);

				// 3 blocks of air above
				for($y = 49; $y <= 51; ++$y){
					$endWorld->setBlockAt($x, $y, $z, $air);
				}
			}
		}

		return new Position(self::END_SPAWN_X, self::END_SPAWN_Y, self::END_SPAWN_Z, $endWorld);
	}

	public static function routeToEnd(World $endWorld) : Position{
		return self::generateEndSpawnPlatform($endWorld);
	}

	public static function routeToOverworld(Entity $entity, World $overworld) : Position{
		if($entity instanceof Player){
			$spawn = $entity->getSpawn();
			if($spawn !== null && $spawn->getWorld()->getId() === $overworld->getId()){
				return $spawn;
			}
		}

		return $overworld->getSpawnLocation();
	}
}
