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

use pocketmine\block\tile\Dispenser as TileDispenser;
use pocketmine\block\utils\PoweredByRedstone;
use pocketmine\block\utils\PoweredByRedstoneTrait;
use pocketmine\block\utils\RedstoneReceiver;
use pocketmine\data\runtime\RuntimeDataDescriber;
use pocketmine\item\Item;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\BlockTransaction;
use pocketmine\world\redstone\RedstoneEngine;

class Dispenser extends Opaque implements PoweredByRedstone, RedstoneReceiver{
	use PoweredByRedstoneTrait;

	private int $facing = Facing::NORTH;

	protected function describeBlockOnlyState(RuntimeDataDescriber $w) : void{
		$w->facing($this->facing);
		$w->bool($this->powered);
	}

	public function getFacing() : int{
		return $this->facing;
	}

	/** @return $this */
	public function setFacing(int $facing) : self{
		$this->facing = $facing;
		return $this;
	}

	public function place(BlockTransaction $tx, Item $item, Block $blockReplace, Block $blockClicked, int $face, Vector3 $clickVector, ?Player $player = null) : bool{
		if($player !== null){
			$pitch = $player->getLocation()->pitch;
			if($pitch > 45.0){
				$this->facing = Facing::UP;
			}elseif($pitch < -45.0){
				$this->facing = Facing::DOWN;
			}else{
				$this->facing = Facing::opposite($player->getHorizontalFacing());
			}
		}

		return parent::place($tx, $item, $blockReplace, $blockClicked, $face, $clickVector, $player);
	}

	public function onInteract(Item $item, int $face, Vector3 $clickVector, ?Player $player = null, array &$returnedItems = []) : bool{
		if($player !== null){
			$tile = $this->position->getWorld()->getTile($this->position);
			if($tile instanceof TileDispenser){
				$player->setCurrentWindow($tile->getInventory());
			}
			return true;
		}
		return false;
	}

	public function onRedstoneUpdate(RedstoneEngine $engine) : void{
		$isPowered = $engine->getReceivedPower($this->position) > 0;
		if($isPowered !== $this->powered){
			$this->setPowered($isPowered);
			$engine->getWorld()->setBlock($this->position, $this);

			if($isPowered){
				$this->dispense();
			}
		}
	}

	public function dispense() : void{
		$tile = $this->position->getWorld()->getTile($this->position);
		if(!$tile instanceof TileDispenser){
			return;
		}

		// Dispense logic will be integrated in Task 2 & 3
	}
}
