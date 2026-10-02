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
use pocketmine\block\RedstoneTorch;
use pocketmine\block\RedstoneWire;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\world\World;
use function array_key_last;
use function count;
use function max;
use function min;
use function sort;
use function array_shift;

final class TorchBurnout{
	// UNCONFIRMED Bedrock boundary; requires measurement in the vanilla game.
	private const TOGGLES = 8;
	private const WINDOW = 60;

	/** @var array<int, list<int>> torch hash => ticks it toggled at */
	private array $toggles = [];
	/** @var array<int, true> */
	private array $burntOut = [];
	/** @var array<int, int> */
	private array $expectedStates = [];
	/** @var array<int, int> one-shot authorization for an engine-driven state write */
	private array $expectedWrites = [];
	/** @var array<int, \stdClass> */
	private array $torchIdentities = [];
	/** @var array<int, TorchFeedbackCheck> */
	private array $feedbackChecks = [];
	/** @var \SplQueue<TorchFeedbackCheck> */
	private \SplQueue $feedbackQueue;
	/** @var array<int, int> */
	private array $positionVersions = [];
	/** @var array<int, int> */
	private array $positionStates = [];
	/** @var array<int, \SplObjectStorage<TorchFeedbackCheck, null>> */
	private array $positionWatchers = [];
	/** @var \SplQueue<int> */
	private \SplQueue $dirtyPositions;
	/** @var array<int, int> */
	private array $dirtyVersions = [];
	/** @var array<int, \stdClass> local watched-chunk topology tokens */
	private array $chunkVersions = [];
	private bool $dirtyTurn = false;

	public function __construct(){
		$this->feedbackQueue = new \SplQueue();
		$this->dirtyPositions = new \SplQueue();
	}

	public function isBurntOut(Vector3 $pos, int $currentTick) : bool{
		return isset($this->burntOut[self::hash($pos)]);
	}

	/** Only extinguishes with a verified, delay-free return path count towards burnout. */
	public function recordToggle(Vector3 $pos, RedstoneEngine $engine, ?int $expectedStateId = null, ?int $extinguishTick = null) : void{
		$hash = self::hash($pos);
		$currentTick = $engine->getCurrentTick();
		$recent = [];
		foreach($this->toggles[$hash] ?? [] as $tick){
			if($currentTick - $tick < self::WINDOW){
				$recent[] = $tick;
			}
		}
		$extinguishTick ??= $currentTick;
		if($currentTick - $extinguishTick < self::WINDOW){
			$recent[] = $extinguishTick;
		}
		sort($recent);
		if(count($recent) > self::TOGGLES){
			array_shift($recent);
		}
		if(count($recent) >= self::TOGGLES){
			$this->burntOut[$hash] = true;
		}
		if($expectedStateId !== null){
			$this->expectedStates[$hash] = $expectedStateId;
		}
		if($recent === []){
			unset($this->toggles[$hash]);
		}else{
			$this->toggles[$hash] = $recent;
		}
	}

	public function onUpdate(Vector3 $pos, int $currentTick) : void{
		$hash = self::hash($pos);
		if(isset($this->burntOut[$hash])){
			$recent = $this->toggles[$hash] ?? [];
			if($recent === [] || $currentTick - $recent[0] >= self::WINDOW){
				$this->forget($hash);
			}
		}
	}

	public function expectState(Vector3 $pos, int $stateId) : void{
		$this->expectedStates[self::hash($pos)] = $stateId;
		$this->expectedWrites[self::hash($pos)] = $stateId;
	}

	/** Called synchronously by World, before deferred neighbour notifications can coalesce writes. */
	public function onBlockChanged(Block $block) : void{
		$hash = self::hash($block->getPosition());
		if(isset($this->expectedStates[$hash]) || isset($this->torchIdentities[$hash])){
			if(($this->expectedWrites[$hash] ?? null) !== $block->getStateId()){
				$this->forget($hash);
			}
			unset($this->expectedWrites[$hash]);
		}
		$this->invalidateFeedbackAt($block);
	}

	public function forgetIfReplaced(Vector3 $pos, int $stateId) : void{
		$hash = self::hash($pos);
		if(isset($this->expectedStates[$hash]) && $this->expectedStates[$hash] !== $stateId){
			$this->forget($hash);
		}
	}

	public function checkFeedback(RedstoneTorch $torch, RedstoneEngine $engine, bool $consumeResult = true) : ?bool{
		$hash = self::hash($torch->getPosition());
		if(isset($this->feedbackChecks[$hash]) && $this->feedbackChecks[$hash]->extinguishTick !== null){
			unset($this->feedbackChecks[$hash]);
		}
		if(isset($this->feedbackChecks[$hash])){
			$check = $this->feedbackChecks[$hash];
			if($check->stateId === $torch->getStateId() && !$check->dirty && $this->isTopologyCurrent($check)){
				if($check->result === null){
					return null;
				}
				$result = $check->result;
				if($consumeResult){
					$this->cancelFeedback($torch->getPosition());
				}
				return $result;
			}
			$this->cancelFeedback($torch->getPosition());
		}
		$target = $torch->getPosition()->getSide(Facing::opposite($torch->getFacing()));
		if(!$engine->getWires()->isWireAreaLoaded($target)){
			return null;
		}
		$wireInput = false;
		foreach(Facing::ALL as $face){
			$pos = $target->getSide($face);
			$block = $engine->getWorld()->getBlockAt($pos->getFloorX(), $pos->getFloorY(), $pos->getFloorZ());
			if($block instanceof RedstoneWire && $block->getRedstoneOutput(Facing::opposite($face), false, $engine) > 0){
				$wireInput = true;
				break;
			}
		}
		if(!$wireInput){
			return false;
		}
		if(!$engine->getWires()->isWireAreaLoaded($torch->getPosition())){
			return null;
		}
		$seeds = $engine->getWires()->getTorchSeedWires($torch);
		if($seeds === []){
			return false;
		}
		$check = new TorchFeedbackCheck(
			$torch->getPosition()->asVector3(),
			$target->asVector3(),
			$torch->getStateId()
		);
		foreach($seeds as $pos){
			$check->pending->enqueue([$pos, 15]);
		}
		$this->observeArea($check, $engine, $torch->getPosition());
		$this->observeArea($check, $engine, $torch->getPosition()->getSide(Facing::UP));
		$this->observeArea($check, $engine, $target);
		$check->identity = $this->torchIdentities[$hash] ??= new \stdClass();
		$check->facing = $torch->getFacing();
		$this->feedbackChecks[$hash] = $check;
		$this->feedbackQueue->enqueue($check);
		return null;
	}

	public function recordExtinguish(RedstoneTorch $torch, RedstoneEngine $engine) : void{
		$hash = self::hash($torch->getPosition());
		if(isset($this->feedbackChecks[$hash]) && $this->feedbackChecks[$hash]->extinguishTick === null && ($this->feedbackChecks[$hash]->dirty || !$this->isTopologyCurrent($this->feedbackChecks[$hash]))){
			$this->cancelFeedback($torch->getPosition());
		}
		$result = $this->checkFeedback($torch, $engine, false);
		if($result === true){
			$this->recordToggle($torch->getPosition(), $engine, (clone $torch)->setLit(false)->getStateId());
			$this->cancelFeedback($torch->getPosition());
		}elseif($result === null && isset($this->feedbackChecks[$hash])){
			$this->feedbackChecks[$hash]->extinguishTick = $engine->getCurrentTick();
		}else{
			$this->cancelFeedback($torch->getPosition());
		}
	}

	private function isTopologyCurrent(TorchFeedbackCheck $check) : bool{
		// Power falls by one per wire: the 15-step path plus seed/observation halo spans
		// at most four chunks per horizontal axis (16 tokens), regardless of fanout.
		// A change elsewhere in a watched chunk conservatively discards this evidence.
		foreach($check->chunkVersions as [$token, $version]){
			if($token->version !== $version){
				return false;
			}
		}
		return true;
	}

	private function finishExtinguish(TorchFeedbackCheck $check, RedstoneEngine $engine) : void{
		$hash = self::hash($check->position);
		if(!$this->isTopologyCurrent($check)){
			$check->dirty = true;
		}
		if(!$check->dirty && !$check->cancelled && $check->result === true && ($this->torchIdentities[$hash] ?? null) === $check->identity){
			$torch = $engine->getWorld()->getBlockAt($check->position->getFloorX(), $check->position->getFloorY(), $check->position->getFloorZ());
			if($torch instanceof RedstoneTorch && $torch->getFacing() === $check->facing){
				$this->recordToggle($check->position, $engine, $torch->getStateId(), $check->extinguishTick);
				if($this->isBurntOut($check->position, $engine->getCurrentTick())){
					$engine->request($check->position->getFloorX(), $check->position->getFloorY(), $check->position->getFloorZ());
				}
			}
		}
		$check->cancelled = true;
		if(($this->feedbackChecks[$hash] ?? null) === $check){
			unset($this->feedbackChecks[$hash]);
		}
	}

	public function invalidateFeedbackAt(Block $block) : void{
		$hash = self::hash($block->getPosition());
		if(isset($this->positionStates[$hash])){
			$state = self::topologyState($block);
			if($this->positionStates[$hash] !== $state){
				$this->positionStates[$hash] = $state;
				++$this->positionVersions[$hash];
				++$this->chunkVersions[self::chunkHash($block->getPosition())]->version;
				if(!isset($this->dirtyVersions[$hash])){
					$this->dirtyVersions[$hash] = $this->positionVersions[$hash];
					$this->positionWatchers[$hash]->rewind();
					$this->dirtyPositions->enqueue($hash);
				}
			}
		}
	}

	private static function topologyState(Block $block) : int{
		if($block instanceof RedstoneWire){
			return (clone $block)->setOutputSignalStrength(0)->getStateId();
		}
		return $block instanceof RedstoneTorch ? (clone $block)->setLit(false)->getStateId() : $block->getStateId();
	}

	private function observeArea(TorchFeedbackCheck $check, RedstoneEngine $engine, Vector3 $pos) : void{
		$positions = [$pos, $pos->getSide(Facing::UP), $pos->getSide(Facing::DOWN)];
		foreach([Facing::NORTH, Facing::SOUTH, Facing::WEST, Facing::EAST] as $face){
			$side = $pos->getSide($face);
			$positions[] = $side;
			$positions[] = $side->getSide(Facing::UP);
			$positions[] = $side->getSide(Facing::DOWN);
		}
		foreach($positions as $observed){
			$hash = self::hash($observed);
			if(isset($check->versions[$hash])){
				continue;
			}
			if(!isset($this->positionWatchers[$hash])){
				$block = $engine->getWorld()->getBlockAt($observed->getFloorX(), $observed->getFloorY(), $observed->getFloorZ());
				$this->positionWatchers[$hash] = new \SplObjectStorage();
				$this->positionStates[$hash] = self::topologyState($block);
				$this->positionVersions[$hash] = 0;
				$chunkHash = self::chunkHash($observed);
				$token = $this->chunkVersions[$chunkHash] ??= (object) ["version" => 0, "positions" => 0];
				++$token->positions;
			}
			$chunkHash = self::chunkHash($observed);
			$token = $this->chunkVersions[$chunkHash];
			$check->chunkVersions[$chunkHash] ??= [$token, $token->version];
			$check->versions[$hash] = $this->positionVersions[$hash];
			$this->positionWatchers[$hash]->attach($check);
		}
	}

	public function cancelFeedback(Vector3 $pos, bool $includeExtinguishes = true) : void{
		$hash = self::hash($pos);
		if(isset($this->feedbackChecks[$hash]) && ($includeExtinguishes || $this->feedbackChecks[$hash]->extinguishTick === null)){
			$check = $this->feedbackChecks[$hash];
			$check->result = false;
			$check->cancelled = true;
			if(!$check->queued){
				$check->queued = true;
				$this->feedbackQueue->enqueue($check);
			}
			unset($this->feedbackChecks[$hash]);
		}
	}

	public function processFeedback(RedstoneEngine $engine, int $budget) : int{
		$steps = 0;
		$parkedChecks = 0;
		$parkedLimit = min($this->feedbackQueue->count(), max(32, $budget));
		while($steps < $budget){
			if(!$this->dirtyPositions->isEmpty() && ($this->dirtyTurn || $this->feedbackQueue->isEmpty() || $parkedChecks >= $parkedLimit)){
				$this->dirtyTurn = false;
				$this->processDirtyPosition();
				++$steps;
				continue;
			}
			if($this->feedbackQueue->isEmpty() || $parkedChecks >= $parkedLimit){
				break;
			}
			$this->dirtyTurn = true;
			$check = $this->feedbackQueue->dequeue();
			$check->queued = false;
			if($check->extinguishTick !== null && ($this->torchIdentities[self::hash($check->position)] ?? null) !== $check->identity){
				$check->result = false;
				$check->cancelled = true;
			}
			if($check->result === null && !$engine->getWorld()->isChunkLoaded($check->position->getFloorX() >> 4, $check->position->getFloorZ() >> 4)){
				$this->feedbackQueue->enqueue($check);
				$check->queued = true;
				++$parkedChecks;
				continue;
			}
			if($check->result === null && !$check->pending->isEmpty()){
				[$nextPos] = $check->pending->bottom();
				if(!$engine->getWires()->isWireAreaLoaded($nextPos)){
					$this->feedbackQueue->enqueue($check);
					$check->queued = true;
					++$parkedChecks;
					continue;
				}
			}
			++$steps;
			if($check->result === null){
				$torch = $engine->getWorld()->getBlockAt($check->position->getFloorX(), $check->position->getFloorY(), $check->position->getFloorZ());
				if(($check->extinguishTick === null && $torch->getStateId() !== $check->stateId) || !$torch instanceof RedstoneTorch || $torch->getFacing() !== $check->facing || $check->pending->isEmpty()){
					$check->result = false;
				}else{
					[$pos, $power] = $check->pending->dequeue();
					$hash = self::hash($pos);
					if(!isset($check->seen[$hash])){
						$check->seen[$hash] = true;
						$this->observeArea($check, $engine, $pos);
						$wire = $engine->getWorld()->getBlockAt($pos->getFloorX(), $pos->getFloorY(), $pos->getFloorZ());
						if($wire instanceof RedstoneWire){
							foreach(Facing::ALL as $face){
								if($pos->getSide($face)->equals($check->target) && (clone $wire)->setOutputSignalStrength($power)->getRedstoneOutput($face, false, $engine) > 0){
									$check->result = true;
									break;
								}
							}
							if($check->result === null && $power > 1){
								foreach($engine->getWires()->getTransmittedWireNeighbours($pos) as $next){
									$check->pending->enqueue([$next, $power - 1]);
								}
							}
						}
					}
				}
			}else{
				if(!$check->pending->isEmpty()){
					$check->pending->dequeue();
				}elseif($check->seen !== []){
					unset($check->seen[array_key_last($check->seen)]);
				}elseif($check->cancelled && $check->versions !== []){
					$hash = array_key_last($check->versions);
					$this->positionWatchers[$hash]->detach($check);
					unset($check->versions[$hash]);
					if($this->positionWatchers[$hash]->count() === 0 && !isset($this->dirtyVersions[$hash])){
						$this->releaseWatchedPosition($hash);
					}
				}
			}
			if($check->extinguishTick !== null && $check->result !== null && !$check->cancelled){
				$this->finishExtinguish($check, $engine);
			}
			if($check->result === null || !$check->pending->isEmpty() || $check->seen !== [] || ($check->cancelled && $check->versions !== [])){
				$this->feedbackQueue->enqueue($check);
				$check->queued = true;
			}
		}
		return $steps;
	}

	private function processDirtyPosition() : void{
		$hash = $this->dirtyPositions->bottom();
		$watchers = $this->positionWatchers[$hash] ?? null;
		if($watchers !== null && $watchers->valid()){
			$check = $watchers->current();
			if(($check->versions[$hash] ?? null) !== $this->positionVersions[$hash]){
				$check->dirty = true;
			}
			$watchers->next();
		}elseif($watchers !== null && $this->dirtyVersions[$hash] !== $this->positionVersions[$hash]){
			$this->dirtyVersions[$hash] = $this->positionVersions[$hash];
			$watchers->rewind();
		}else{
			$this->dirtyPositions->dequeue();
			unset($this->dirtyVersions[$hash]);
			if($watchers === null || $watchers->count() === 0){
				$this->releaseWatchedPosition($hash);
			}
		}
	}

	private function releaseWatchedPosition(int $hash) : void{
		World::getBlockXYZ($hash, $x, $y, $z);
		$chunkHash = World::chunkHash($x >> 4, $z >> 4);
		if(--$this->chunkVersions[$chunkHash]->positions === 0){
			unset($this->chunkVersions[$chunkHash]);
		}
		unset($this->positionWatchers[$hash], $this->positionStates[$hash], $this->positionVersions[$hash]);
	}

	private static function chunkHash(Vector3 $pos) : int{
		return World::chunkHash($pos->getFloorX() >> 4, $pos->getFloorZ() >> 4);
	}

	public function forget(int $hash) : void{
		World::getBlockXYZ($hash, $x, $y, $z);
		$this->cancelFeedback(new Vector3($x, $y, $z));
		unset($this->toggles[$hash], $this->burntOut[$hash], $this->expectedStates[$hash], $this->expectedWrites[$hash], $this->torchIdentities[$hash]);
	}

	public function clear() : void{
		$this->toggles = [];
		$this->burntOut = [];
		$this->expectedStates = [];
		$this->expectedWrites = [];
		$this->torchIdentities = [];
		$this->feedbackChecks = [];
		$this->feedbackQueue = new \SplQueue();
		$this->dirtyPositions = new \SplQueue();
		$this->positionVersions = [];
		$this->positionStates = [];
		$this->positionWatchers = [];
		$this->dirtyVersions = [];
		$this->chunkVersions = [];
		$this->dirtyTurn = false;
	}

	private static function hash(Vector3 $pos) : int{
		return World::blockHash($pos->getFloorX(), $pos->getFloorY(), $pos->getFloorZ());
	}
}
