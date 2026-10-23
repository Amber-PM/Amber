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

namespace pocketmine\world\redstone\wire;

use pocketmine\block\RedstoneTorch;

use pocketmine\block\RedstoneWire;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\world\redstone\RedstoneEngine;
use pocketmine\world\World;
use function array_key_first;
use function array_key_last;
use function array_pop;
use function count;
use function max;
use function min;

class WireNetwork{
	private const MAX_WIRES = 4096;
	private WireTopology $topology;

	public const PHASE_DISCOVER = 0;
	public const PHASE_SOURCES = 1;
	public const PHASE_PROPAGATE = 2;
	public const PHASE_APPLY = 3;
	public const PHASE_MERGE = 4;
	public const PHASE_CLEANUP = 5;
	public const CLEANUP_SETTLED = 0;
	public const CLEANUP_INVALIDATED = 1;

	protected array $done = [];

	protected int $nextContinuationId = 1;

	protected array $continuationOwner = [];

	protected array $continuationAliases = [];

	protected array $continuationAliasSources = [];

	protected array $continuations = [];

	public function __construct(
		private RedstoneEngine $engine,
		private World $world
	){
		$this->topology = new WireTopology($engine, $world);
	}

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
		$owner = $this->continuationOwner[$hash] ?? null;
		if($owner === null){
			return null;
		}
		$id = $this->resolveContinuationId($owner);
		if(!isset($this->continuations[$id])){
			return null;
		}
		$c = &$this->continuations[$id];
		return $c->phase !== WirePhase::CLEANUP || $c->cleanupMode === self::CLEANUP_SETTLED ? $owner : null;
	}

	public function getContinuationCount() : int{
		$count = 0;
		foreach($this->continuations as $c){
			if($c->phase !== WirePhase::CLEANUP){
				$count += 1 + count($c->mergingSources);
			}
		}
		return $count;
	}

	public function clear() : void{
		$this->done = [];
		$this->continuations = [];
		$this->continuationOwner = [];
		$this->continuationAliases = [];
		$this->continuationAliasSources = [];
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
		if($continuation->phase === WirePhase::APPLY){
			World::getBlockXYZ($hash, $x, $y, $z);
			$block = $this->world->getBlockAt($x, $y, $z);
			if($block instanceof RedstoneWire){
				return;
			}
			unset($continuation->wires[$hash], $this->continuationOwner[$hash]);
			$continuation->mutated = true;
			return;
		}

		$continuation->phase = WirePhase::CLEANUP;
		$continuation->cleanupMode = self::CLEANUP_INVALIDATED;
		$continuation->mutated = false;
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
				unset($current);
			}

			if($consumed === 0){
				break;
			}
		}
		return $steps;
	}

	public function update(RedstoneWire $start, ?int &$budget = null) : void{
		$pos = $start->getPosition();
		$x = $pos->getFloorX();
		$y = $pos->getFloorY();
		$z = $pos->getFloorZ();
		$startHash = World::blockHash($x, $y, $z);
		if(isset($this->done[$startHash]) || $this->getContinuationOwner($startHash) !== null){
			return;
		}

		$id = $this->nextContinuationId++;
		$this->continuationOwner[$startHash] = $id;

		$this->continuations[$id] = new WireContinuation($startHash, $x, $y, $z);

		$availableBudget = $budget ?? ($this->engine->isTicking() ? max(0, $this->engine->getRemainingBudget()) : self::MAX_WIRES);
		$continuation = &$this->continuations[$id];
		$consumed = $this->stepContinuation($id, $continuation, $availableBudget);

		if($budget !== null){
			$budget -= $consumed;
		}elseif($this->engine->isTicking() && $this->engine->getRemainingBudget() > 0){
			$this->engine->consumeBudget($consumed);
		}

	}

	private function stepContinuation(int $id, WireContinuation $continuation, int $budget) : int{
		$steps = 0;
		while($steps < $budget && isset($this->continuations[$id])){
			$slice = $budget - $steps;
			$consumed = 0;
			$oldPhase = $continuation->phase;

			switch($continuation->phase){
				case WirePhase::DISCOVER:
					$consumed = $this->stepDiscover($continuation, $slice, $id);
					$steps += $consumed;
					if($continuation->phase === WirePhase::DISCOVER && $continuation->pending === []){
						$continuation->phase = WirePhase::SOURCES;
						$continuation->power = [];
						$continuation->buckets = [];
					}
					break;

				case WirePhase::MERGE:
					$consumed = $this->stepMerge($id, $continuation, $slice);
					$steps += $consumed;
					break;

				case WirePhase::SOURCES:
					$consumed = $this->stepSources($continuation, $slice);
					$steps += $consumed;
					if($continuation->sourceQueue === []){
						$continuation->phase = WirePhase::PROPAGATE;
						$continuation->propagateLevel = 15;
					}
					break;

				case WirePhase::PROPAGATE:
					$consumed = $this->stepPropagate($continuation, $slice);
					$steps += $consumed;
					if($continuation->propagateLevel < 2){
						$continuation->phase = WirePhase::APPLY;

					}
					break;

				case WirePhase::CLEANUP:
					$consumed = $this->stepCleanup($id, $continuation, $slice);
					$steps += $consumed;
					if(!isset($this->continuations[$id])){
						return $steps;
					}
					break;

				case WirePhase::APPLY:
					$consumed = $this->stepApply($continuation, $slice);
					$steps += $consumed;
					if($continuation->applyQueue === []){
						$continuation->phase = WirePhase::CLEANUP;
						$continuation->cleanupMode = self::CLEANUP_SETTLED;
					}
					break;
			}

			if($consumed === 0 && $continuation->phase === $oldPhase){
				break;
			}
		}
		return $steps;
	}

	private function stepDiscover(WireContinuation $c, int $budget, int $id) : int{
		$steps = 0;
		while($c->pending !== [] && $steps < $budget){
			$hash = $c->pending[array_key_last($c->pending)];
			[$wx, $wy, $wz] = $c->wires[$hash];
			if(!$this->isSettlingAreaLoaded($wx, $wz, 1)){
				return $steps + 1;
			}
			array_pop($c->pending);
			++$steps;
			if(isset($c->edges[$hash])){
				continue;
			}
			[$wx, $wy, $wz] = $c->wires[$hash];
			foreach($this->connectedWires($wx, $wy, $wz) as [$nx, $ny, $nz]){
				$next = World::blockHash($nx, $ny, $nz);
				$rawOwner = $this->getContinuationOwner($next);
				if($rawOwner !== null){
					$owner = $this->resolveContinuationId($rawOwner);
					if($owner !== $id){

						if(isset($this->continuations[$owner]->aliasPending) || $this->continuations[$owner]->phase === WirePhase::CLEANUP){
							$c->pending[] = $hash;
							unset($c->edges[$hash]);
							return $steps;
						}
						$c->mergingSources[$owner] = $this->continuations[$owner];
						unset($this->continuations[$owner]);
						$this->continuationAliases[$owner] = $id;
						$this->continuationAliasSources[$id][$owner] = true;
						$c->aliasPending = $owner;
						$c->phase = WirePhase::MERGE;
						$c->pending[] = $hash;
						unset($c->edges[$hash]);
						return $steps;
					}
				}
				if($this->canTransmit($wx, $wy, $wz, $nx, $ny, $nz)){
					$c->edges[$hash][] = $next;
				}
				if($rawOwner !== null){
					continue;
				}
				if(!isset($c->wires[$next])){
					$c->wires[$next] = [$nx, $ny, $nz];
					$c->sourceQueue[] = $next;
					$c->applyQueue[] = $next;
					$c->pending[] = $next;
					$this->continuationOwner[$next] = $id;
				}
			}
		}
		return $steps;
	}

	private function stepMerge(int $id, WireContinuation $c, int $budget) : int{
		$steps = 0;
		while(isset($c->aliasPending) && $steps < $budget){
			$owner = $c->aliasPending;
			$alias = array_key_last($this->continuationAliasSources[$owner] ?? []);
			if($alias === null){
				unset($this->continuationAliasSources[$owner], $c->aliasPending);
				break;
			}
			$this->continuationAliases[$alias] = $id;
			$this->continuationAliasSources[$id][$alias] = true;
			unset($this->continuationAliasSources[$owner][$alias]);
			++$steps;
		}
		while(!isset($c->aliasPending) && $c->mergingSources !== [] && $steps < $budget){
			$sourceId = array_key_first($c->mergingSources);
			$source = &$c->mergingSources[$sourceId];
			if($source->wires !== []){
				$wHash = array_key_last($source->wires);
				$coords = $source->wires[$wHash];
				unset($source->wires[$wHash]);
				$c->wires[$wHash] = $coords;
				$c->sourceQueue[] = $wHash;
				$c->applyQueue[] = $wHash;
				$this->continuationOwner[$wHash] = $id;
				if(isset($source->edges[$wHash])){
					$c->edges[$wHash] = $source->edges[$wHash];
					unset($source->edges[$wHash]);
				}else{
					$c->pending[] = $wHash;
				}
			}elseif(($source->mergingSources ?? []) !== []){
				$subId = array_key_last($source->mergingSources);
				$c->mergingSources[$subId] = $source->mergingSources[$subId];
				unset($source->mergingSources[$subId]);
			}elseif(!$this->discardSourceData($source)){
				unset($c->mergingSources[$sourceId]);
			}
			unset($source);
			++$steps;
		}
		if($c->mergingSources === [] && !isset($c->aliasPending)){
			$c->phase = WirePhase::DISCOVER;
		}
		return $steps;
	}

	private function stepSources(WireContinuation $c, int $budget) : int{
		$steps = 0;
		while($c->sourceQueue !== [] && $steps < $budget){
			$hash = $c->sourceQueue[array_key_last($c->sourceQueue)];
			[$wx, $wy, $wz] = $c->wires[$hash];
			if(!$this->isSettlingAreaLoaded($wx, $wz, 2)){
				return $steps + 1;
			}
			array_pop($c->sourceQueue);
			++$steps;
			[$wx, $wy, $wz] = $c->wires[$hash];
			$p = $this->sourcePower($wx, $wy, $wz);
			$c->power[$hash] = $p;
			if($p > 0){
				$c->buckets[$p][] = $hash;
			}
		}
		return $steps;
	}

	private function stepPropagate(WireContinuation $c, int $budget) : int{
		$steps = 0;
		while($c->propagateLevel > 1 && $steps < $budget){
			$level = $c->propagateLevel;
			if(!isset($c->buckets[$level]) || $c->buckets[$level] === []){
				--$c->propagateLevel;
				continue;
			}

			$hash = $c->buckets[$level][array_key_last($c->buckets[$level])];
			[$wx, $wy, $wz] = $c->wires[$hash];
			if(!$this->isSettlingAreaLoaded($wx, $wz, 1)){
				return $steps + 1;
			}
			array_pop($c->buckets[$level]);
			++$steps;

			if(($c->power[$hash] ?? 0) !== $level){
				continue;
			}

			foreach($c->edges[$hash] ?? [] as $next){
				if(isset($c->power[$next]) && $c->power[$next] < $level - 1){
					$c->power[$next] = $level - 1;
					$c->buckets[$level - 1][] = $next;
				}
			}
		}
		return $steps;
	}

	private function stepApply(WireContinuation $c, int $budget) : int{
		$steps = 0;
		while($c->applyQueue !== [] && $steps < $budget){
			$hash = $c->applyQueue[array_key_last($c->applyQueue)];
			if(isset($c->wires[$hash])){
				[$wx, $wy, $wz] = $c->wires[$hash];
				if(!$this->isSettlingAreaLoaded($wx, $wz, 1)){
					return $steps + 1;
				}
			}
			array_pop($c->applyQueue);
			++$steps;

			if(!isset($c->wires[$hash])){
				continue;
			}
			[$wx, $wy, $wz] = $c->wires[$hash];
			$wire = $this->world->getBlockAt($wx, $wy, $wz);
			if($wire instanceof RedstoneWire){
				$targetPower = $c->power[$hash] ?? 0;
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

	private function discardSourceData(WireContinuation $source) : bool{
		foreach(["edges", "pending", "sourceQueue", "power", "applyQueue"] as $field){
			if(($source->{$field} ?? []) !== []){
				array_pop($source->{$field});
				return true;
			}
		}
		if(($source->buckets ?? []) !== []){
			$key = array_key_last($source->buckets);
			if($source->buckets[$key] !== []){
				array_pop($source->buckets[$key]);
			}else{
				unset($source->buckets[$key]);
			}
			return true;
		}
		return false;
	}

	private function stepCleanup(int $id, WireContinuation $c, int $budget) : int{
		$steps = 0;
		while($steps < $budget){
			if($c->wires !== []){
				$hash = array_key_last($c->wires);
				[$x, $y, $z] = $c->wires[$hash];
				unset($c->wires[$hash]);
				if($this->releaseOwner($hash, $id) && $c->cleanupMode === self::CLEANUP_SETTLED){
					$this->done[$hash] = true;
				}
				if($c->mutated){
					$this->engine->request($x, $y, $z);
				}
			}elseif($c->mergingSources !== []){
				$sourceId = array_key_first($c->mergingSources);
				$source = &$c->mergingSources[$sourceId];
				if($source->wires !== []){
					$hash = array_key_last($source->wires);
					unset($source->wires[$hash]);
					if($this->releaseOwner($hash, $id) && $c->cleanupMode === self::CLEANUP_SETTLED){
						$this->done[$hash] = true;
					}
				}elseif(($source->mergingSources ?? []) !== []){
					$subId = array_key_last($source->mergingSources);
					$c->mergingSources[$subId] = $source->mergingSources[$subId];
					unset($source->mergingSources[$subId]);
				}elseif(!$this->discardSourceData($source)){
					unset($c->mergingSources[$sourceId]);
				}
				unset($source);
			}elseif($this->discardSourceData($c)){
			}elseif(($this->continuationAliasSources[$id] ?? []) !== []){
				$alias = array_key_last($this->continuationAliasSources[$id]);

				$subAlias = array_key_last($this->continuationAliasSources[$alias] ?? []);
				if($subAlias !== null){
					unset($this->continuationAliases[$subAlias], $this->continuationAliasSources[$alias][$subAlias]);
				}else{
					unset($this->continuationAliases[$alias], $this->continuationAliasSources[$id][$alias], $this->continuationAliasSources[$alias]);
				}
			}else{
				unset($this->continuations[$id], $this->continuationAliasSources[$id]);
				++$steps;
				break;
			}
			++$steps;
		}
		return $steps;
	}

	private function releaseOwner(int $hash, int $id) : bool{
		$owner = $this->continuationOwner[$hash] ?? null;
		if($owner !== null && $this->resolveContinuationId($owner) === $id){
			unset($this->continuationOwner[$hash]);
			return true;
		}
		return false;
	}

	public function getDirections(RedstoneWire $wire) : array{
		return $this->topology->getDirections($wire);
	}

	private function canTransmit(int $x1, int $y1, int $z1, int $x2, int $y2, int $z2) : bool{
		return $this->topology->canTransmit($x1, $y1, $z1, $x2, $y2, $z2);
	}

	private function connectedWires(int $x, int $y, int $z) : array{
		return $this->topology->connectedWires($x, $y, $z);
	}

	public function getTransmittedWireNeighbours(Vector3 $pos) : array{
		return $this->topology->getTransmittedWireNeighbours($pos);
	}

	private function isSettlingAreaLoaded(int $x, int $z, int $radius) : bool{
		return $this->topology->isSettlingAreaLoaded($x, $z, $radius);
	}

	public function isWireAreaLoaded(Vector3 $pos) : bool{
		return $this->topology->isWireAreaLoaded($pos);
	}

	public function getTorchSeedWires(RedstoneTorch $torch) : array{
		return $this->topology->getTorchSeedWires($torch);
	}

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

}
