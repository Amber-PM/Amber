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

namespace pocketmine\world\redstone;

use pocketmine\block\Block;
use pocketmine\block\RedstoneRepeater;
use pocketmine\block\RedstoneWire;
use pocketmine\block\Slab;
use pocketmine\block\utils\RedstoneSource;
use pocketmine\block\utils\SlabType;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\world\World;
use function array_fill_keys;
use function array_key_first;
use function array_key_last;
use function array_keys;
use function array_pop;
use function array_shift;
use function count;
use function in_array;
use function max;
use function min;

/**
 * Power along redstone wire: a change to one wire recomputes its whole connected network at once, so a long line
 * settles in one step instead of one wire per tick.
 *
 * @phpstan-type MergingSource array{
 *     phase?: int,
 *     wires: array<int, array{int, int, int}>,
 *     edges?: array<int, list<int>>,
 *     pending?: list<int>,
 *     mergingSources?: array<int, mixed>
 * }
 * @phpstan-type ContinuationState array{
 *     phase: int,
 *     wires: array<int, array{int, int, int}>,
 *     edges?: array<int, list<int>>,
 *     pending: list<int>,
 *     sourceQueue: list<int>,
 *     power: array<int, int>,
 *     buckets?: array<int, list<int>>,
 *     propagateLevel: int,
 *     applyQueue: list<int>,
 *     mutated: bool,
 *     mergingSources: array<int, MergingSource>
 * }
 */
final class WireNetwork{
	private const MAX_WIRES = 4096;
	private const HORIZONTAL = [Facing::NORTH, Facing::SOUTH, Facing::WEST, Facing::EAST];

	public const PHASE_DISCOVER = 0;
	public const PHASE_SOURCES = 1;
	public const PHASE_PROPAGATE = 2;
	public const PHASE_APPLY = 3;
	public const PHASE_MERGE = 4;

	/** @var array<int, true> wires whose network was already recomputed this tick */
	private array $done = [];

	private int $nextContinuationId = 1;

	/** @var array<int, int> blockHash => continuationId */
	private array $continuationOwner = [];

	/** @var array<int, int> continuationId => aliased continuationId */
	private array $continuationAliases = [];

	/**
	 * @var array<int, ContinuationState> continuationId => state
	 */
	private array $continuations = [];

	public function __construct(
		private RedstoneEngine $engine,
		private World $world
	){}

	public function startTick() : void{
		$this->done = [];
	}

	public function hasDeferred() : bool{
		return $this->continuations !== [];
	}

	private function resolveContinuationId(int $id) : int{
		while(isset($this->continuationAliases[$id])){
			$id = $this->continuationAliases[$id];
		}
		return $id;
	}

	public function getContinuationOwner(int $hash) : ?int{
		return $this->continuationOwner[$hash] ?? null;
	}

	public function getContinuationCount() : int{
		$count = count($this->continuations);
		foreach($this->continuations as $c){
			$count += count($c["mergingSources"] ?? []);
		}
		return $count;
	}

	public function clear() : void{
		$this->done = [];
		$this->continuations = [];
		$this->continuationOwner = [];
		$this->continuationAliases = [];
	}

	public function invalidate(int $hash) : void{
		if(!isset($this->continuationOwner[$hash])){
			return;
		}
		$id = $this->resolveContinuationId($this->continuationOwner[$hash]);
		if(!isset($this->continuations[$id])){
			unset($this->continuationOwner[$hash]);
			return;
		}

		$continuation = &$this->continuations[$id];
		if($continuation["phase"] === self::PHASE_APPLY){
			World::getBlockXYZ($hash, $x, $y, $z);
			$block = $this->world->getBlockAt($x, $y, $z);
			if($block instanceof RedstoneWire){
				return;
			}
			unset($continuation["wires"][$hash], $this->continuationOwner[$hash]);
			$continuation["mutated"] = true;
			return;
		}

		foreach($continuation["wires"] as $wHash => $_){
			unset($this->continuationOwner[$wHash]);
		}
		$sourcesToClean = isset($continuation["mergingSources"]) ? array_values($continuation["mergingSources"]) : [];
		while($sourcesToClean !== []){
			$source = array_pop($sourcesToClean);
			foreach($source["wires"] as $wHash => $_){
				unset($this->continuationOwner[$wHash]);
			}
			if(isset($source["mergingSources"])){
				foreach($source["mergingSources"] as $subSource){
					$sourcesToClean[] = $subSource;
				}
			}
		}
		unset($this->continuations[$id]);
		foreach($this->continuationAliases as $aliasSource => $aliasTarget){
			if($aliasTarget === $id || $aliasSource === $id){
				unset($this->continuationAliases[$aliasSource]);
			}
		}
		$this->done = [];
	}

	public function processDeferred(int $budget = self::MAX_WIRES) : int{
		$steps = 0;
		$sliceSize = 512;
		while($this->continuations !== [] && $steps < $budget){
			$id = array_key_first($this->continuations);
			if($id === null){
				break;
			}
			$slice = min($sliceSize, $budget - $steps);
			$continuation = &$this->continuations[$id];
			$consumed = $this->stepContinuation($id, $continuation, $slice);
			$steps += $consumed;

			if(isset($this->continuations[$id])){
				$current = $this->continuations[$id];
				unset($this->continuations[$id]);
				$this->continuations[$id] = $current;
			}

			if($consumed === 0){
				break;
			}
		}
		return $steps;
	}

	/**
	 * Recomputes the power of every wire connected to this one: each wire has the strongest of the power it gets
	 * from non-wire sources and its connected wires' power minus one.
	 */
	public function update(RedstoneWire $start, ?int &$budget = null) : void{
		$pos = $start->getPosition();
		$x = $pos->getFloorX();
		$y = $pos->getFloorY();
		$z = $pos->getFloorZ();
		$startHash = World::blockHash($x, $y, $z);
		if(isset($this->done[$startHash]) || isset($this->continuationOwner[$startHash])){
			return;
		}

		$id = $this->nextContinuationId++;
		$this->continuationOwner[$startHash] = $id;

		$this->continuations[$id] = [
			"phase" => self::PHASE_DISCOVER,
			"wires" => [$startHash => [$x, $y, $z]],
			"edges" => [],
			"pending" => [$startHash],
			"sourceQueue" => [],
			"power" => [],
			"buckets" => [],
			"propagateLevel" => 15,
			"applyQueue" => [],
			"mutated" => false,
			"mergingSources" => []
		];

		$availableBudget = $budget ?? ($this->engine->isTicking() ? max(0, $this->engine->getRemainingBudget()) : self::MAX_WIRES);
		$continuation = &$this->continuations[$id];
		$consumed = $this->stepContinuation($id, $continuation, $availableBudget);

		if($budget !== null){
			$budget -= $consumed;
		}elseif($this->engine->isTicking() && $this->engine->getRemainingBudget() > 0){
			$this->engine->consumeBudget($consumed);
		}

		if(isset($this->continuations[$id])){
			foreach($this->continuations[$id]["wires"] as $hash => $_){
				$this->done[$hash] = true;
			}
		}
	}

	/**
	 * @param ContinuationState $continuation
	 */
	private function stepContinuation(int $id, array &$continuation, int $budget) : int{
		$steps = 0;
		while($steps < $budget && isset($this->continuations[$id])){
			$slice = $budget - $steps;
			$consumed = 0;
			$oldPhase = $continuation["phase"];

			switch($continuation["phase"]){
				case self::PHASE_DISCOVER:
					$consumed = $this->stepDiscover($continuation, $slice, $id);
					$steps += $consumed;
					if($continuation["phase"] === self::PHASE_DISCOVER && $continuation["pending"] === []){
						$continuation["phase"] = self::PHASE_SOURCES;
						$continuation["sourceQueue"] = array_keys($continuation["wires"]);
						$continuation["power"] = [];
						$continuation["buckets"] = [];
					}
					break;

				case self::PHASE_MERGE:
					$consumed = $this->stepMerge($id, $continuation, $slice);
					$steps += $consumed;
					break;

				case self::PHASE_SOURCES:
					$consumed = $this->stepSources($continuation, $slice);
					$steps += $consumed;
					if($continuation["sourceQueue"] === []){
						$continuation["phase"] = self::PHASE_PROPAGATE;
						$continuation["propagateLevel"] = 15;
					}
					break;

				case self::PHASE_PROPAGATE:
					$consumed = $this->stepPropagate($continuation, $slice);
					$steps += $consumed;
					if($continuation["propagateLevel"] < 2){
						$continuation["phase"] = self::PHASE_APPLY;
						$continuation["applyQueue"] = array_keys($continuation["wires"]);
						unset($continuation["edges"], $continuation["buckets"]);
					}
					break;

				case self::PHASE_APPLY:
					$consumed = $this->stepApply($continuation, $slice);
					$steps += $consumed;
					if($continuation["applyQueue"] === []){
						$this->finalizeContinuation($id, $continuation);
						return $steps;
					}
					break;
			}

			if($consumed === 0 && $continuation["phase"] === $oldPhase){
				break;
			}
		}
		return $steps;
	}

	/**
	 * @param ContinuationState $c
	 */
	private function stepDiscover(array &$c, int $budget, int $id) : int{
		$steps = 0;
		while($c["pending"] !== [] && $steps < $budget){
			$hash = array_pop($c["pending"]);
			if(isset($c["edges"][$hash])){
				continue;
			}
			++$steps;
			[$wx, $wy, $wz] = $c["wires"][$hash];
			foreach($this->connectedWires($wx, $wy, $wz) as [$nx, $ny, $nz]){
				$next = World::blockHash($nx, $ny, $nz);
				if($this->canTransmit($wx, $wy, $wz, $nx, $ny, $nz)){
					$c["edges"][$hash][] = $next;
				}
				if(isset($this->continuationOwner[$next])){
					$owner = $this->resolveContinuationId($this->continuationOwner[$next]);
					if($owner !== $id && isset($this->continuations[$owner])){
						$c["mergingSources"][$owner] = $this->continuations[$owner];
						unset($this->continuations[$owner]);
						$this->continuationAliases[$owner] = $id;
						foreach($this->continuationAliases as $src => $dst){
							if($dst === $owner){
								$this->continuationAliases[$src] = $id;
							}
						}
						$c["phase"] = self::PHASE_MERGE;
						return $steps;
					}
					continue;
				}
				if(!isset($c["wires"][$next])){
					$c["wires"][$next] = [$nx, $ny, $nz];
					$c["pending"][] = $next;
					$this->continuationOwner[$next] = $id;
				}
			}
		}
		return $steps;
	}

	/**
	 * @param ContinuationState $c
	 */
	private function stepMerge(int $id, array &$c, int $budget) : int{
		$steps = 0;
		while($c["mergingSources"] !== [] && $steps < $budget){
			$sourceId = array_key_first($c["mergingSources"]);
			if($sourceId === null){
				break;
			}
			$source = &$c["mergingSources"][$sourceId];

			while($source["wires"] !== [] && $steps < $budget){
				$wHash = array_key_last($source["wires"]);
				$coords = $source["wires"][$wHash];
				unset($source["wires"][$wHash]);

				$c["wires"][$wHash] = $coords;
				$this->continuationOwner[$wHash] = $id;

				if(isset($source["edges"][$wHash])){
					$c["edges"][$wHash] = $source["edges"][$wHash];
					unset($source["edges"][$wHash]);
				}else{
					$c["pending"][] = $wHash;
				}

				++$steps;
			}

			if($source["wires"] === []){
				if(isset($source["pending"])){
					foreach($source["pending"] as $pHash){
						if(!isset($c["wires"][$pHash])){
							$c["pending"][] = $pHash;
						}
					}
				}
				if(isset($source["mergingSources"])){
					foreach($source["mergingSources"] as $subSourceId => $subSourceData){
						$c["mergingSources"][$subSourceId] = $subSourceData;
						$this->continuationAliases[$subSourceId] = $id;
						foreach($this->continuationAliases as $src => $dst){
							if($dst === $subSourceId){
								$this->continuationAliases[$src] = $id;
							}
						}
					}
				}
				unset($c["mergingSources"][$sourceId]);
			}
		}

		if($c["mergingSources"] === []){
			$c["phase"] = self::PHASE_DISCOVER;
		}

		return $steps;
	}

	/**
	 * @param ContinuationState $c
	 */
	private function stepSources(array &$c, int $budget) : int{
		$steps = 0;
		while($c["sourceQueue"] !== [] && $steps < $budget){
			$hash = array_pop($c["sourceQueue"]);
			++$steps;
			[$wx, $wy, $wz] = $c["wires"][$hash];
			$p = $this->sourcePower($wx, $wy, $wz);
			$c["power"][$hash] = $p;
			if($p > 0){
				$c["buckets"][$p][] = $hash;
			}
		}
		return $steps;
	}

	/**
	 * @param ContinuationState $c
	 */
	private function stepPropagate(array &$c, int $budget) : int{
		$steps = 0;
		while($c["propagateLevel"] > 1 && $steps < $budget){
			$level = $c["propagateLevel"];
			if(!isset($c["buckets"][$level]) || $c["buckets"][$level] === []){
				--$c["propagateLevel"];
				continue;
			}

			$hash = array_pop($c["buckets"][$level]);
			++$steps;

			if(($c["power"][$hash] ?? 0) !== $level){
				continue;
			}

			foreach($c["edges"][$hash] ?? [] as $next){
				if(isset($c["power"][$next]) && $c["power"][$next] < $level - 1){
					$c["power"][$next] = $level - 1;
					$c["buckets"][$level - 1][] = $next;
				}
			}
		}
		return $steps;
	}

	/**
	 * @param ContinuationState $c
	 */
	private function stepApply(array &$c, int $budget) : int{
		$steps = 0;
		while($c["applyQueue"] !== [] && $steps < $budget){
			$hash = array_pop($c["applyQueue"]);
			++$steps;

			if(!isset($c["wires"][$hash])){
				continue;
			}
			[$wx, $wy, $wz] = $c["wires"][$hash];
			$wire = $this->world->getBlockAt($wx, $wy, $wz);
			if($wire instanceof RedstoneWire){
				$targetPower = $c["power"][$hash] ?? 0;
				if($wire->getOutputSignalStrength() !== $targetPower){
					$pos = new Vector3($wx, $wy, $wz);
					$this->world->setBlockAt($wx, $wy, $wz, $wire->setOutputSignalStrength($targetPower), false);
					$this->world->notifyNeighbourBlockUpdate($pos);
					$this->engine->requestAround($pos);
				}
			}
			$this->done[$hash] = true;
		}
		return $steps;
	}

	/**
	 * @param ContinuationState $c
	 */
	private function finalizeContinuation(int $id, array $c) : void{
		foreach($c["wires"] as $hash => $_){
			unset($this->continuationOwner[$hash]);
			$this->done[$hash] = true;
		}
		$sourcesToClean = isset($c["mergingSources"]) ? array_values($c["mergingSources"]) : [];
		while($sourcesToClean !== []){
			$source = array_pop($sourcesToClean);
			foreach($source["wires"] as $hash => $_){
				unset($this->continuationOwner[$hash]);
				$this->done[$hash] = true;
			}
			if(isset($source["mergingSources"])){
				foreach($source["mergingSources"] as $subSource){
					$sourcesToClean[] = $subSource;
				}
			}
		}
		unset($this->continuations[$id]);
		foreach($this->continuationAliases as $aliasSource => $aliasTarget){
			if($aliasTarget === $id || $aliasSource === $id){
				unset($this->continuationAliases[$aliasSource]);
			}
		}

		if($c["mutated"]){
			foreach($c["wires"] as $hash => [$wx, $wy, $wz]){
				$this->engine->request($wx, $wy, $wz);
			}
		}
	}

	/**
	 * The horizontal directions a wire powers blocks in: towards what it connects to, straight through when it only
	 * connects on one side, and every direction when it connects to nothing.
	 *
	 * @return array<int, true>
	 */
	public function getDirections(RedstoneWire $wire) : array{
		$pos = $wire->getPosition();
		$x = $pos->getFloorX();
		$y = $pos->getFloorY();
		$z = $pos->getFloorZ();
		$directions = [];
		foreach(self::HORIZONTAL as $face){
			[$dx, , $dz] = Facing::OFFSET[$face];
			$side = $this->world->getBlockAt($x + $dx, $y, $z + $dz);
			if(
				$side instanceof RedstoneWire || self::connectsToWire($side, $face) ||
				($this->world->getBlockAt($x + $dx, $y + 1, $z + $dz) instanceof RedstoneWire && ($this->canTransmit($x, $y, $z, $x + $dx, $y + 1, $z + $dz) || $this->canTransmit($x + $dx, $y + 1, $z + $dz, $x, $y, $z))) ||
				($this->world->getBlockAt($x + $dx, $y - 1, $z + $dz) instanceof RedstoneWire && $this->canTransmit($x, $y, $z, $x + $dx, $y - 1, $z + $dz))
			){
				$directions[$face] = true;
			}
		}
		if($directions === []){
			return [Facing::NORTH => true, Facing::SOUTH => true, Facing::WEST => true, Facing::EAST => true];
		}
		if(count($directions) === 1){
			foreach($directions as $face => $_){
				$directions[Facing::opposite($face)] = true;
			}
		}
		return $directions;
	}

	private static function isSlab(Block $block) : bool{
		return $block instanceof Slab && $block->getSlabType() !== SlabType::DOUBLE;
	}

	private function canTransmit(int $x1, int $y1, int $z1, int $x2, int $y2, int $z2) : bool{
		$dy = $y2 - $y1;
		if($dy === 0){
			return true;
		}
		if($dy === 1){
			return !RedstoneEngine::isConductor($this->world->getBlockAt($x1, $y1 + 1, $z1));
		}
		if($dy === -1){
			$support = $this->world->getBlockAt($x1, $y1 - 1, $z1);
			$side = $this->world->getBlockAt($x2, $y1, $z2);
			return !self::isSlab($support) && !self::isSlab($side) && !RedstoneEngine::isConductor($side);
		}
		return false;
	}

	/**
	 * @return list<array{int, int, int}>
	 */
	private function connectedWires(int $x, int $y, int $z) : array{
		$connected = [];
		foreach(self::HORIZONTAL as $face){
			[$dx, , $dz] = Facing::OFFSET[$face];
			$side = $this->world->getBlockAt($x + $dx, $y, $z + $dz);
			if($side instanceof RedstoneWire){
				$connected[] = [$x + $dx, $y, $z + $dz];
				continue;
			}
			$upper = $this->world->getBlockAt($x + $dx, $y + 1, $z + $dz);
			if($upper instanceof RedstoneWire && ($this->canTransmit($x, $y, $z, $x + $dx, $y + 1, $z + $dz) || $this->canTransmit($x + $dx, $y + 1, $z + $dz, $x, $y, $z))){
				$connected[] = [$x + $dx, $y + 1, $z + $dz];
			}
			$lower = $this->world->getBlockAt($x + $dx, $y - 1, $z + $dz);
			if($lower instanceof RedstoneWire && ($this->canTransmit($x, $y, $z, $x + $dx, $y - 1, $z + $dz) || $this->canTransmit($x + $dx, $y - 1, $z + $dz, $x, $y, $z))){
				$connected[] = [$x + $dx, $y - 1, $z + $dz];
			}
		}
		return $connected;
	}

	/** Power a wire gets from anything but other wire: sources beside it, and strongly powered blocks. */
	private function sourcePower(int $x, int $y, int $z) : int{
		$power = 0;
		foreach(Facing::ALL as $face){
			[$dx, $dy, $dz] = Facing::OFFSET[$face];
			$neighbour = $this->world->getBlockAt($x + $dx, $y + $dy, $z + $dz);
			if($neighbour instanceof RedstoneWire){
				continue;
			}
			$power = max($power, $this->engine->getOutput($neighbour, Facing::opposite($face), false));
			if(RedstoneEngine::isConductor($neighbour)){
				$power = max($power, $this->engine->getPowerIntoBlock(new Vector3($x + $dx, $y + $dy, $z + $dz), true));
			}
			if($power >= 15){
				break;
			}
		}
		return $power;
	}

	/** Components a wire visually connects to, seen from a wire in direction $face. */
	private static function connectsToWire(Block $block, int $face) : bool{
		if($block instanceof RedstoneRepeater){
			return Facing::axis($block->getFacing()) === Facing::axis($face);
		}
		return $block instanceof RedstoneSource;
	}
}
