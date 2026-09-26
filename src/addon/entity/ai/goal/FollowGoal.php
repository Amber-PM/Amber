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
use pocketmine\entity\Entity;

/**
 * minecraft:behavior.follow_parent / follow_owner / follow_mob: stay near a parent, the owner or a nearby mob.
 */
final class FollowGoal extends Goal{
	private ?Entity $leader = null;

	public function __construct(AddonEntity $mob, array $data, int $priority, private string $mode){
		parent::__construct($mob, $data, $priority);
	}

	public function getFlags() : int{ return self::FLAG_MOVE | self::FLAG_LOOK; }

	private function findLeader() : ?Entity{
		$pos = $this->mob->getPosition();
		switch($this->mode){
			case "owner":
				return $this->mob->isTamed() && !$this->mob->isSitting() ? $this->mob->getOwningEntity() : null;
			case "parent":
				if(!$this->mob->isBaby()){
					return null;
				}
				$best = null;
				$bestDist = 16.0 ** 2;
				foreach($this->mob->getWorld()->getNearbyEntities($this->mob->getBoundingBox()->expandedCopy(8, 4, 8), $this->mob) as $other){
					if($other instanceof AddonEntity && !$other->isBaby() && $other->getAddonIdentifier() === $this->mob->getAddonIdentifier() && ($d = $other->getPosition()->distanceSquared($pos)) < $bestDist){
						$best = $other;
						$bestDist = $d;
					}
				}
				return $best;
			default:
				$radius = $this->float("search_range", 8.0);
				foreach($this->mob->getWorld()->getNearbyEntities($this->mob->getBoundingBox()->expandedCopy($radius, 4, $radius), $this->mob) as $other){
					if($other instanceof AddonEntity && $other->getAddonIdentifier() !== $this->mob->getAddonIdentifier()){
						return $other;
					}
				}
				return null;
		}
	}

	private function startDistance() : float{
		return $this->float("start_distance", $this->mode === "owner" ? 10.0 : 3.0);
	}

	public function canStart() : bool{
		$this->leader = $this->findLeader();
		return $this->leader !== null && $this->leader->getPosition()->distanceSquared($this->mob->getPosition()) > $this->startDistance() ** 2;
	}

	public function canContinue() : bool{
		return $this->leader !== null && !$this->leader->isClosed() && $this->leader->getWorld() === $this->mob->getWorld()
			&& $this->leader->getPosition()->distanceSquared($this->mob->getPosition()) > $this->float("stop_distance", 2.0) ** 2
			&& !($this->mode === "owner" && $this->mob->isSitting());
	}

	public function tick() : void{
		if($this->leader === null){
			return;
		}
		$distance = $this->leader->getPosition()->distance($this->mob->getPosition());
		if($this->mode === "owner" && $distance > 16 && $this->leader->isOnGround()){
			$this->mob->teleport($this->leader->getPosition());
			$this->navigator()->stop();
			return;
		}
		$this->mob->lookAt($this->leader->getEyePos());
		$this->navigator()->moveTo($this->leader->getPosition(), $this->speedMultiplier(), $this->now());
	}

	public function stop() : void{
		$this->leader = null;
		$this->navigator()->stop();
	}
}
