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

namespace pocketmine\event\block;

use pocketmine\block\tile\MonsterSpawner as TileMonsterSpawner;
use pocketmine\entity\Entity;
use pocketmine\event\Cancellable;
use pocketmine\event\CancellableTrait;
use pocketmine\world\Position;

class SpawnerSpawnEvent extends BlockEvent implements Cancellable{
	use CancellableTrait;

	public function __construct(
		private TileMonsterSpawner $spawner,
		private Entity $entity,
		private Position $spawnPosition
	){
		parent::__construct($spawner->getBlock());
	}

	public function getSpawnerTile() : TileMonsterSpawner{
		return $this->spawner;
	}

	public function getEntity() : Entity{
		return $this->entity;
	}

	public function getSpawnPosition() : Position{
		return $this->spawnPosition;
	}
}
