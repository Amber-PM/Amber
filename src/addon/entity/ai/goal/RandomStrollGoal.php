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
use pocketmine\math\Vector3;
use function max;

/**
 * minecraft:behavior.random_stroll (and random_swim, random_fly, swim_wander, random_hover): wander to random nearby spots.
 */
final class RandomStrollGoal extends Goal{
	private ?Vector3 $target = null;

	public function getFlags() : int{ return self::FLAG_MOVE; }

	public function canStart() : bool{
		if($this->mob->isSitting() || $this->navigator()->isMoving()){
			return false;
		}
		$interval = max(1.0, $this->float("interval", 120.0));
		if(!$this->chance(1 / $interval)){
			return false;
		}
		$xz = (int) $this->float("xz_dist", 10.0);
		$y = (int) $this->float("y_dist", $this->navigator()->isDirect() ? 7.0 : 3.0);
		$this->target = $this->randomPoint($this->mob->getPosition(), max(1, $xz), max(0, $y));
		$radius = $this->mob->getFeatures()->homeRestriction();
		$home = $this->mob->getFeatures()->getHome();
		if($radius !== null && $home !== null && $this->target->distance($home) > $radius){
			$this->target = $this->randomPoint($home, max(1, (int) $radius), max(0, $y));
		}
		return true;
	}

	public function start() : void{
		if($this->target !== null && !$this->navigator()->moveTo($this->target, $this->speedMultiplier(), $this->now())){
			$this->target = null;
		}
	}

	public function canContinue() : bool{
		return $this->target !== null && $this->navigator()->isMoving();
	}

	public function stop() : void{
		$this->target = null;
		$this->navigator()->stop();
	}
}
