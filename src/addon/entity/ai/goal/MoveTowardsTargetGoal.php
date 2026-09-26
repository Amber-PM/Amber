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

/**
 * minecraft:behavior.move_towards_target: walk to the target without attacking.
 */
final class MoveTowardsTargetGoal extends Goal{
	public function getFlags() : int{ return self::FLAG_MOVE; }

	public function canStart() : bool{
		$target = $this->mob->getTargetEntity();
		return self::isValidTarget($target) && $target->getPosition()->distanceSquared($this->mob->getPosition()) <= $this->float("within_radius", 16.0) ** 2;
	}

	public function tick() : void{
		$target = $this->mob->getTargetEntity();
		if($target !== null){
			$this->navigator()->moveTo($target->getPosition(), $this->speedMultiplier(), $this->now());
		}
	}

	public function stop() : void{
		$this->navigator()->stop();
	}
}
