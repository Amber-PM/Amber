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
use pocketmine\math\Vector3;
use pocketmine\world\redstone\RedstoneEngine;
use pocketmine\world\World;
use function array_shift;
use function count;
use function sort;

class TorchBurnout{

	private const TOGGLES = 8;
	private const WINDOW = 60;

	protected array $toggles = [];

	protected array $burntOut = [];

	protected array $expectedStates = [];

	protected array $expectedWrites = [];

	protected array $torchIdentities = [];
	private TorchFeedbackTracker $feedback;

	public function __construct(){
		$this->feedback = new TorchFeedbackTracker($this);
	}

	public function isBurntOut(Vector3 $pos, int $currentTick) : bool{
		return isset($this->burntOut[self::hash($pos)]);
	}

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

	public function recordExtinguish(RedstoneTorch $torch, RedstoneEngine $engine) : void{
		$hash = self::hash($torch->getPosition());
		$check = $this->feedback->getCheck($hash);
		if($check !== null && $check->extinguishTick === null && ($check->dirty || !$this->feedback->isTopologyCurrent($check))){
			$this->cancelFeedback($torch->getPosition());
		}
		$result = $this->checkFeedback($torch, $engine, false);
		$check = $this->feedback->getCheck($hash);
		if($result === true){
			$this->recordToggle($torch->getPosition(), $engine, (clone $torch)->setLit(false)->getStateId());
			$this->cancelFeedback($torch->getPosition());
		}elseif($result === null && $check !== null){
			$check->extinguishTick = $engine->getCurrentTick();
		}else{
			$this->cancelFeedback($torch->getPosition());
		}
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
		$this->feedback->clear();
	}

	private static function hash(Vector3 $pos) : int{
		return World::blockHash($pos->getFloorX(), $pos->getFloorY(), $pos->getFloorZ());
	}

	public function getFeedbackTracker() : TorchFeedbackTracker{ return $this->feedback; }

	public function getIdentity(int $hash) : ?\stdClass{ return $this->torchIdentities[$hash] ?? null; }

	public function getOrCreateIdentity(int $hash) : \stdClass{ return $this->torchIdentities[$hash] ??= new \stdClass(); }

	public function checkFeedback(RedstoneTorch $torch, RedstoneEngine $engine, bool $consumeResult = true) : ?bool{
		return $this->feedback->checkFeedback($torch, $engine, $consumeResult);
	}
	public function cancelFeedback(Vector3 $pos, bool $includeExtinguishes = true) : void{
		$this->feedback->cancelFeedback($pos, $includeExtinguishes);
	}
	public function invalidateFeedbackAt(Block $block) : void{ $this->feedback->invalidateFeedbackAt($block); }
	public function processFeedback(RedstoneEngine $engine, int $budget) : int{ return $this->feedback->processFeedback($engine, $budget); }
}
