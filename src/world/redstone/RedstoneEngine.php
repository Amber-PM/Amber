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
	/** @var array<int, int> block hash => tick its delayed change is due */
	private array $delayedIndex = [];
	/** @var array<int, true> positions last seen powered by blocks that react to changes of power (doors, TNT...) */
	private array $lastPowered = [];
	private bool $active = false;
	private int $processed = 0;
	private int $currentTick = 0;

	private WireNetwork $wires;
	private TorchBurnout $torchBurnout;
	private ContainerWatch $containerWatch;

	public function __construct(
		private World $world,
		private int $maxUpdatesPerTick
	){
		$this->queue = new \SplQueue();
		$this->wires = new WireNetwork($this, $world);
		$this->torchBurnout = new TorchBurnout();
		$this->containerWatch = new ContainerWatch($world);
	}

	public function getWorld() : World{ return $this->world; }

	public function getCurrentTick() : int{ return $this->currentTick; }

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
		if(self::isComponent($block)){
			$this->active = true;
			$this->request($pos->getFloorX(), $pos->getFloorY(), $pos->getFloorZ());
			return;
		}
		if(!$this->active){
			return;
		}
		//whatever was here is gone: forget its state
		$hash = World::blockHash($pos->getFloorX(), $pos->getFloorY(), $pos->getFloorZ());
		unset($this->lastPowered[$hash]);
		$this->torchBurnout->forget($hash);
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
	public function schedule(Vector3 $pos, int $delay) : void{
		$hash = World::blockHash($pos->getFloorX(), $pos->getFloorY(), $pos->getFloorZ());
		if(isset($this->delayedIndex[$hash])){
			return;
		}
		$due = $this->currentTick + max(1, $delay);
		$this->delayedIndex[$hash] = $due;
		$this->delayed[$due][] = $hash;
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
		foreach($this->delayed as $tick => $hashes){
			if($tick > $currentTick){
				continue;
			}
			unset($this->delayed[$tick]);
			foreach($hashes as $hash){
				if(($this->delayedIndex[$hash] ?? null) !== $tick){
					continue;
				}
				unset($this->delayedIndex[$hash]);
				World::getBlockXYZ($hash, $x, $y, $z);
				if($this->world->isChunkLoaded($x >> 4, $z >> 4)){
					$block = $this->world->getBlockAt($x, $y, $z);
					if($block instanceof DelayedRedstoneReceiver){
						$block->onRedstoneScheduledUpdate($this);
					}
				}
			}
		}

		if($currentTick % self::REDSTONE_TICK === 0){
			$this->containerWatch->check($this);
		}

		$this->wires->startTick();
		$budget = min($this->queue->count(), $this->maxUpdatesPerTick);
		for($i = 0; $i < $budget; ++$i){
			$hash = $this->queue->dequeue();
			unset($this->queued[$hash]);
			World::getBlockXYZ($hash, $x, $y, $z);
			if(!$this->world->isChunkLoaded($x >> 4, $z >> 4)){
				continue;
			}
			++$this->processed;
			$block = $this->world->getBlockAt($x, $y, $z);
			if($block instanceof RedstoneReceiver){
				$block->onRedstoneUpdate($this);
			}
			//sources (levers, buttons, plates...) need nothing here: the world's neighbour updates for their change
			//already reach what they power
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
