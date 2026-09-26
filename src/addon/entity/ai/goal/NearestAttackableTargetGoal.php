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
use function max;

/**
 * minecraft:behavior.nearest_attackable_target (and nearest_prioritized_attackable_target): pick a target from entity_types.
 */
final class NearestAttackableTargetGoal extends Goal{
	private int $nextScan = 0;
	private ?Entity $target = null;

	public function getFlags() : int{ return self::FLAG_TARGET; }

	private function types() : array{
		return $this->entityTypes($this->float("within_radius", 0.0) > 0 ? $this->float("within_radius", 16.0) : 16.0);
	}

	public function canStart() : bool{
		if($this->now() < $this->nextScan){
			return false;
		}
		//attack_interval: seconds between target searches (a random chance per tick in the game)
		$this->nextScan = $this->now() + max(10, (int) ($this->float("attack_interval", 0.5) * 20));
		if($this->mob->isTamed() && (bool) ($this->data["must_reach"] ?? false)){
			return false;
		}
		$found = $this->findNearest($this->types());
		$this->target = $found[0] ?? null;
		return $this->target !== null;
	}

	public function start() : void{
		$this->mob->setTargetEntity($this->target);
	}

	public function canContinue() : bool{
		$target = $this->mob->getTargetEntity();
		if(!self::isValidTarget($target) || $target !== $this->target){
			return false;
		}
		$limit = 0.0;
		foreach($this->types() as $type){
			$limit = max($limit, $type["max_dist"]);
		}
		if($target->getPosition()->distanceSquared($this->mob->getPosition()) > ($limit * 1.5) ** 2){
			return false;
		}
		if((bool) ($this->data["reselect_targets"] ?? false) && $this->now() >= $this->nextScan){
			$this->nextScan = $this->now() + 20;
			$found = $this->findNearest($this->types());
			if($found !== null && $found[0] !== $target){
				$this->target = $found[0];
				$this->mob->setTargetEntity($this->target);
			}
		}
		return true;
	}

	public function stop() : void{
		if($this->mob->getTargetEntity() === $this->target){
			$this->mob->setTargetEntity(null);
		}
		$this->target = null;
	}
}
