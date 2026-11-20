<?php

/*
 *
 *     _             _
 *    / \   _ __ ___ | |__   ___ _ __
 *   / _ \ | '_ ` _ \| '_ \ / _ \ '__|
 *  / ___ \| | | | | | |_) |  __/ |
 * /_/   \_\_| |_| |_|_.__/ \___|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author AmberPM Team
 * @link https://github.com/Amber-PM/Amber
 *
 *
 */

declare(strict_types=1);

namespace pocketmine\entity;

use pocketmine\player\Player;

/** @phpstan-require-extends Living */
trait HostilePlayerTargeting{

	private int $nextPlayerSearch = 0;

	protected function canTargetPlayer(Player $player) : bool{
		return !$player->isClosed() && $player->isAlive() && !$player->isCreative() && !$player->isSpectator() && $player->getWorld() === $this->getWorld();
	}

	protected function findHostilePlayer(float $range) : ?Player{
		$world = $this->getWorld();
		$rangeSquared = $range ** 2;
		$targetId = $this->getTargetEntityId();
		$target = $targetId !== null ? $world->getEntity($targetId) : null;
		if($target instanceof Player && $this->canTargetPlayer($target) && $this->location->distanceSquared($target->getLocation()) <= $rangeSquared){
			return $target;
		}

		// Avoid repeating an empty chunk search on every tick while the mob is idle.
		if($targetId === null && $this->ticksLived < $this->nextPlayerSearch){
			return null;
		}
		$this->nextPlayerSearch = $this->ticksLived + 10;
		$target = null;
		foreach($world->getNearbyEntities($this->boundingBox->expandedCopy($range, $range, $range), $this) as $nearby){
			if($nearby instanceof Player && $this->canTargetPlayer($nearby)){
				$distanceSquared = $this->location->distanceSquared($nearby->getLocation());
				if($distanceSquared <= $rangeSquared){
					$target = $nearby;
					$rangeSquared = $distanceSquared;
				}
			}
		}

		if($target?->getId() !== $targetId){
			$this->setTargetEntity($target);
		}
		return $target;
	}
}
