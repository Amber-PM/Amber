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
use function array_pop;
use function count;
use function in_array;
use function max;
use function min;

/**
 * Power along redstone wire: a change to one wire recomputes its whole connected network at once, so a long line
 * settles in one step instead of one wire per tick.
 */
final class WireNetwork{
	private const MAX_WIRES = 4096;
	private const HORIZONTAL = [Facing::NORTH, Facing::SOUTH, Facing::WEST, Facing::EAST];

	/** @var array<int, true> wires whose network was already recomputed this tick */
	private array $done = [];

	private int $nextContinuationId = 1;

	/** @var array<int, int> blockHash => continuationId */
	private array $continuationOwner = [];

	/**
	 * @var array<int, array{
	 *     wires: array<int, array{int, int, int}>,
	 *     pending: list<int>,
	 *     edges: array<int, list<int>>
	 * }> continuationId => state
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

	public function getContinuationOwner(int $hash) : ?int{
		return $this->continuationOwner[$hash] ?? null;
	}

	public function getContinuationCount() : int{
		return count($this->continuations);
	}

	public function clear() : void{
		$this->done = [];
		$this->continuations = [];
		$this->continuationOwner = [];
	}

	public function invalidate(int $hash) : void{
		if(!isset($this->continuationOwner[$hash])){
			return;
		}
		$id = $this->continuationOwner[$hash];
		if(isset($this->continuations[$id])){
			foreach($this->continuations[$id]["wires"] as $wHash => $_){
				unset($this->continuationOwner[$wHash]);
			}
			unset($this->continuations[$id]);
			$this->done = [];
		}else{
			unset($this->continuationOwner[$hash]);
		}
	}

	public function processDeferred(int $budget = self::MAX_WIRES) : int{
		$steps = 0;
		$sliceSize = 512;
		while($this->continuations !== [] && $steps < $budget){
			$id = array_key_first($this->continuations);
			if($id === null){
				break;
			}
			$continuation = $this->continuations[$id];
			unset($this->continuations[$id]);

			$wires = $continuation["wires"];
			$pending = $continuation["pending"];
			$edges = $continuation["edges"];

			$slice = min($sliceSize, $budget - $steps);
			$consumed = $this->advanceNetwork($wires, $pending, $edges, $slice, $id);
			$steps += $consumed;

			if($pending === []){
				$this->settleNetwork($id, $wires, $edges);
			}else{
				$this->continuations[$id] = [
					"wires" => $wires,
					"pending" => $pending,
					"edges" => $edges
				];
				if($consumed === 0){
					break;
				}
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
		$wires = [$startHash => [$x, $y, $z]];
		$pending = [$startHash];
		$edges = [];
		$this->continuationOwner[$startHash] = $id;

		$availableBudget = $budget ?? ($this->engine->isTicking() ? max(0, $this->engine->getRemainingBudget()) : self::MAX_WIRES);
		$consumed = $this->advanceNetwork($wires, $pending, $edges, $availableBudget, $id);

		if($budget !== null){
			$budget -= $consumed;
		}elseif($this->engine->isTicking() && $this->engine->getRemainingBudget() > 0){
			$this->engine->consumeBudget($consumed);
		}

		if($pending === []){
			$this->settleNetwork($id, $wires, $edges);
		}else{
			foreach($wires as $hash => $_){
				$this->done[$hash] = true;
			}
			$this->continuations[$id] = [
				"wires" => $wires,
				"pending" => $pending,
				"edges" => $edges
			];
		}
	}

	/**
	 * @param array<int, array{int, int, int}> $wires
	 * @param list<int>                        $pending
	 * @param array<int, list<int>>            $edges
	 */
	private function advanceNetwork(array &$wires, array &$pending, array &$edges, int $budget, int $continuationId) : int{
		$steps = 0;
		while($pending !== [] && $steps < $budget){
			$hash = array_pop($pending);
			++$steps;
			[$wx, $wy, $wz] = $wires[$hash];
			foreach($this->connectedWires($wx, $wy, $wz) as [$nx, $ny, $nz]){
				$next = World::blockHash($nx, $ny, $nz);
				if($this->canTransmit($wx, $wy, $wz, $nx, $ny, $nz)){
					$edges[$hash][] = $next;
				}
				if(isset($this->continuationOwner[$next]) && $this->continuationOwner[$next] !== $continuationId){
					$this->mergeContinuations($continuationId, $this->continuationOwner[$next], $wires, $pending, $edges);
					continue;
				}
				if(!isset($wires[$next])){
					$wires[$next] = [$nx, $ny, $nz];
					$pending[] = $next;
					$this->continuationOwner[$next] = $continuationId;
				}
			}
		}
		return $steps;
	}

	/**
	 * @param array<int, array{int, int, int}> $targetWires
	 * @param list<int>                        $targetPending
	 * @param array<int, list<int>>            $targetEdges
	 */
	private function mergeContinuations(
		int $targetId,
		int $sourceId,
		array &$targetWires,
		array &$targetPending,
		array &$targetEdges
	) : void{
		if(!isset($this->continuations[$sourceId])){
			return;
		}
		$source = $this->continuations[$sourceId];
		unset($this->continuations[$sourceId]);

		foreach($source["wires"] as $hash => $coords){
			$targetWires[$hash] = $coords;
			$this->continuationOwner[$hash] = $targetId;
		}
		$pendingLookup = array_fill_keys($targetPending, true);
		foreach($source["pending"] as $hash){
			if(!isset($pendingLookup[$hash])){
				$targetPending[] = $hash;
				$pendingLookup[$hash] = true;
			}
		}
		foreach($source["edges"] as $hash => $edgeList){
			if(!isset($targetEdges[$hash])){
				$targetEdges[$hash] = $edgeList;
			}else{
				$edgeLookup = array_fill_keys($targetEdges[$hash], true);
				foreach($edgeList as $edgeHash){
					if(!isset($edgeLookup[$edgeHash])){
						$targetEdges[$hash][] = $edgeHash;
						$edgeLookup[$edgeHash] = true;
					}
				}
			}
		}
	}

	/**
	 * @param array<int, array{int, int, int}> $wires
	 * @param array<int, list<int>>            $edges
	 */
	private function settleNetwork(int $id, array $wires, array $edges) : void{
		foreach($wires as $hash => $_){
			unset($this->continuationOwner[$hash]);
		}
		unset($this->continuations[$id]);

		$power = [];
		/** @var array<int, list<int>> $buckets */
		$buckets = [];
		foreach($wires as $hash => [$wx, $wy, $wz]){
			$power[$hash] = $this->sourcePower($wx, $wy, $wz);
			if($power[$hash] > 0){
				$buckets[$power[$hash]][] = $hash;
			}
		}
		for($level = 15; $level > 1; --$level){
			foreach($buckets[$level] ?? [] as $hash){
				if($power[$hash] !== $level){
					continue;
				}
				foreach($edges[$hash] ?? [] as $next){
					if(isset($power[$next]) && $power[$next] < $level - 1){
						$power[$next] = $level - 1;
						$buckets[$level - 1][] = $next;
					}
				}
			}
		}

		foreach($wires as $hash => [$wx, $wy, $wz]){
			$wire = $this->world->getBlockAt($wx, $wy, $wz);
			if($wire instanceof RedstoneWire){
				$powerChanged = $wire->getOutputSignalStrength() !== $power[$hash];
				if($powerChanged){
					$wirePos = new Vector3($wx, $wy, $wz);
					$this->world->setBlockAt($wx, $wy, $wz, $wire->setOutputSignalStrength($power[$hash]), false);
					$this->world->notifyNeighbourBlockUpdate($wirePos);
					$this->engine->requestAround($wirePos);
				}
			}
			$this->done[$hash] = true;
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
