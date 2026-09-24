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
use function sqrt;

/**
 * minecraft:behavior.leap_at_target: pounce on the target from a few blocks away.
 */
final class LeapAtTargetGoal extends Goal{
	public function getFlags() : int{ return self::FLAG_JUMP | self::FLAG_MOVE; }

	public function canStart() : bool{
		$target = $this->mob->getTargetEntity();
		if(!self::isValidTarget($target) || !$this->mob->isOnGround()){
			return false;
		}
		$distance = $target->getPosition()->distanceSquared($this->mob->getPosition());
		return $distance >= 4 && $distance <= 16 && $this->chance(0.2);
	}

	public function start() : void{
		$target = $this->mob->getTargetEntity();
		if($target === null){
			return;
		}
		$dx = $target->getPosition()->x - $this->mob->getPosition()->x;
		$dz = $target->getPosition()->z - $this->mob->getPosition()->z;
		$length = sqrt($dx * $dx + $dz * $dz);
		if($length > 0.0001){
			$this->mob->setMotion($this->mob->getMotion()->withComponents($dx / $length * 0.4, $this->float("yd", 0.4), $dz / $length * 0.4));
		}
	}

	public function canContinue() : bool{
		return !$this->mob->isOnGround();
	}
}
