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

use pocketmine\addon\AddonMath;
use pocketmine\addon\entity\AddonEntity;
use pocketmine\addon\entity\EntityFilter;
use pocketmine\addon\entity\FilterContext;
use pocketmine\entity\Entity;
use pocketmine\entity\Living;
use pocketmine\math\Vector3;
use pocketmine\math\VoxelRayTrace;
use pocketmine\player\Player;
use function array_is_list;
use function is_array;
use function is_numeric;
use function max;
use function min;
use function mt_rand;

/**
 * One AI behaviour ("minecraft:behavior.*"). The brain runs the most important goals whose control flags do
 * not clash: one goal moves the mob, one turns its head, one chooses its target.
 *
 * Plugins can add their own goals with MobBrain::registerGoal().
 */
abstract class Goal{
	public const FLAG_MOVE = 1;
	public const FLAG_LOOK = 2;
	public const FLAG_TARGET = 4;
	public const FLAG_JUMP = 8;

	private bool $running = false;

	/**
	 * @param mixed[] $data the behavior component's JSON
	 */
	public function __construct(
		protected AddonEntity $mob,
		protected array $data,
		protected int $priority
	){}

	public function getPriority() : int{ return $this->priority; }

	/** Which of the mob's controls this goal takes while running (FLAG_*). */
	abstract public function getFlags() : int;

	/** Checked every couple of ticks while the goal is idle. */
	abstract public function canStart() : bool;

	/** Checked every tick while the goal runs. */
	public function canContinue() : bool{
		return $this->canStart();
	}

	public function start() : void{}

	public function stop() : void{}

	public function tick() : void{}

	/** Whether a more important goal may interrupt this one. */
	public function isInterruptable() : bool{
		return true;
	}

	final public function isRunning() : bool{ return $this->running; }

	/** @internal */
	final public function setRunning(bool $running) : void{ $this->running = $running; }

	protected function navigator() : Navigator{
		return $this->mob->getBrain()->getNavigator();
	}

	protected function now() : int{
		return $this->mob->getWorld()->getServer()->getTick();
	}

	protected function float(string $key, float $default) : float{
		$value = $this->data[$key] ?? null;
		return is_numeric($value) ? (float) $value : $default;
	}

	/** A number or a [min, max] range, in whatever unit the JSON uses. */
	protected function range(string $key, float $min, float $max) : float{
		$value = $this->data[$key] ?? null;
		if(is_numeric($value)){
			return (float) $value;
		}
		if(is_array($value)){
			$a = is_numeric($value[0] ?? $value["range_min"] ?? null) ? (float) ($value[0] ?? $value["range_min"]) : $min;
			$b = is_numeric($value[1] ?? $value["range_max"] ?? null) ? (float) ($value[1] ?? $value["range_max"]) : $a;
			return $a + AddonMath::randomFloat() * max(0.0, $b - $a);
		}
		return $min + AddonMath::randomFloat() * max(0.0, $max - $min);
	}

	protected function speedMultiplier(float $default = 1.0) : float{
		return $this->float("speed_multiplier", $default);
	}

	/**
	 * The entity_types list of a behavior, each with its filters and its own distance.
	 *
	 * @return list<array{filters: mixed, max_dist: float, must_see: bool, walk: float, sprint: float, sprint_dist: float}>
	 */
	protected function entityTypes(float $defaultDist) : array{
		$types = $this->data["entity_types"] ?? null;
		if(!is_array($types)){
			return [];
		}
		$out = [];
		foreach(array_is_list($types) ? $types : [$types] as $type){
			if(!is_array($type)){
				continue;
			}
			$out[] = [
				"filters" => $type["filters"] ?? null,
				"max_dist" => is_numeric($type["max_dist"] ?? null) ? (float) $type["max_dist"] : $defaultDist,
				"must_see" => (bool) ($type["must_see"] ?? $this->data["must_see"] ?? false),
				"walk" => is_numeric($type["walk_speed_multiplier"] ?? null) ? (float) $type["walk_speed_multiplier"] : 1.0,
				"sprint" => is_numeric($type["sprint_speed_multiplier"] ?? null) ? (float) $type["sprint_speed_multiplier"] : 1.0,
				"sprint_dist" => is_numeric($type["sprint_distance"] ?? null) ? (float) $type["sprint_distance"] : 7.0,
			];
		}
		return $out;
	}

	/**
	 * The nearest entity matching one of the entity types.
	 *
	 * @param list<array{filters: mixed, max_dist: float, must_see: bool, walk: float, sprint: float, sprint_dist: float}> $types
	 * @return array{Entity, int}|null the entity and the index of the type it matched
	 */
	protected function findNearest(array $types) : ?array{
		if($types === []){
			return null;
		}
		$reach = 0.0;
		foreach($types as $type){
			$reach = max($reach, $type["max_dist"]);
		}
		$pos = $this->mob->getPosition();
		$best = null;
		$bestDist = \PHP_FLOAT_MAX;
		foreach($this->mob->getWorld()->getNearbyEntities($this->mob->getBoundingBox()->expandedCopy($reach, $reach / 2 + 2, $reach), $this->mob) as $candidate){
			if(!self::isValidTarget($candidate)){
				continue;
			}
			$dist = $candidate->getPosition()->distanceSquared($pos);
			if($dist >= $bestDist){
				continue;
			}
			foreach($types as $index => $type){
				if($dist > $type["max_dist"] ** 2){
					continue;
				}
				if(!EntityFilter::test($type["filters"], new FilterContext($this->mob, $candidate), $this->mob->reporter())){
					continue;
				}
				if($type["must_see"] && !$this->canSee($candidate)){
					continue;
				}
				$best = [$candidate, $index];
				$bestDist = $dist;
				break;
			}
		}
		return $best;
	}

	/** Alive, in the world, and not a creative/spectator player. */
	public static function isValidTarget(?Entity $entity) : bool{
		if($entity === null || $entity->isClosed() || !$entity->isAlive() || !$entity instanceof Living){
			return false;
		}
		return !$entity instanceof Player || ($entity->hasFiniteResources() && !$entity->isSpectator());
	}

	/** A block-accurate line of sight from eye to eye. */
	protected function canSee(Entity $target) : bool{
		$world = $this->mob->getWorld();
		foreach(VoxelRayTrace::betweenPoints($this->mob->getEyePos(), $target->getEyePos()) as $vector){
			$block = $world->getBlockAt((int) $vector->x, (int) $vector->y, (int) $vector->z);
			if($block->isSolid() && !$block->isTransparent()){
				return false;
			}
		}
		return true;
	}

	/** A random point up to xz blocks around (and y up or down), for strolling and fleeing. */
	protected function randomPoint(Vector3 $around, int $xz, int $y, ?Vector3 $awayFrom = null) : Vector3{
		$dx = mt_rand(-$xz, $xz);
		$dz = mt_rand(-$xz, $xz);
		if($awayFrom !== null){
			$ax = $around->x - $awayFrom->x;
			$az = $around->z - $awayFrom->z;
			if($ax * $dx + $az * $dz < 0){
				$dx = -$dx;
				$dz = -$dz;
			}
		}
		return $around->add($dx, mt_rand(-$y, $y), $dz);
	}

	/** A per-tick probability, checked once per poll (MobBrain::POLL_TICKS ticks), so it is scaled to match. */
	protected function chance(float $chance) : bool{
		return EntityFilter::chance(min(1.0, $chance * MobBrain::POLL_TICKS));
	}
}
