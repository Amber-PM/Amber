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

namespace pocketmine\entity\boss;

use pocketmine\entity\Location;
use pocketmine\entity\object\EndCrystal;
use pocketmine\math\Vector3;
use pocketmine\world\World;

class DragonFightManager{

	protected ?EnderDragon $dragon = null;
	protected bool $dragonKilled = false;
	protected bool $previouslyKilled = false;

	/** @var EndCrystal[] */
	protected array $crystals = [];

	public function __construct(
		protected World $world,
		protected Vector3 $exitPortalLocation = new Vector3(0, 65, 0)
	){}

	public function getWorld() : World{
		return $this->world;
	}

	public function getDragon() : ?EnderDragon{
		return $this->dragon;
	}

	public function setDragon(?EnderDragon $dragon) : void{
		$this->dragon = $dragon;
	}

	public function isDragonKilled() : bool{
		return $this->dragonKilled;
	}

	public function setDragonKilled(bool $killed) : void{
		$this->dragonKilled = $killed;
		if($killed){
			$this->previouslyKilled = true;
		}
	}

	public function isPreviouslyKilled() : bool{
		return $this->previouslyKilled;
	}

	/**
	 * @return EndCrystal[]
	 */
	public function getCrystals() : array{
		return $this->crystals;
	}

	public function registerCrystal(EndCrystal $crystal) : void{
		$this->crystals[$crystal->getId()] = $crystal;
	}

	public function unregisterCrystal(EndCrystal $crystal) : void{
		unset($this->crystals[$crystal->getId()]);
	}

	public function spawnNewDragon() : EnderDragon{
		$spawnLoc = new Location(
			$this->exitPortalLocation->x,
			$this->exitPortalLocation->y + 20,
			$this->exitPortalLocation->z,
			$this->world,
			0.0,
			0.0
		);
		$dragon = new EnderDragon($spawnLoc);
		$dragon->spawnToAll();
		$this->dragon = $dragon;
		$this->dragonKilled = false;
		return $dragon;
	}

	public function tick() : void{
		if($this->dragon !== null && !$this->dragon->isClosed() && $this->dragon->isAlive()){
			// Check crystal links
			$closestDist = PHP_FLOAT_MAX;
			$closestCrystal = null;
			foreach($this->crystals as $id => $crystal){
				if($crystal->isClosed() || !$crystal->isAlive()){
					unset($this->crystals[$id]);
					continue;
				}
				$dist = $this->dragon->getLocation()->distance($crystal->getLocation());
				if($dist < $closestDist && $dist <= 48.0){
					$closestDist = $dist;
					$closestCrystal = $crystal;
				}
			}
			if($closestCrystal !== null){
				$this->dragon->tickCrystalHealing($closestCrystal);
			}
		}elseif($this->dragon !== null && ($this->dragon->isClosed() || !$this->dragon->isAlive())){
			$this->dragonKilled = true;
			$this->previouslyKilled = true;
			$this->dragon = null;
		}
	}
}
