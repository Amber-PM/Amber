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
use function abs;
use function is_array;
use function max;

/**
 * minecraft:behavior.melee_attack (and delayed_attack, melee_box_attack): chase the target and hit it.
 */
final class MeleeAttackGoal extends Goal{
	private int $nextAttack = 0;
	private int $nextStop = 0;

	public function getFlags() : int{ return self::FLAG_MOVE | self::FLAG_LOOK; }

	public function canStart() : bool{
		$target = $this->mob->getTargetEntity();
		return self::isValidTarget($target) && !$this->mob->isSitting();
	}

	public function start() : void{
		$this->nextStop = $this->now() + $this->stopInterval();
	}

	private function stopInterval() : int{
		$interval = $this->float("random_stop_interval", 0.0);
		return $interval > 0 ? (int) ($interval * 20) : \PHP_INT_MAX >> 1;
	}

	public function tick() : void{
		$target = $this->mob->getTargetEntity();
		if($target === null){
			return;
		}
		$this->mob->lookAt($target->getEyePos());
		$distance = $target->getPosition()->distanceSquared($this->mob->getPosition());
		$width = $this->mob->getSize()->getWidth();
		$reach = ($width * $this->float("reach_multiplier", 2.0)) ** 2 + $target->getSize()->getWidth();
		if($this->now() >= $this->nextStop){
			$this->navigator()->stop();
			$this->nextStop = $this->now() + $this->stopInterval();
		}elseif($distance > $reach * 0.6 || !$this->navigator()->isMoving()){
			$this->navigator()->moveTo($target->getPosition(), $this->speedMultiplier(), $this->now());
		}
		if($distance <= max($reach, 2.0) && $this->now() >= $this->nextAttack && abs($target->getPosition()->y - $this->mob->getPosition()->y) < 2.5){
			$this->nextAttack = $this->now() + max(1, (int) ($this->float("cooldown_time", 1.0) * 20));
			$event = is_array($this->data["on_attack"] ?? null) ? $this->data["on_attack"] : null;
			if($event !== null){
				$this->mob->triggerEventDefinition($event, $target);
			}
			$this->mob->attackEntity($target);
		}
	}

	public function stop() : void{
		$this->navigator()->stop();
	}
}
