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

namespace pocketmine\addon\entity\ai\goal;

use pocketmine\addon\entity\ai\Goal;
use pocketmine\entity\Entity;

/**
 * minecraft:behavior.avoid_mob_type (and avoid_entity): flee from matching mobs.
 */
final class AvoidMobTypeGoal extends Goal{
	private ?Entity $threat = null;
	private float $walk = 1.0;
	private float $sprint = 1.0;
	private float $sprintDist = 7.0;

	public function getFlags() : int{ return self::FLAG_MOVE; }

	public function canStart() : bool{
		$types = $this->entityTypes($this->float("max_dist", 3.0));
		$found = $this->findNearest($types);
		if($found === null){
			return false;
		}
		[$this->threat, $index] = $found;
		$this->walk = $types[$index]["walk"] * $this->float("walk_speed_multiplier", 1.0);
		$this->sprint = $types[$index]["sprint"] * $this->float("sprint_speed_multiplier", 1.0);
		$this->sprintDist = $types[$index]["sprint_dist"];
		return true;
	}

	public function start() : void{
		$this->flee();
	}

	private function flee() : void{
		if($this->threat === null){
			return;
		}
		$flee = (int) $this->float("max_flee", 10.0);
		$target = $this->randomPoint($this->mob->getPosition(), $flee, 2, $this->threat->getPosition());
		$close = $this->threat->getPosition()->distanceSquared($this->mob->getPosition()) < $this->sprintDist ** 2;
		$this->navigator()->moveTo($target, $close ? $this->sprint : $this->walk, $this->now());
	}

	public function canContinue() : bool{
		if($this->threat === null || $this->threat->isClosed()){
			return false;
		}
		if(!$this->navigator()->isMoving()){
			if($this->threat->getPosition()->distanceSquared($this->mob->getPosition()) > ($this->float("max_dist", 3.0) + 4) ** 2){
				return false;
			}
			$this->flee();
		}
		return true;
	}

	public function stop() : void{
		$this->threat = null;
		$this->navigator()->stop();
	}
}
