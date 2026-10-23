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

use pocketmine\block\utils\DelayedRedstoneReceiver;
use pocketmine\math\Vector3;
use pocketmine\world\World;
use function array_key_first;
use function count;
use function max;
use function min;

final class RedstoneScheduler{

	private array $delayed = [];

	private array $delayedCursor = [];

	private \SplMinHeap $delayedTicksHeap;

	private array $delayedIndex = [];

	private array $delayedState = [];

	private array $unloadedDelayed = [];
	private int $unloadedDelayedCount = 0;

	public function __construct(private World $world, private int $maxUpdatesPerTick){
		$this->delayedTicksHeap = new \SplMinHeap();
	}
	public function clear() : void{
		$this->delayed = [];
		$this->delayedCursor = [];
		$this->delayedTicksHeap = new \SplMinHeap();
		$this->delayedIndex = [];
		$this->delayedState = [];
		$this->unloadedDelayed = [];
		$this->unloadedDelayedCount = 0;
	}
	public function hasPending() : bool{ return $this->delayed !== [] || $this->unloadedDelayed !== []; }
	public function hasDifferentState(int $hash, int $stateId) : bool{
		return isset($this->delayedState[$hash]) && $this->delayedState[$hash] !== $stateId;
	}
	public function getUnloadedDelayedCount() : int{ return $this->unloadedDelayedCount; }

	public function schedule(Vector3 $pos, int $delay, int $currentTick, ?int $expectedStateId = null) : void{
		$x = $pos->getFloorX();
		$y = $pos->getFloorY();
		$z = $pos->getFloorZ();
		if(!$this->world->isInWorld($x, $y, $z)){
			return;
		}
		if($expectedStateId === null && !$this->world->isChunkLoaded($x >> 4, $z >> 4)){
			return;
		}
		$hash = World::blockHash($x, $y, $z);
		$stateId = $expectedStateId ?? $this->world->getBlockAt($x, $y, $z)->getStateId();
		if(isset($this->delayedIndex[$hash])){
			if(($this->delayedState[$hash] ?? null) === $stateId){
				return;
			}
		}
		$due = $currentTick + max(1, $delay);
		$this->delayedIndex[$hash] = $due;
		$this->delayedState[$hash] = $stateId;
		if(!isset($this->delayed[$due])){
			$this->delayed[$due] = [];
			$this->delayedTicksHeap->insert($due);
		}
		$this->delayed[$due][] = $hash;
	}

	public function updateExpectedState(Vector3 $pos, int $stateId) : void{
		$hash = World::blockHash($pos->getFloorX(), $pos->getFloorY(), $pos->getFloorZ());
		if(isset($this->delayedIndex[$hash])){
			$this->delayedState[$hash] = $stateId;
		}
	}

	public function cancelSchedule(Vector3 $pos) : void{
		$hash = World::blockHash($pos->getFloorX(), $pos->getFloorY(), $pos->getFloorZ());
		unset($this->delayedIndex[$hash], $this->delayedState[$hash]);

		$chunkHash = World::chunkHash($pos->getFloorX() >> 4, $pos->getFloorZ() >> 4);
		if(isset($this->unloadedDelayed[$chunkHash][$hash])){
			unset($this->unloadedDelayed[$chunkHash][$hash]);
			--$this->unloadedDelayedCount;
			if($this->unloadedDelayed[$chunkHash] === []){
				unset($this->unloadedDelayed[$chunkHash]);
			}
		}
	}

	public function isScheduled(Vector3 $pos) : bool{
		return isset($this->delayedIndex[World::blockHash($pos->getFloorX(), $pos->getFloorY(), $pos->getFloorZ())]);
	}

	public function tick(RedstoneEngine $engine, RedstoneBudget $budget, int $currentTick) : void{
		if($this->unloadedDelayed !== [] && $budget->remaining() > 0){
			$chunkCheckLimit = min(count($this->unloadedDelayed), max(32, $budget->remaining()));
			$checked = 0;
			while($this->unloadedDelayed !== [] && $budget->remaining() > 0 && $checked < $chunkCheckLimit){
				++$checked;
				$chunkHash = array_key_first($this->unloadedDelayed);

				$chunkX = null;
				$chunkZ = null;
				World::getXZ($chunkHash, $chunkX, $chunkZ);
				if($chunkX === null || $chunkZ === null || !$this->world->isChunkLoaded($chunkX, $chunkZ)){
					$events = $this->unloadedDelayed[$chunkHash];
					unset($this->unloadedDelayed[$chunkHash]);
					$this->unloadedDelayed[$chunkHash] = $events;
					unset($events);
					continue;
				}

				$staleChecks = 0;
				$staleLimit = min(count($this->unloadedDelayed[$chunkHash]), max(32, $budget->remaining()));
				while(isset($this->unloadedDelayed[$chunkHash]) && $this->unloadedDelayed[$chunkHash] !== [] && $budget->remaining() > 0){
					$hash = array_key_first($this->unloadedDelayed[$chunkHash]);
					$dueTick = $this->unloadedDelayed[$chunkHash][$hash];
					unset($this->unloadedDelayed[$chunkHash][$hash]);
					--$this->unloadedDelayedCount;

					if(($this->delayedIndex[$hash] ?? null) !== $dueTick){
						if(++$staleChecks >= $staleLimit){
							break;
						}
						continue;
					}

					$expectedState = $this->delayedState[$hash] ?? null;
					unset($this->delayedIndex[$hash], $this->delayedState[$hash]);

					$budget->consume(1);
					World::getBlockXYZ($hash, $x, $y, $z);
					$block = $this->world->getBlockAt($x, $y, $z);
					if($block instanceof DelayedRedstoneReceiver && ($expectedState === null || $block->getStateId() === $expectedState)){
						$block->onRedstoneScheduledUpdate($engine);
					}
				}

				if(!isset($this->unloadedDelayed[$chunkHash])){
					continue;
				}
				if($this->unloadedDelayed[$chunkHash] === []){
					unset($this->unloadedDelayed[$chunkHash]);
				}else{
					$remaining = $this->unloadedDelayed[$chunkHash];
					unset($this->unloadedDelayed[$chunkHash]);
					$this->unloadedDelayed[$chunkHash] = $remaining;
					unset($remaining);
				}
			}
		}

		if(!$this->delayedTicksHeap->isEmpty()){
			$staleChecks = 0;
			$staleLimit = $this->maxUpdatesPerTick * 4;
			while(!$this->delayedTicksHeap->isEmpty() && $budget->remaining() > 0){
				$tick = $this->delayedTicksHeap->top();
				if($tick > $currentTick){
					break;
				}
				if(!isset($this->delayed[$tick])){
					$this->delayedTicksHeap->extract();
					continue;
				}
				$hashes = $this->delayed[$tick];
				$count = count($hashes);
				$idx = $this->delayedCursor[$tick] ?? 0;
				while($idx < $count && $budget->remaining() > 0){
					$hash = $hashes[$idx++];
					if(($this->delayedIndex[$hash] ?? null) !== $tick){
						if(++$staleChecks >= $staleLimit){
							break;
						}
						continue;
					}
					$budget->consume(1);
					$expectedState = $this->delayedState[$hash] ?? null;
					World::getBlockXYZ($hash, $x, $y, $z);
					$chunkX = $x >> 4;
					$chunkZ = $z >> 4;
					if(!$this->world->isChunkLoaded($chunkX, $chunkZ)){
						$chunkHash = World::chunkHash($chunkX, $chunkZ);
						if(!isset($this->unloadedDelayed[$chunkHash][$hash])){
							$this->unloadedDelayed[$chunkHash][$hash] = $tick;
							++$this->unloadedDelayedCount;
						}
						continue;
					}
					unset($this->delayedIndex[$hash], $this->delayedState[$hash]);
					$block = $this->world->getBlockAt($x, $y, $z);
					if($block instanceof DelayedRedstoneReceiver && ($expectedState === null || $block->getStateId() === $expectedState)){
						$block->onRedstoneScheduledUpdate($engine);
					}
				}
				if($idx >= $count){
					$this->delayedTicksHeap->extract();
					unset($this->delayed[$tick], $this->delayedCursor[$tick]);
				}else{
					$this->delayedCursor[$tick] = $idx;
					break;
				}
			}
		}
	}
}
