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
use pocketmine\addon\entity\EntityFilter;

/**
 * minecraft:behavior.look_at_player / look_at_entity / look_at_target: turn the head towards a nearby entity.
 */
final class LookAtEntityGoal extends Goal{
	private ?Entity $target = null;
	private int $until = 0;

	public function getFlags() : int{ return self::FLAG_LOOK; }

	public function canStart() : bool{
		if(!$this->chance($this->float("probability", 0.02))){
			return false;
		}
		$distance = $this->float("look_distance", 8.0);
		if(isset($this->data["filters"])){
			$found = $this->findNearest([["filters" => $this->data["filters"], "max_dist" => $distance, "must_see" => false, "walk" => 1.0, "sprint" => 1.0, "sprint_dist" => 0.0]]);
			$this->target = $found[0] ?? null;
		}else{
			$this->target = EntityFilter::nearestPlayer($this->mob, $distance);
		}
		return $this->target !== null;
	}

	public function start() : void{
		$this->until = $this->now() + (int) ($this->range("look_time", 2, 4) * 20);
	}

	public function canContinue() : bool{
		return $this->target !== null && !$this->target->isClosed() && $this->now() < $this->until
			&& $this->target->getPosition()->distanceSquared($this->mob->getPosition()) < $this->float("look_distance", 8.0) ** 2 * 1.5;
	}

	public function tick() : void{
		if($this->target !== null && !$this->navigator()->isMoving()){
			$this->mob->lookAt($this->target->getEyePos());
		}
	}

	public function stop() : void{
		$this->target = null;
	}
}
