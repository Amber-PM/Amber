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

namespace pocketmine\block\dispenser;

use pocketmine\block\Block;
use pocketmine\block\tile\Tile;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\world\Position;
use pocketmine\world\World;

final class BlockSource{

	public function __construct(
		private World $world,
		private Vector3 $pos,
		private int $facing,
		private ?Tile $tile = null
	){}

	public function getWorld() : World{
		return $this->world;
	}

	public function getPos() : Vector3{
		return $this->pos;
	}

	public function getPosition() : Position{
		return Position::fromObject($this->pos, $this->world);
	}

	public function getFacing() : int{
		return $this->facing;
	}

	public function getTile() : ?Tile{
		return $this->tile;
	}

	public function getBlock() : Block{
		return $this->world->getBlock($this->pos);
	}

	/**
	 * Returns the spawn location centered just in front of the dispenser block face.
	 */
	public function getDispensePosition() : Vector3{
		[$dx, $dy, $dz] = Facing::OFFSET[$this->facing];
		return $this->pos->add(0.5 + $dx * 0.7, 0.5 + $dy * 0.7, 0.5 + $dz * 0.7);
	}
}
