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
use pocketmine\player\Player;
use pocketmine\world\particle\AngryVillagerParticle;
use pocketmine\world\particle\HeartParticle;
use function array_is_list;
use function is_array;
use function is_numeric;
use function max;
use function mt_rand;

/**
 * Riding behaviors:
 * - find_mount: walk to a nearby mob that would carry this one, and climb on (jockeys);
 * - mount_pathing: a mount carrying a mob heads for its rider's target;
 * - run_around_like_crazy: a wild minecraft:tamemount mob bucks around under a player until it is tamed
 *   (its temper, raised by each attempt, against max_temper) or throws the player off.
 */
final class MountGoal extends Goal{
	public const FIND = "find";
	public const PATHING = "pathing";
	public const CRAZY = "crazy";

	private ?AddonEntity $mount = null;
	private int $failures = 0;
	private int $decideAt = 0;
	private int $temper = 0;

	/** @param mixed[] $data */
	public function __construct(AddonEntity $mob, array $data, int $priority, private string $mode){
		parent::__construct($mob, $data, $priority);
	}

	public function getFlags() : int{ return self::FLAG_MOVE; }

	public function canStart() : bool{
		return match($this->mode){
			self::FIND => $this->findMount(),
			self::PATHING => $this->riderTarget() !== null,
			default => $this->wildRider() !== null,
		};
	}

	private function findMount() : bool{
		if(AddonEntity::getVehicleOf($this->mob) !== null || $this->failures > (int) $this->float("max_failed_attempts", 20) || $this->mob->isSitting()){
			return false;
		}
		if((bool) ($this->data["target_needed"] ?? false) && $this->mob->getTargetEntity() === null){
			return false;
		}
		if(!$this->chance(0.05)){
			return false;
		}
		$radius = $this->float("within_radius", 0) > 0 ? $this->float("within_radius", 0) : 16.0;
		$best = null;
		$bestDist = \PHP_FLOAT_MAX;
		foreach($this->mob->getWorld()->getNearbyEntities($this->mob->getBoundingBox()->expandedCopy($radius, $radius / 2, $radius), $this->mob) as $entity){
			if($entity instanceof AddonEntity && $entity->canAddRider($this->mob)){
				$d = $entity->getPosition()->distanceSquared($this->mob->getPosition());
				if($d < $bestDist){
					$best = $entity;
					$bestDist = $d;
				}
			}
		}
		$this->mount = $best;
		if($best === null){
			$this->failures++;
		}
		return $best !== null;
	}

	private function riderTarget() : ?Entity{
		foreach($this->mob->getRiders() as $rider){
			if($rider instanceof AddonEntity){
				$target = $rider->getTargetEntity();
				return self::isValidTarget($target) ? $target : null;
			}
		}
		return null;
	}

	private function wildRider() : ?Player{
		if($this->mob->isTamed() || $this->mob->getComponent("minecraft:tamemount") === null){
			return null;
		}
		foreach($this->mob->getRiders() as $rider){
			if($rider instanceof Player){
				return $rider;
			}
		}
		return null;
	}

	public function start() : void{
		$this->decideAt = $this->now() + mt_rand(20, 60);
	}

	public function canContinue() : bool{
		return match($this->mode){
			self::FIND => $this->mount !== null && !$this->mount->isClosed() && AddonEntity::getVehicleOf($this->mob) === null && $this->mount->canAddRider($this->mob),
			self::PATHING => $this->riderTarget() !== null,
			default => $this->wildRider() !== null,
		};
	}

	public function tick() : void{
		if($this->mode === self::FIND){
			$mount = $this->mount;
			if($mount === null){
				return;
			}
			if($mount->getPosition()->distance($this->mob->getPosition()) <= $mount->getSize()->getWidth() + 1.2){
				$mount->addRider($this->mob);
				$this->mount = null;
				return;
			}
			$this->navigator()->moveTo($mount->getPosition(), $this->speedMultiplier(), $this->now());
			return;
		}
		if($this->mode === self::PATHING){
			$target = $this->riderTarget();
			if($target !== null){
				$this->navigator()->moveTo($target->getPosition(), $this->speedMultiplier(), $this->now());
			}
			return;
		}
		$rider = $this->wildRider();
		if($rider === null){
			return;
		}
		if(!$this->navigator()->isMoving()){
			$this->navigator()->moveTo($this->randomPoint($this->mob->getPosition(), 5, 1), $this->speedMultiplier(), $this->now());
		}
		if($this->now() < $this->decideAt){
			return;
		}
		$this->decideAt = $this->now() + mt_rand(20, 60);
		$tame = is_array($this->mob->getComponent("minecraft:tamemount")) ? $this->mob->getComponent("minecraft:tamemount") : [];
		$max = is_numeric($tame["max_temper"] ?? null) ? (int) $tame["max_temper"] : 100;
		if($this->temper === 0){
			$this->temper = is_numeric($tame["min_temper"] ?? null) ? (int) $tame["min_temper"] : 0;
		}
		$world = $this->mob->getWorld();
		$above = $this->mob->getPosition()->add(0, $this->mob->getSize()->getHeight(), 0);
		if(mt_rand(0, max(1, $max)) < $this->temper){
			$this->mob->tameBy($rider);
			$world->addParticle($above, new HeartParticle());
			$events = $tame["tame_event"] ?? null;
			foreach(is_array($events) && array_is_list($events) ? $events : [$events] as $event){
				$this->mob->triggerEventDefinition($event, $rider);
			}
			return;
		}
		$this->temper += is_numeric($tame["attempt_temper_mod"] ?? null) ? (int) $tame["attempt_temper_mod"] : 5;
		$this->mob->removeRider($rider);
		$world->addParticle($above, new AngryVillagerParticle());
	}

	public function stop() : void{
		$this->mount = null;
		$this->navigator()->stop();
	}
}
