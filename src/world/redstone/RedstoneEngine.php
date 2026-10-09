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
use pocketmine\block\utils\RedstoneReceiver;
use pocketmine\block\utils\RedstoneSource;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\world\World;
use function count;
use function intdiv;
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
	/** @var array<int, true> positions last seen powered by blocks that react to changes of power (doors, TNT...) */
	private array $lastPowered = [];
	private bool $active = false;
	private int $processed = 0;
	private int $currentTick = 0;
	private RedstoneBudget $budget;
	private RedstoneScheduler $scheduler;
	private RedstonePower $power;
	private bool $isTicking = false;
	private bool $feedbackTurn = false;
	private int $workCursor = 0;
	private array $entityScans = [];

	private WireNetwork $wires;
	private TorchBurnout $torchBurnout;
	private ContainerWatch $containerWatch;

	public function __construct(
		private World $world,
		private int $maxUpdatesPerTick
	){
		$this->queue = new \SplQueue();
		$this->budget = new RedstoneBudget();
		$this->scheduler = new RedstoneScheduler($world, $maxUpdatesPerTick);
		$this->power = new RedstonePower($this, $world);
		$this->wires = new WireNetwork($this, $world);
		$this->torchBurnout = new TorchBurnout();
		$this->containerWatch = new ContainerWatch($world);
	}

	public function getScheduler() : RedstoneScheduler{ return $this->scheduler; }

	public function getWorld() : World{ return $this->world; }

	public function getCurrentTick() : int{ return $this->currentTick; }

	public function isTicking() : bool{ return $this->isTicking; }

	public function getMaxUpdatesPerTick() : int{ return $this->maxUpdatesPerTick; }

	public function getRemainingBudget() : int{ return $this->budget->remaining(); }

	public function consumeBudget(int $amount) : int{ return $this->budget->consume($amount); }

	public function getUnloadedDelayedCount() : int{
		return $this->scheduler->getUnloadedDelayedCount();
	}

	public function clear() : void{
		$this->active = false;
		$this->workCursor = 0;
		$this->feedbackTurn = false;
		$this->entityScans = [];
		$this->queue = new \SplQueue();
		$this->queued = [];
		$this->scheduler->clear();
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
		return RedstonePower::isConductor($block);
	}

	/**
	 * Called for every block that gets a neighbour update. Components are queued; a conductor queues the components
	 * around it, since power passes through it.
	 */
	private ?int $updatingReceiver = null;

	public function onBlockChanged(Block $block) : void{
		$pos = $block->getPosition();
		$hash = World::blockHash($pos->getFloorX(), $pos->getFloorY(), $pos->getFloorZ());
		$this->cancelEntityScan($pos);
		if($this->updatingReceiver !== $hash){
			$this->scheduler->cancelSchedule($pos);
		}
		$this->torchBurnout->onBlockChanged($block);
	}

	public function cancelEntityScan(Vector3 $pos) : void{
		$chunk = World::chunkHash($pos->getFloorX() >> 4, $pos->getFloorZ() >> 4);
		$hash = World::blockHash($pos->getFloorX(), $pos->getFloorY(), $pos->getFloorZ());
		unset($this->entityScans[$chunk][$hash]);
		if(($this->entityScans[$chunk] ?? []) === []){
			unset($this->entityScans[$chunk]);
		}
	}

	public function onChunkUnloaded(int $x, int $z) : void{
		unset($this->entityScans[World::chunkHash($x, $z)]);
	}

	public function collectEntities(Block $block, AxisAlignedBB $area, int $maxMatches, \Closure $filter) : ?array{
		$pos = $block->getPosition();
		$chunk = World::chunkHash($pos->getFloorX() >> 4, $pos->getFloorZ() >> 4);
		$hash = World::blockHash($pos->getFloorX(), $pos->getFloorY(), $pos->getFloorZ());
		$scan = $this->entityScans[$chunk][$hash] ?? null;
		if($scan === null || $scan->getStateId() !== $block->getStateId()){
			$scan = $this->entityScans[$chunk][$hash] = new EntityScan($this->world, $area, $block->getStateId());
		}
		$result = $scan->poll(min(64, max(1, $this->budget->remaining() + 1)), max(1, $maxMatches), $filter);
		$this->budget->consume(max(0, $scan->getWork() - 1));
		if($result !== null){
			$this->cancelEntityScan($pos);
		}
		return $result;
	}

	public function updateReceiverState(Block $block) : void{
		$pos = $block->getPosition();
		$previous = $this->updatingReceiver;
		$this->updatingReceiver = World::blockHash($pos->getFloorX(), $pos->getFloorY(), $pos->getFloorZ());
		$this->scheduler->updateExpectedState($pos, $block->getStateId());
		try{
			$this->world->setBlock($pos, $block);
		}finally{
			$this->updatingReceiver = $previous;
		}
	}

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
			if($this->scheduler->hasDifferentState($hash, $block->getStateId())){
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
			if(!$this->world->isInWorld($x + $dx, $y + $dy, $z + $dz) || !$this->world->isChunkLoaded(($x + $dx) >> 4, ($z + $dz) >> 4)){
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
			if($this->world->isInWorld($x + $dx, $y + $dy, $z + $dz) && $this->world->isChunkLoaded(($x + $dx) >> 4, ($z + $dz) >> 4) && self::isComponent($this->world->getBlockAt($x + $dx, $y + $dy, $z + $dz))){
				$this->request($x + $dx, $y + $dy, $z + $dz);
			}
		}
	}

	/**
	 * Calls onRedstoneScheduledUpdate() on the block at the position after $delay game ticks. A block that already
	 * has a change pending keeps that one; it is re-evaluated when it happens.
	 */
	public function schedule(Vector3 $pos, int $delay, ?int $expectedStateId = null) : void{
		$this->active = true;
		$this->scheduler->schedule($pos, $delay, $this->currentTick, $expectedStateId);
	}

	public function cancelSchedule(Vector3 $pos) : void{
		$this->scheduler->cancelSchedule($pos);
	}

	public function isScheduled(Vector3 $pos) : bool{
		return $this->scheduler->isScheduled($pos);
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
		$this->budget->reset($this->maxUpdatesPerTick);
		$this->isTicking = true;
		try{
			$otherWork = $this->scheduler->hasPending() || $this->wires->hasDeferred() || !$this->queue->isEmpty();
			$feedbackBudget = $this->budget->remaining();
			if($otherWork){
				if($feedbackBudget === 1){
					$this->feedbackTurn = !$this->feedbackTurn;
					$feedbackBudget = $this->feedbackTurn ? 0 : 1;
				}else{
					$feedbackBudget = max(1, (int) ($feedbackBudget / 4));
				}
			}
			$this->budget->consume($this->torchBurnout->processFeedback($this, $feedbackBudget));
			$this->wires->startTick();
			$pending = [
				$this->scheduler->hasPending(),
				$currentTick % self::REDSTONE_TICK === 0 && $this->containerWatch->getWatchedCount() > 0,
				$this->wires->hasDeferred(),
				!$this->queue->isEmpty()
			];
			$stages = 0;
			foreach($pending as $hasWork){
				$stages += (int) $hasWork;
			}
			$start = $this->budget->remaining() < $stages ? $this->workCursor : 0;
			for($i = 0; $i < count($pending) && $this->budget->remaining() > 0; ++$i){
				$phase = ($start + $i) % count($pending);
				if($phase === 3 && !$pending[$phase] && !$this->queue->isEmpty()){
					$pending[$phase] = true;
					++$stages;
				}
				if(!$pending[$phase]){
					continue;
				}
				$limit = max(1, intdiv($this->budget->remaining(), max(1, $stages--)));
				$reserved = $this->budget->remaining() - $limit;
				$this->budget->reset($limit);
				try{
					switch($phase){
						case 0:
							$this->scheduler->tick($this, $this->budget, $currentTick);
							break;
						case 1:
							$this->budget->consume($this->containerWatch->check($this, $limit));
							break;
						case 2:
							$this->budget->consume($this->wires->processDeferred($limit));
							break;
						case 3:
							$this->processQueuedUpdates();
							break;
					}
				}finally{
					if($this->budget->remaining() < $limit){
						$this->workCursor = ($phase + 1) % count($pending);
					}
					$this->budget->reset($reserved + $this->budget->remaining());
				}
			}
		}finally{
			$this->isTicking = false;
			$this->budget->reset(0);
		}
	}

	private function processQueuedUpdates() : void{
		$unloadedSkips = 0;
		$unloadedSkipLimit = max(1, $this->budget->remaining());
		while($this->budget->remaining() > 0 && !$this->queue->isEmpty()){
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
			$this->budget->consume(1);
			$block = $this->world->getBlockAt($x, $y, $z);
			if($block instanceof RedstoneReceiver){
				$block->onRedstoneUpdate($this);
			}
		}
	}

	/** Power the block sends through its face $face (see RedstoneSource::getRedstoneOutput()). */
	public function getOutput(Block $block, int $face, bool $strongOnly) : int{
		return $this->power->getOutput($block, $face, $strongOnly);
	}

	/** Power the block at the position gets from the blocks around it: all of it, or only strong power. */
	public function getPowerIntoBlock(Vector3 $pos, bool $strongOnly) : int{
		return $this->power->getPowerIntoBlock($pos, $strongOnly);
	}

	/** Power a component at the position receives: from sources and wire beside it, and through powered blocks. */
	public function getReceivedPower(Vector3 $pos) : int{
		return $this->power->getReceivedPower($pos);
	}

	/** Power a component gets from the block at $from, whose face $face points at the component. */
	public function getPowerFrom(Vector3 $from, int $face) : int{
		return $this->power->getPowerFrom($from, $face);
	}
}
