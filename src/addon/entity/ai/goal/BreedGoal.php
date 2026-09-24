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

use pocketmine\addon\entity\AddonEntity;
use pocketmine\addon\entity\ai\Goal;

/**
 * minecraft:behavior.breed: two mobs in love find each other and make a baby (minecraft:breedable).
 */
final class BreedGoal extends Goal{
	private ?AddonEntity $mate = null;
	private int $together = 0;

	public function getFlags() : int{ return self::FLAG_MOVE | self::FLAG_LOOK; }

	public function canStart() : bool{
		if(!$this->mob->isInLove()){
			return false;
		}
		foreach($this->mob->getWorld()->getNearbyEntities($this->mob->getBoundingBox()->expandedCopy(8, 3, 8), $this->mob) as $other){
			if($other instanceof AddonEntity && $other->isInLove() && $this->mob->canBreedWith($other)){
				$this->mate = $other;
				return true;
			}
		}
		return false;
	}

	public function start() : void{
		$this->together = 0;
	}

	public function canContinue() : bool{
		return $this->mate !== null && !$this->mate->isClosed() && $this->mate->isAlive() && $this->mob->isInLove() && $this->mate->isInLove();
	}

	public function tick() : void{
		if($this->mate === null){
			return;
		}
		$this->mob->lookAt($this->mate->getEyePos());
		if($this->mate->getPosition()->distanceSquared($this->mob->getPosition()) > 2.25){
			$this->navigator()->moveTo($this->mate->getPosition(), $this->speedMultiplier(), $this->now());
			return;
		}
		$this->navigator()->stop();
		if(++$this->together >= 60){
			$this->mob->breedWith($this->mate);
		}
	}

	public function stop() : void{
		$this->mate = null;
		$this->navigator()->stop();
	}
}
