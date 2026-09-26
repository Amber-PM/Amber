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

use pocketmine\addon\AddonMath;
use pocketmine\addon\entity\ai\Goal;
use function max;

/**
 * minecraft:behavior.ranged_attack: keep the target in range and shoot at it.
 */
final class RangedAttackGoal extends Goal{
	private int $nextShot = 0;
	private int $burstLeft = 0;

	public function getFlags() : int{ return self::FLAG_MOVE | self::FLAG_LOOK; }

	public function canStart() : bool{
		return self::isValidTarget($this->mob->getTargetEntity()) && !$this->mob->isSitting();
	}

	public function start() : void{
		$this->nextShot = $this->now() + (int) ($this->range("attack_interval_min", 1, 3) * 20);
		$this->burstLeft = max(1, (int) $this->float("burst_shots", 1.0));
	}

	public function tick() : void{
		$target = $this->mob->getTargetEntity();
		if($target === null){
			return;
		}
		$this->mob->lookAt($target->getEyePos());
		$radius = $this->float("attack_radius", 15.0);
		$distance = $target->getPosition()->distanceSquared($this->mob->getPosition());
		$visible = $this->canSee($target);
		if($distance > ($radius * 0.8) ** 2 || !$visible){
			$this->navigator()->moveTo($target->getPosition(), $this->speedMultiplier(), $this->now());
		}else{
			$this->navigator()->stop();
		}
		if($visible && $distance <= $radius ** 2 && $this->now() >= $this->nextShot){
			$this->mob->shootAt($target, $this->float("charge_shoot_trigger", 0.0));
			if(--$this->burstLeft > 0){
				$this->nextShot = $this->now() + max(1, (int) ($this->float("burst_interval", 0.1) * 20));
			}else{
				$this->burstLeft = max(1, (int) $this->float("burst_shots", 1.0));
				$min = $this->float("attack_interval_min", $this->float("attack_interval", 1.0));
				$max = $this->float("attack_interval_max", $min);
				$this->nextShot = $this->now() + max(1, (int) (($min + AddonMath::randomFloat() * max(0.0, $max - $min)) * 20));
			}
		}
	}

	public function stop() : void{
		$this->navigator()->stop();
	}
}
