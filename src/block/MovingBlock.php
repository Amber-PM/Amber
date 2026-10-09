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

use pocketmine\block\tile\MovingBlock as MovingBlockTile;
use pocketmine\block\utils\DelayedRedstoneReceiver;
use pocketmine\item\Item;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\redstone\RedstoneEngine;

final class MovingBlock extends Transparent implements DelayedRedstoneReceiver{
	private ?Block $carriedBlock = null;
	private ?Vector3 $offset = null;
	private ?MovingBlockTile $movingTile = null;
	private array $localCollisionBoxes = [];

	public function readStateFromWorld() : Block{
		$tile = $this->position->getWorld()->getTile($this->position);
		if($tile instanceof MovingBlockTile){
			$this->movingTile = $tile;
			$this->localCollisionBoxes = $tile->getLocalCollisionBoxes();
			$this->carriedBlock = $tile->getCarriedBlock();
			$this->offset = $tile->getMovementOffset();
		}
		return $this;
	}

	public function getModelPositionOffset() : ?Vector3{
		return $this->offset;
	}

	protected function recalculateCollisionBoxes() : array{
		$boxes = [];
		foreach($this->localCollisionBoxes as $box){
			$boxes[] = clone $box;
		}
		return $boxes;
	}

	public function isSolid() : bool{
		return false;
	}

	public function canBePlaced() : bool{
		return false;
	}

	public function getDrops(Item $item) : array{
		return $this->carriedBlock?->getDrops($item) ?? [];
	}

	public function getDropsForCompatibleTool(Item $item) : array{
		return $this->carriedBlock?->getDropsForCompatibleTool($item) ?? [];
	}

	public function materialize() : ?Block{
		$world = $this->position->getWorld();
		if(!$world->isChunkLoaded($this->position->getFloorX() >> 4, $this->position->getFloorZ() >> 4)){
			return null;
		}
		if($this->movingTile?->finish()){
			return $world->getBlock($this->position);
		}
		$current = $world->getBlock($this->position);
		return !$current instanceof self && $current->getStateId() === $this->carriedBlock?->getStateId() ? $current : null;
	}

	public function onBreak(Item $item, ?Player $player = null, array &$returnedItems = []) : bool{
		return $this->materialize()?->onBreak($item, $player, $returnedItems) ?? false;
	}

	public function onScheduledUpdate() : void{
		$world = $this->position->getWorld();
		if(($engine = $world->getRedstoneEngine()) !== null){
			$engine->schedule($this->position, 1);
		}else{
			$this->recover();
		}
	}

	public function onRedstoneUpdate(RedstoneEngine $engine) : void{
	}

	public function onRedstoneScheduledUpdate(RedstoneEngine $engine) : void{
		$this->recover();
	}

	private function recover() : void{
		$world = $this->position->getWorld();
		$tile = $world->getTile($this->position);
		if(!$tile instanceof MovingBlockTile){
			return;
		}
		if($tile->isOwnerChunkLoaded()){
			if(($owner = $tile->getOwner()) === null){
				if($tile->finish() && ($block = $world->getBlock($this->position)) instanceof Chest){
					$block->onPostPlace();
				}
				return;
			}
			if(($engine = $world->getRedstoneEngine()) !== null){
				$engine->schedule($owner->getPosition(), 1);
			}else{
				$world->scheduleDelayedBlockUpdate($owner->getPosition(), 1);
			}
		}
		if(($engine = $world->getRedstoneEngine()) !== null){
			$engine->schedule($this->position, 20);
		}else{
			$world->scheduleDelayedBlockUpdate($this->position, 20);
		}
	}
}
