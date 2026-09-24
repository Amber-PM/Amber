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
use function abs;
use function fmod;
use function max;
use function min;

/**
 * minecraft:behavior.random_look_around: glance in random directions while idle.
 */
final class RandomLookAroundGoal extends Goal{
	private float $yaw = 0.0;
	private int $until = 0;

	public function getFlags() : int{ return self::FLAG_LOOK; }

	public function canStart() : bool{
		return !$this->navigator()->isMoving() && $this->chance($this->float("probability", 0.02));
	}

	public function start() : void{
		$this->yaw = AddonMath::randomFloat() * 360;
		$this->until = $this->now() + (int) ($this->range("look_time", 2, 4) * 20);
	}

	public function canContinue() : bool{
		return $this->now() < $this->until && !$this->navigator()->isMoving();
	}

	public function tick() : void{
		$location = $this->mob->getLocation();
		$delta = fmod($this->yaw - $location->yaw + 540, 360) - 180;
		if(abs($delta) > 1){
			$this->mob->setRotation($location->yaw + max(-10, min(10, $delta)), 0.0);
		}
	}
}
