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
use pocketmine\addon\entity\EntityFilter;
use pocketmine\addon\entity\FilterContext;

/**
 * minecraft:behavior.teleport_to_owner: a pet that falls more than 12 blocks behind its owner teleports to it.
 */
final class TeleportToOwnerGoal extends Goal{
	private const DISTANCE = 12;

	private int $readyAt = 0;

	public function getFlags() : int{ return 0; }

	public function canStart() : bool{
		if($this->now() < $this->readyAt || $this->mob->isSitting() || $this->mob->getRiders() !== []){
			return false;
		}
		$owner = $this->mob->getOwningEntity();
		if($owner === null || $owner->getWorld() !== $this->mob->getWorld() || $owner->getPosition()->distanceSquared($this->mob->getPosition()) <= self::DISTANCE ** 2){
			return false;
		}
		return EntityFilter::test($this->data["filters"] ?? null, new FilterContext($this->mob, $owner), $this->mob->reporter());
	}

	public function start() : void{
		$this->readyAt = $this->now() + (int) ($this->float("cooldown", 1.0) * 20);
		$owner = $this->mob->getOwningEntity();
		if($owner !== null){
			$this->navigator()->stop();
			$this->mob->getFeatures()->teleportNear($owner->getPosition(), 2, 2);
		}
	}

	public function canContinue() : bool{ return false; }
}
