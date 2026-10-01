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
use pocketmine\block\utils\PoweredByRedstone;
use pocketmine\block\utils\PoweredByRedstoneTrait;
use pocketmine\data\runtime\RuntimeDataDescriber;
use pocketmine\world\redstone\RedstoneEngine;

class RedstoneLamp extends Opaque implements PoweredByRedstone, Lightable, DelayedRedstoneReceiver{
	use PoweredByRedstoneTrait;

	protected function describeBlockOnlyState(RuntimeDataDescriber $w) : void{
		$w->bool($this->powered);
	}

	public function getLightLevel() : int{
		return $this->powered ? 15 : 0;
	}

	public function isLit() : bool{
		return $this->powered;
	}

	/** @return $this */
	public function setLit(bool $lit = true) : self{
		$this->powered = $lit;
		return $this;
	}

	public function onRedstoneUpdate(RedstoneEngine $engine) : void{
		$powered = $engine->getReceivedPower($this->position) > 0;
		if($powered && !$this->isLit()){
			$engine->getWorld()->setBlock($this->position, $this->setLit(true));
		}elseif(!$powered && $this->isLit()){
			$engine->schedule($this->position, 2 * RedstoneEngine::REDSTONE_TICK); //lamps turn off with a delay
		}
	}

	public function onRedstoneScheduledUpdate(RedstoneEngine $engine) : void{
		if($this->isLit() && $engine->getReceivedPower($this->position) === 0){
			$engine->getWorld()->setBlock($this->position, $this->setLit(false));
		}
	}
}
