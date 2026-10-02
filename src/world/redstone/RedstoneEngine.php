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
use pocketmine\block\Redstone;
use pocketmine\block\RedstoneTorch;
use pocketmine\block\utils\DelayedRedstoneReceiver;
use pocketmine\block\utils\RedstoneReceiver;
use pocketmine\block\utils\RedstoneSource;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\world\World;
use function max;
use function min;

/**
 * Redstone for one world. The engine queues and schedules updates and answers power queries; what each block does
 * with power lives in the block (RedstoneSource, RedstoneReceiver), with wire networks, torch burnout and comparators'
 * containers handled by WireNetwork, TorchBurnout and ContainerWatch.
 *
 * Changes near redstone blocks are queued, and at most a set number of positions are evaluated per tick, so a large
 * or looping circuit slows down instead of stalling the server.
 */
final class RedstoneEngine{
	/** Game ticks per redstone tick. */
	public const REDSTONE_TICK = 2;

	/** @var \SplQueue<int> */
	private \SplQueue $queue;
	/** @var array<int, true> */
	private array $queued = [];
	/** @var array<int, list<int>> tick => block hashes whose delayed change is due */
	private array $delayed = [];
	/** @var array<int, int> due tick => offset index of next element to process */
	private array $delayedCursor = [];
	/** @var \SplMinHeap<int> */
	private \SplMinHeap $delayedTicksHeap;
	/** @var array<int, int> block hash => tick its delayed change is due */
	private array $delayedIndex = [];
	/** @var array<int, int> block hash => state ID of block when scheduled */
	private array $delayedState = [];
	/** @var array<int, array<int, int>> chunkHash => [blockHash => dueTick] */
	private array $unloadedDelayed = [];
	private int $unloadedDelayedCount = 0;
	/** @var array<int, true> positions last seen powered by blocks that react to changes of power (doors, TNT...) */
	private array $lastPowered = [];
	private bool $active = false;
	private int $processed = 0;
	private int $currentTick = 0;
	private int $currentBudget = 0;
	private bool $isTicking = false;
	private bool $feedbackTurn = false;

	private WireNetwork $wires;
	private TorchBurnout $torchBurnout;
	private ContainerWatch $containerWatch;

	public function __construct(
		private World $world,
		private int $maxUpdatesPerTick
	){
		$this->queue = new \SplQueue();
		$this->delayedTicksHeap = new \SplMinHeap();
		$this->wires = new WireNetwork($this, $world);
		$this->torchBurnout = new TorchBurnout();
		$this->containerWatch = new ContainerWatch($world);
	}

	public function getWorld() : World{ return $this->world; }

	public function getCurrentTick() : int{ return $this->currentTick; }

	public function isTicking() : bool{ return $this->isTicking; }

	public function getMaxUpdatesPerTick() : int{ return $this->maxUpdatesPerTick; }

	public function getRemainingBudget() : int{ return $this->currentBudget; }

	public function consumeBudget(int $amount) : int{
		$consumed = min($this->currentBudget, $amount);
		$this->currentBudget -= $consumed;
		return $consumed;
	}

	public function getUnloadedDelayedCount() : int{
		return $this->unloadedDelayedCount;
	}

	public function clear() : void{
		$this->active = false;
		$this->queue = new \SplQueue();
		$this->queued = [];
		$this->delayed = [];
		$this->delayedCursor = [];
		$this->delayedTicksHeap = new \SplMinHeap();
		$this->delayedIndex = [];
		$this->delayedState = [];
		$this->unloadedDelayed = [];
		$this->unloadedDelayedCount = 0;
		$this->lastPowered = [];
		$this->wires->clear();
		$this->torchBurnout->clear();
		$this->containerWatch->clear();
	}

	public function getWires() : WireNetwork{ return $this->wires; }

	public function getTorchBurnout() : TorchBurnout{ return $this->torchBurnout; }

	public function getContainerWatch() : ContainerWatch{ return $this->containerWatch; }

	/** Positions evaluated since the engine was created. */
	public function getProcessedCount() : int{ return $this->processed; }

	public static function isComponent(Block $block) : bool{
		return $block instanceof RedstoneSource || $block instanceof RedstoneReceiver;
	}

	/** A block redstone power can pass through (a full, non-transparent block). */
	public static function isConductor(Block $block) : bool{
		return $block->isSolid() && !$block->isTransparent() && $block->isFullCube() && !$block instanceof Redstone;
	}

	/**
	 * Called for every block that gets a neighbour update. Components are queued; a conductor queues the components
	 * around it, since power passes through it.
	 */
	public function onNeighbourUpdate(Block $block) : void{
		$pos = $block->getPosition();
		$this->torchBurnout->invalidateFeedbackAt($block);
		$hash = World::blockHash($pos->getFloorX(), $pos->getFloorY(), $pos->getFloorZ());
		$this->wires->invalidate($hash);
		if(self::isComponent($block)){
			$this->active = true;
			if($block instanceof RedstoneTorch){
				$this->torchBurnout->forgetIfReplaced($pos, $block->getStateId());
			}
			if(isset($this->delayedState[$hash]) && $this->delayedState[$hash] !== $block->getStateId()){
				$this->cancelSchedule($pos);
				$this->torchBurnout->forget($hash);
			}
			if(!$block instanceof RedstoneTorch){
				$this->torchBurnout->forget($hash);
			}
			$this->request($pos->getFloorX(), $pos->getFloorY(), $pos->getFloorZ());
			return;
		}
		$this->cancelSchedule($pos);
		$this->torchBurnout->forget($hash);
		if(!$this->active){
			return;
		}
		//whatever was here is gone: forget its state
		unset($this->lastPowered[$hash]);
		if(self::isConductor($block)){
			$this->requestComponentsAround($pos->getFloorX(), $pos->getFloorY(), $pos->getFloorZ());
		}
	}

	public function request(int $x, int $y, int $z) : void{
		if(!$this->world->isInWorld($x, $y, $z)){
			return;
		}
		$hash = World::blockHash($x, $y, $z);
		if(!isset($this->queued[$hash])){
			$this->queued[$hash] = true;
			$this->queue->enqueue($hash);
		}
	}

	/**
	 * Queues the components a change of power at this position can reach: those beside it, and those beside the
	 * conductors beside it.
	 */
	public function requestAround(Vector3 $pos) : void{
		$x = $pos->getFloorX();
		$y = $pos->getFloorY();
		$z = $pos->getFloorZ();
		foreach(Facing::OFFSET as [$dx, $dy, $dz]){
			if(!$this->world->isInWorld($x + $dx, $y + $dy, $z + $dz)){
				continue;
			}
			$block = $this->world->getBlockAt($x + $dx, $y + $dy, $z + $dz);
			if(self::isComponent($block)){
				$this->request($x + $dx, $y + $dy, $z + $dz);
			}elseif(self::isConductor($block)){
				$this->requestComponentsAround($x + $dx, $y + $dy, $z + $dz);
			}
		}
	}

	private function requestComponentsAround(int $x, int $y, int $z) : void{
		foreach(Facing::OFFSET as [$dx, $dy, $dz]){
			if($this->world->isInWorld($x + $dx, $y + $dy, $z + $dz) && self::isComponent($this->world->getBlockAt($x + $dx, $y + $dy, $z + $dz))){
				$this->request($x + $dx, $y + $dy, $z + $dz);
			}
		}
	}

	/**
	 * Calls onRedstoneScheduledUpdate() on the block at the position after $delay game ticks. A block that already
	 * has a change pending keeps that one; it is re-evaluated when it happens.
	 */
	public function schedule(Vector3 $pos, int $delay, ?int $expectedStateId = null) : void{
		$x = $pos->getFloorX();
		$y = $pos->getFloorY();
		$z = $pos->getFloorZ();
		if(!$this->world->isInWorld($x, $y, $z)){
			return;
		}
		$hash = World::blockHash($x, $y, $z);
		$stateId = $expectedStateId ?? $this->world->getBlockAt($x, $y, $z)->getStateId();
		if(isset($this->delayedIndex[$hash])){
			if(($this->delayedState[$hash] ?? null) === $stateId){
				return;
			}
		}
		$this->active = true;
		$due = $this->currentTick + max(1, $delay);
		$this->delayedIndex[$hash] = $due;
		$this->delayedState[$hash] = $stateId;
		if(!isset($this->delayed[$due])){
			$this->delayed[$due] = [];
			$this->delayedTicksHeap->insert($due);
		}
		$this->delayed[$due][] = $hash;
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

	/**
	 * Records the power seen at a position by a block that reacts to changes of power (doors, trapdoors, TNT, note
	 * blocks); true when it changed since the last call, or on the first call when powered.
	 */
	public function powerChanged(Vector3 $pos, bool $powered) : bool{
		$hash = World::blockHash($pos->getFloorX(), $pos->getFloorY(), $pos->getFloorZ());
		$previous = isset($this->lastPowered[$hash]);
		if($powered){
			$this->lastPowered[$hash] = true;
		}else{
			unset($this->lastPowered[$hash]);
		}
		return $previous !== $powered;
	}

	public function tick(int $currentTick) : void{
		$this->currentTick = $currentTick;
		$this->currentBudget = $this->maxUpdatesPerTick;
		$this->isTicking = true;
		try{
			$otherWork = $this->delayed !== [] || $this->unloadedDelayed !== [] || $this->wires->hasDeferred() || !$this->queue->isEmpty();
			$feedbackBudget = $this->currentBudget;
			if($otherWork){
				if($feedbackBudget === 1){
					$this->feedbackTurn = !$this->feedbackTurn;
					$feedbackBudget = $this->feedbackTurn ? 0 : 1;
				}else{
					$feedbackBudget = max(1, (int) ($feedbackBudget / 4));
				}
			}
			$this->currentBudget -= $this->torchBurnout->processFeedback($this, $feedbackBudget);
			if($this->unloadedDelayed !== [] && $this->currentBudget > 0){
				$chunkCheckLimit = min(count($this->unloadedDelayed), max(32, $this->currentBudget));
				$checked = 0;
				foreach($this->unloadedDelayed as $chunkHash => $events){
					if($this->currentBudget <= 0 || ++$checked > $chunkCheckLimit){
						break;
					}

					$chunkX = null;
					$chunkZ = null;
					World::getXZ($chunkHash, $chunkX, $chunkZ);
					if($chunkX === null || $chunkZ === null || !$this->world->isChunkLoaded($chunkX, $chunkZ)){
						unset($this->unloadedDelayed[$chunkHash]);
						$this->unloadedDelayed[$chunkHash] = $events;
						continue;
					}

					$staleChecks = 0;
					$staleLimit = min(count($events), max(32, $this->currentBudget));
					foreach($events as $hash => $dueTick){
						if($this->currentBudget <= 0){
							break;
						}
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

						--$this->currentBudget;
						World::getBlockXYZ($hash, $x, $y, $z);
						$block = $this->world->getBlockAt($x, $y, $z);
						if($block instanceof DelayedRedstoneReceiver && ($expectedState === null || $block->getStateId() === $expectedState)){
							$block->onRedstoneScheduledUpdate($this);
						}
					}

					if($this->unloadedDelayed[$chunkHash] === []){
						unset($this->unloadedDelayed[$chunkHash]);
					}else{
						$remaining = $this->unloadedDelayed[$chunkHash];
						unset($this->unloadedDelayed[$chunkHash]);
						$this->unloadedDelayed[$chunkHash] = $remaining;
					}
				}
			}

			if(!$this->delayedTicksHeap->isEmpty()){
				$staleChecks = 0;
				$staleLimit = $this->maxUpdatesPerTick * 4;
				while(!$this->delayedTicksHeap->isEmpty() && $this->currentBudget > 0){
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
					while($idx < $count && $this->currentBudget > 0){
						$hash = $hashes[$idx++];
						if(($this->delayedIndex[$hash] ?? null) !== $tick){
							if(++$staleChecks >= $staleLimit){
								break;
							}
							continue;
						}
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
						--$this->currentBudget;
						$block = $this->world->getBlockAt($x, $y, $z);
						if($block instanceof DelayedRedstoneReceiver && ($expectedState === null || $block->getStateId() === $expectedState)){
							$block->onRedstoneScheduledUpdate($this);
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

			if($this->currentBudget > 0 && $currentTick % self::REDSTONE_TICK === 0 && $this->containerWatch->getWatchedCount() > 0){
				$limit = min($this->currentBudget, max(1, (int) ($this->currentBudget / 4)));
				$consumed = $this->containerWatch->check($this, $limit);
				$this->currentBudget -= $consumed;
			}

			$this->wires->startTick();
			if($this->wires->hasDeferred() && $this->currentBudget > 0){
				$deferredBudget = $this->queue->isEmpty()
					? $this->currentBudget
					: min($this->currentBudget, max(1, (int) ($this->currentBudget / 2)));
				$consumed = $this->wires->processDeferred($deferredBudget);
				$this->currentBudget -= $consumed;
			}

			$unloadedSkips = 0;
			$unloadedSkipLimit = max(1, $this->currentBudget);
			while($this->currentBudget > 0 && !$this->queue->isEmpty()){
				$hash = $this->queue->dequeue();
				unset($this->queued[$hash]);
				World::getBlockXYZ($hash, $x, $y, $z);
				if(!$this->world->isChunkLoaded($x >> 4, $z >> 4)){
					if(++$unloadedSkips >= $unloadedSkipLimit){
						break;
					}
					continue;
				}
				++$this->processed;
				--$this->currentBudget;
				$block = $this->world->getBlockAt($x, $y, $z);
				if($block instanceof RedstoneReceiver){
					$block->onRedstoneUpdate($this);
				}
			}
		}finally{
			$this->isTicking = false;
			$this->currentBudget = 0;
		}
	}

	/** Power the block sends through its face $face (see RedstoneSource::getRedstoneOutput()). */
	public function getOutput(Block $block, int $face, bool $strongOnly) : int{
		return $block instanceof RedstoneSource ? $block->getRedstoneOutput($face, $strongOnly, $this) : 0;
	}

	/** Power the block at the position gets from the blocks around it: all of it, or only strong power. */
	public function getPowerIntoBlock(Vector3 $pos, bool $strongOnly) : int{
		$x = $pos->getFloorX();
		$y = $pos->getFloorY();
		$z = $pos->getFloorZ();
		$power = 0;
		foreach(Facing::ALL as $face){
			[$dx, $dy, $dz] = Facing::OFFSET[$face];
			$power = max($power, $this->getOutput($this->world->getBlockAt($x + $dx, $y + $dy, $z + $dz), Facing::opposite($face), $strongOnly));
			if($power >= 15){
				break;
			}
		}
		return $power;
	}

	/** Power a component at the position receives: from sources and wire beside it, and through powered blocks. */
	public function getReceivedPower(Vector3 $pos) : int{
		$power = 0;
		foreach(Facing::ALL as $face){
			$power = max($power, $this->getPowerFrom($pos->getSide($face), Facing::opposite($face)));
			if($power >= 15){
				break;
			}
		}
		return $power;
	}

	/** Power a component gets from the block at $from, whose face $face points at the component. */
	public function getPowerFrom(Vector3 $from, int $face) : int{
		if(!$this->world->isInWorld($from->getFloorX(), $from->getFloorY(), $from->getFloorZ())){
			return 0;
		}
		$block = $this->world->getBlockAt($from->getFloorX(), $from->getFloorY(), $from->getFloorZ());
		$power = $this->getOutput($block, $face, false);
		if($power < 15 && self::isConductor($block)){
			$power = max($power, $this->getPowerIntoBlock($from, false));
		}
		return $power;
	}
}
