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
use pocketmine\addon\entity\EntityFilter;
use pocketmine\addon\script\ScriptHost;
use pocketmine\block\Water;
use pocketmine\math\Vector3;
use function array_is_list;
use function floor;
use function in_array;
use function is_array;
use function is_string;
use function max;
use function mt_rand;
use function str_contains;

/**
 * Behaviors that walk somewhere for a reason:
 * - flee_sun: burning mobs look for shade in daylight;
 * - avoid_block: run from nearby listed blocks;
 * - go_home and move_towards_home_restriction: back to the minecraft:home position.
 */
final class SeekGoal extends Goal{
	public const FLEE_SUN = "flee_sun";
	public const AVOID_BLOCK = "avoid_block";
	public const GO_HOME = "go_home";
	public const HOME_RESTRICTION = "home_restriction";

	private ?Vector3 $goal = null;
	private int $nextCheck = 0;

	/** @param mixed[] $data */
	public function __construct(AddonEntity $mob, array $data, int $priority, private string $mode){
		parent::__construct($mob, $data, $priority);
	}

	public function getFlags() : int{ return self::FLAG_MOVE; }

	private function exposedToSun(Vector3 $at) : bool{
		$world = $this->mob->getWorld();
		$highest = $world->getHighestBlockAt((int) floor($at->x), (int) floor($at->z));
		return EntityFilter::isDay($world) && ($highest === null || $highest < $at->y + 1) && !$world->getBlockAt((int) floor($at->x), (int) floor($at->y), (int) floor($at->z)) instanceof Water;
	}

	public function canStart() : bool{
		if($this->mob->isSitting() || $this->now() < $this->nextCheck){
			return false;
		}
		$this->nextCheck = $this->now() + max(1, (int) $this->float("tick_interval", 1)) * 4;
		$pos = $this->mob->getPosition();
		$this->goal = match($this->mode){
			self::FLEE_SUN => $this->findShade(),
			self::AVOID_BLOCK => $this->awayFromBlock(),
			self::GO_HOME => $this->chance(1 / max(1.0, $this->float("interval", 120))) ? $this->home(0.0) : null,
			default => $this->home($this->mob->getFeatures()->homeRestriction() ?? \PHP_FLOAT_MAX),
		};
		return $this->goal !== null && $this->navigator()->moveTo($this->goal, $this->mode === self::AVOID_BLOCK ? $this->float("sprint_speed_modifier", 1.0) : $this->speedMultiplier(), $this->now());
	}

	private function findShade() : ?Vector3{
		$pos = $this->mob->getPosition();
		if(!$this->exposedToSun($pos) || (!$this->mob->isOnFire() && $this->mob->getComponent("minecraft:burns_in_daylight") === null)){
			return null;
		}
		for($i = 0; $i < 10; $i++){
			$candidate = $this->randomPoint($pos, 10, 3);
			if(!$this->exposedToSun($candidate)){
				return $candidate;
			}
		}
		return null;
	}

	private function awayFromBlock() : ?Vector3{
		$list = $this->data["target_blocks"] ?? [];
		$names = [];
		foreach(is_array($list) ? (array_is_list($list) ? $list : [$list]) : [] as $name){
			if(is_string($name)){
				$names[] = str_contains($name, ":") ? $name : "minecraft:$name";
			}
		}
		if($names === []){
			return null;
		}
		$range = (int) max(1, $this->float("search_range", 8));
		$height = (int) max(0, $this->float("search_height", 2));
		$world = $this->mob->getWorld();
		$pos = $this->mob->getPosition();
		for($i = 0; $i < 16; $i++){
			$x = (int) floor($pos->x) + mt_rand(-$range, $range);
			$y = (int) floor($pos->y) + mt_rand(-$height, $height);
			$z = (int) floor($pos->z) + mt_rand(-$range, $range);
			if($world->isInWorld($x, $y, $z) && in_array(ScriptHost::blockTypeId($world->getBlockAt($x, $y, $z)), $names, true)){
				return $this->randomPoint($pos, $range + 2, 1, new Vector3($x, $y, $z));
			}
		}
		return null;
	}

	/** The home position when the mob is further than $beyond from it. */
	private function home(float $beyond) : ?Vector3{
		$home = $this->mob->getFeatures()->getHome();
		if($home === null || $home->distance($this->mob->getPosition()) <= max($beyond, $this->float("goal_radius", 0.5) + 1)){
			return null;
		}
		return $home;
	}

	public function canContinue() : bool{
		if($this->goal === null){
			return false;
		}
		if($this->mode === self::GO_HOME && $this->goal->distance($this->mob->getPosition()) <= $this->float("goal_radius", 0.5) + 1){
			$home = $this->data["on_home"] ?? null;
			foreach(is_array($home) && array_is_list($home) ? $home : [$home] as $event){
				$this->mob->triggerEventDefinition($event);
			}
			return false;
		}
		return $this->navigator()->isMoving();
	}

	public function stop() : void{
		if($this->mode === self::GO_HOME && $this->goal !== null && $this->goal->distance($this->mob->getPosition()) > $this->float("goal_radius", 0.5) + 1){
			$failed = $this->data["on_failed"] ?? null;
			foreach(is_array($failed) && array_is_list($failed) ? $failed : [$failed] as $event){
				$this->mob->triggerEventDefinition($event);
			}
		}
		$this->goal = null;
		$this->navigator()->stop();
	}
}
