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

namespace pocketmine\addon\entity\ai;

use pocketmine\addon\AddonTimings;
use pocketmine\addon\entity\AddonEntity;
use pocketmine\math\Vector3;
use function abs;
use function atan2;
use function count;
use function sqrt;
use const M_PI;

/**
 * Moves a mob along a path. Walkers follow A* waypoints; flyers and swimmers steer straight at the goal.
 *
 * The navigator only decides a wanted horizontal velocity (and jumps); AddonEntity applies it in its movement
 * step, after gravity and friction, so knockback and collisions still come from the normal entity physics.
 */
final class Navigator{
	/** Ticks between re-plans while chasing a moving goal. */
	private const REPATH_TICKS = 10;
	/** Ticks without progress after which the path is dropped. */
	private const STUCK_TICKS = 60;

	/** @var list<Vector3> */
	private array $path = [];
	private int $index = 0;
	private ?Vector3 $goal = null;
	private float $speed = 0.0;
	private int $lastPlan = -1000;
	private int $stuckTicks = 0;
	private float $lastDistance = \PHP_FLOAT_MAX;
	private float $wantX = 0.0;
	private float $wantY = 0.0;
	private float $wantZ = 0.0;
	private bool $moving = false;

	public function __construct(
		private AddonEntity $entity,
		private MovementProfile $profile
	){}

	public function getProfile() : MovementProfile{ return $this->profile; }

	public function setProfile(MovementProfile $profile) : void{ $this->profile = $profile; }

	/** Walks towards the goal at speedMultiplier times the mob's movement speed. Returns false if there is no way. */
	public function moveTo(Vector3 $goal, float $speedMultiplier, int $currentTick) : bool{
		$this->speed = $this->profile->speed * $speedMultiplier;
		if($this->profile->direct){
			$this->goal = $goal;
			$this->path = [$goal];
			$this->index = 0;
			$this->moving = true;
			return true;
		}
		$replan = $this->goal === null || $this->path === [] || $this->goal->distanceSquared($goal) > 2.25;
		if($replan && $currentTick - $this->lastPlan >= self::REPATH_TICKS){
			if(!Pathfinder::takeBudget($currentTick)){
				//out of path searches this tick: keep any current path, otherwise try again next tick
				$this->goal = $goal;
				return $this->moving = $this->path !== [];
			}
			$this->lastPlan = $currentTick;
			AddonTimings::$pathfinding->startTiming();
			$path = (new Pathfinder(
				$this->entity->getWorld(),
				Pathfinder::heightInBlocks($this->entity->getSize()->getHeight()),
				$this->profile->canSwim,
				$this->profile->avoidWater,
				$this->profile->maxFall
			))->find($this->entity->getPosition(), $goal, $this->profile->range);
			AddonTimings::$pathfinding->stopTiming();
			if($path === null){
				if($this->path === []){
					$this->stop();
					return false;
				}
			}else{
				$this->path = $path;
				$this->index = 0;
				$this->stuckTicks = 0;
				$this->lastDistance = \PHP_FLOAT_MAX;
			}
		}
		$this->goal = $goal;
		$this->moving = $this->path !== [];
		return $this->moving;
	}

	public function stop() : void{
		$this->path = [];
		$this->index = 0;
		$this->goal = null;
		$this->moving = false;
		$this->wantX = $this->wantY = $this->wantZ = 0.0;
	}

	public function isMoving() : bool{ return $this->moving; }

	public function getGoal() : ?Vector3{ return $this->goal; }

	/** Advances along the path and sets the wanted velocity for this tick. */
	public function tick() : void{
		$this->wantX = $this->wantY = $this->wantZ = 0.0;
		if(!$this->moving){
			return;
		}
		$pos = $this->entity->getPosition();
		while($this->index < count($this->path)){
			$node = $this->path[$this->index];
			$dx = $node->x - $pos->x;
			$dz = $node->z - $pos->z;
			$reach = $this->index === count($this->path) - 1 ? 0.25 : 0.5;
			if($dx * $dx + $dz * $dz <= $reach * $reach && ($this->profile->direct ? abs($node->y - $pos->y) < 1.0 : $pos->y >= $node->y - 0.5)){
				$this->index++;
				continue;
			}
			break;
		}
		if($this->index >= count($this->path)){
			$this->stop();
			return;
		}
		$node = $this->path[$this->index];
		$dx = $node->x - $pos->x;
		$dy = $node->y - $pos->y;
		$dz = $node->z - $pos->z;
		$horizontal = sqrt($dx * $dx + $dz * $dz);

		//stuck detection: no progress towards the waypoint for a while
		if($horizontal < $this->lastDistance - 0.01){
			$this->lastDistance = $horizontal;
			$this->stuckTicks = 0;
		}elseif(++$this->stuckTicks > self::STUCK_TICKS){
			$this->stop();
			return;
		}

		if($horizontal > 0.0001){
			$this->wantX = $dx / $horizontal * $this->speed;
			$this->wantZ = $dz / $horizontal * $this->speed;
			$yaw = atan2($dz, $dx) / M_PI * 180 - 90;
			$this->entity->setRotation($yaw < 0 ? $yaw + 360 : $yaw, $this->entity->getLocation()->pitch);
		}
		if($this->profile->direct){
			$length = sqrt($horizontal * $horizontal + $dy * $dy);
			if($length > 0.0001){
				$this->wantY = $dy / $length * $this->speed;
			}
		}elseif($dy > 0.5 && $this->entity->isOnGround() && $horizontal < 1.5){
			$this->entity->jump();
		}elseif($this->entity->isCollidedHorizontally && $this->entity->isOnGround()){
			$this->entity->jump();
		}
	}

	public function getWantedX() : float{ return $this->wantX; }

	public function getWantedY() : float{ return $this->wantY; }

	public function getWantedZ() : float{ return $this->wantZ; }

	public function isDirect() : bool{ return $this->profile->direct; }
}
