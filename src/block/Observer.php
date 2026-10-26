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
use pocketmine\block\utils\PoweredByRedstone;
use pocketmine\block\utils\PoweredByRedstoneTrait;
use pocketmine\block\utils\RedstoneSource;
use pocketmine\data\runtime\RuntimeDataDescriber;
use pocketmine\item\Item;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\BlockTransaction;
use pocketmine\world\redstone\RedstoneEngine;

class Observer extends Opaque implements PoweredByRedstone, RedstoneSource, DelayedRedstoneReceiver{
	use PoweredByRedstoneTrait;

	private int $facing = Facing::SOUTH;

	protected function describeBlockOnlyState(RuntimeDataDescriber $w) : void{
		$w->facing($this->facing);
		$w->bool($this->powered);
	}

	public function getFacing() : int{
		return $this->facing;
	}

	/** @return $this */
	public function setFacing(int $facing) : self{
		Facing::validate($facing);
		$this->facing = $facing;
		return $this;
	}

	public function place(BlockTransaction $tx, Item $item, Block $blockReplace, Block $blockClicked, int $face, Vector3 $clickVector, ?Player $player = null) : bool{
		if($player !== null){
			$pitch = $player->getLocation()->pitch;
			if($pitch > 45.0){
				$this->facing = Facing::DOWN;
			}elseif($pitch < -45.0){
				$this->facing = Facing::UP;
			}else{
				$this->facing = $player->getHorizontalFacing();
			}
		}

		return parent::place($tx, $item, $blockReplace, $blockClicked, $face, $clickVector, $player);
	}

	public function getRedstoneOutput(int $face, bool $strongOnly, RedstoneEngine $engine) : int{
		return ($this->powered && $face === Facing::opposite($this->facing)) ? 15 : 0;
	}

	public function onNearbyBlockChange() : void{
		if(!$this->powered){
			$this->position->getWorld()->getRedstoneEngine()?->schedule($this->position, RedstoneEngine::REDSTONE_TICK);
		}
	}

	public function onRedstoneUpdate(RedstoneEngine $engine) : void{
		// Observers are not powered by external redstone; they react to nearby block changes
	}

	public function onRedstoneScheduledUpdate(RedstoneEngine $engine) : void{
		if(!$this->powered){
			$this->powered = true;
			$engine->getWorld()->setBlock($this->position, $this);
			$engine->requestAround($this->position);
			$engine->schedule($this->position, RedstoneEngine::REDSTONE_TICK);
		}else{
			$this->powered = false;
			$engine->getWorld()->setBlock($this->position, $this);
			$engine->requestAround($this->position);
		}
	}
}
