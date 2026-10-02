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

namespace pocketmine\block;

use pocketmine\block\utils\DelayedRedstoneReceiver;
use pocketmine\block\utils\Lightable;
use pocketmine\block\utils\LightableTrait;
use pocketmine\block\utils\RedstoneSource;
use pocketmine\data\runtime\RuntimeDataDescriber;
use pocketmine\math\Facing;
use pocketmine\world\redstone\RedstoneEngine;

class RedstoneTorch extends Torch implements Lightable, RedstoneSource, DelayedRedstoneReceiver{
	use LightableTrait;

	public function __construct(BlockIdentifier $idInfo, string $name, BlockTypeInfo $typeInfo){
		$this->lit = true;
		parent::__construct($idInfo, $name, $typeInfo);
	}

	protected function describeBlockOnlyState(RuntimeDataDescriber $w) : void{
		parent::describeBlockOnlyState($w);
		$w->bool($this->lit);
	}

	public function getLightLevel() : int{
		return $this->lit ? 7 : 0;
	}

	public function getRedstoneOutput(int $face, bool $strongOnly, RedstoneEngine $engine) : int{
		if(!$this->lit || $face === Facing::opposite($this->facing)){
			return 0; //not into the block it is attached to
		}
		return !$strongOnly || $face === Facing::UP ? 15 : 0;
	}

	/** A torch is lit unless the block it is attached to is powered (or it is burnt out). */
	private function shouldBeLit(RedstoneEngine $engine) : bool{
		if($engine->getTorchBurnout()->isBurntOut($this->position, $engine->getCurrentTick())){
			return false;
		}
		return $engine->getPowerIntoBlock($this->position->getSide(Facing::opposite($this->facing)), false) === 0;
	}

	public function onRedstoneUpdate(RedstoneEngine $engine) : void{
		$engine->getTorchBurnout()->onUpdate($this->position, $engine->getCurrentTick());
		$lit = $this->shouldBeLit($engine);
		if($lit !== $this->lit){
			if($this->lit){
				$engine->getTorchBurnout()->checkFeedback($this, $engine, false);
			}
			$engine->schedule($this->position, RedstoneEngine::REDSTONE_TICK);
		}
	}

	public function onRedstoneScheduledUpdate(RedstoneEngine $engine) : void{
		$lit = $this->shouldBeLit($engine);
		if($lit === $this->lit){
			$engine->getTorchBurnout()->cancelFeedback($this->position, false);
			return;
		}
		if($this->lit && !$lit && !$engine->getTorchBurnout()->isBurntOut($this->position, $engine->getCurrentTick())){
			$engine->getTorchBurnout()->recordExtinguish($this, $engine);
		}
		if($lit !== $this->lit){
			$changed = (clone $this)->setLit($lit);
			$engine->getTorchBurnout()->expectState($this->position, $changed->getStateId());
			$engine->getWorld()->setBlock($this->position, $changed);
			$engine->requestAround($this->position);
		}
	}
}
