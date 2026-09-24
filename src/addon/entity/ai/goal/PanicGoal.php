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
use function is_array;

/**
 * minecraft:behavior.panic: run away after being hurt (or while burning).
 */
final class PanicGoal extends Goal{
	private int $until = 0;

	public function getFlags() : int{ return self::FLAG_MOVE; }

	public function canStart() : bool{
		$recently = $this->now() - $this->mob->getLastHurtTick() < 20;
		if(!$recently && !($this->mob->isOnFire() && !(bool) ($this->data["ignore_mob_damage"] ?? false))){
			return false;
		}
		$cause = $this->mob->getLastHurtCause();
		$sources = $this->data["damage_sources"] ?? null;
		if(is_array($sources) && $cause !== null){
			$ok = false;
			foreach($sources as $source){
				$id = \pocketmine\addon\entity\EntityFilter::DAMAGE_CAUSES[(string) $source] ?? null;
				if($id === -1 || $id === $cause){
					$ok = true;
				}
			}
			if(!$ok){
				return false;
			}
		}
		return true;
	}

	public function start() : void{
		$damager = $this->mob->getLastDamager();
		$target = $this->randomPoint($this->mob->getPosition(), 5, 2, $damager?->getPosition());
		$this->navigator()->moveTo($target, $this->speedMultiplier(1.25), $this->now());
		$this->until = $this->now() + 60;
	}

	public function canContinue() : bool{
		if(!$this->navigator()->isMoving() && $this->now() < $this->until){
			$this->start();
		}
		return $this->now() < $this->until || (bool) ($this->data["force"] ?? false) && $this->mob->isOnFire();
	}

	public function stop() : void{
		$this->navigator()->stop();
	}

	public function isInterruptable() : bool{
		return false;
	}
}
