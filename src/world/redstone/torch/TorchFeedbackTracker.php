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

namespace pocketmine\world\redstone\torch;

use pocketmine\block\Block;
use pocketmine\block\RedstoneTorch;
use pocketmine\block\RedstoneWire;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\world\redstone\RedstoneEngine;
use pocketmine\world\World;
use function array_key_last;
use function max;
use function min;

final class TorchFeedbackTracker{

	private array $feedbackChecks = [];

	private \SplQueue $feedbackQueue;

	private array $positionVersions = [];

	private array $positionStates = [];

	private array $positionWatchers = [];

	private \SplQueue $dirtyPositions;

	private array $dirtyVersions = [];

	private array $chunkVersions = [];
	private bool $dirtyTurn = false;

	public function __construct(private TorchBurnout $burnout){
		$this->feedbackQueue = new \SplQueue();
		$this->dirtyPositions = new \SplQueue();
	}
	public function getCheck(int $hash) : ?TorchFeedbackCheck{ return $this->feedbackChecks[$hash] ?? null; }
	public function clear() : void{
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
		$check->identity = $this->burnout->getOrCreateIdentity($hash);
		$check->facing = $torch->getFacing();
		$this->feedbackChecks[$hash] = $check;
		$this->feedbackQueue->enqueue($check);
		return null;
	}

	public function isTopologyCurrent(TorchFeedbackCheck $check) : bool{

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
		if(!$check->dirty && !$check->cancelled && $check->result === true && $this->burnout->getIdentity($hash) === $check->identity){
			$torch = $engine->getWorld()->getBlockAt($check->position->getFloorX(), $check->position->getFloorY(), $check->position->getFloorZ());
			if($torch instanceof RedstoneTorch && $torch->getFacing() === $check->facing){
				$this->burnout->recordToggle($check->position, $engine, $torch->getStateId(), $check->extinguishTick);
				if($this->burnout->isBurntOut($check->position, $engine->getCurrentTick())){
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
			if($check->extinguishTick !== null && $this->burnout->getIdentity(self::hash($check->position)) !== $check->identity){
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

	private static function hash(Vector3 $pos) : int{
		return World::blockHash($pos->getFloorX(), $pos->getFloorY(), $pos->getFloorZ());
	}
}
