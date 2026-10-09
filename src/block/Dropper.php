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

use pocketmine\block\dispenser\BlockSource;
use pocketmine\block\dispenser\DispenseBehaviorRegistry;
use pocketmine\block\tile\Container;
use pocketmine\block\tile\Dropper as TileDropper;
use pocketmine\block\utils\DelayedRedstoneReceiver;
use pocketmine\block\utils\PoweredByRedstone;
use pocketmine\block\utils\PoweredByRedstoneTrait;
use pocketmine\data\runtime\RuntimeDataDescriber;
use pocketmine\item\Item;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\BlockTransaction;
use pocketmine\world\hopper\ContainerTransfer;
use pocketmine\world\hopper\ContainerTransferPolicy;
use pocketmine\world\redstone\RedstoneEngine;
use pocketmine\world\sound\ClickFailSound;
use pocketmine\world\sound\ClickSound;
use function array_rand;
use function count;

class Dropper extends Opaque implements PoweredByRedstone, DelayedRedstoneReceiver{
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
		Facing::validate($facing);
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
			if($tile instanceof TileDropper && $tile->canOpenWith($item->getCustomName())){
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
			$engine->updateReceiverState($this);

			if($isPowered){
				$engine->schedule($this->position, 4);
			}
		}
	}

	public function onRedstoneScheduledUpdate(RedstoneEngine $engine) : void{
		$this->drop();
	}

	public function drop() : void{
		$world = $this->position->getWorld();
		$tile = $world->getTile($this->position);
		if(!$tile instanceof TileDropper){
			return;
		}

		$inventory = $tile->getInventory();
		$occupiedSlots = [];
		foreach($inventory->getContents() as $slot => $item){
			if(!$item->isNull()){
				$occupiedSlots[] = $slot;
			}
		}

		if(count($occupiedSlots) === 0){
			$world->addSound($this->position, new ClickFailSound());
			return;
		}

		$randomSlot = $occupiedSlots[array_rand($occupiedSlots)];
		$sourceItem = $inventory->getItem($randomSlot);
		if($sourceItem->isNull()){
			$world->addSound($this->position, new ClickFailSound());
			return;
		}

		$targetPos = $this->position->getSide($this->facing);
		if(!$world->isInWorld($targetPos->getFloorX(), $targetPos->getFloorY(), $targetPos->getFloorZ()) || !$world->isChunkLoaded($targetPos->getFloorX() >> 4, $targetPos->getFloorZ() >> 4)){
			$world->addSound($this->position, new ClickFailSound());
			return;
		}
		$targetTile = $world->getTile($targetPos);

		if($targetTile instanceof Container){
			$transfer = new ContainerTransfer(new ContainerTransferPolicy());
			if($transfer->insertOne($targetTile, $sourceItem, Facing::opposite($this->facing))){
				$sourceItem->pop();
				$inventory->setItem($randomSlot, $sourceItem);
				$world->addSound($this->position, new ClickSound());
			}else{
				$world->addSound($this->position, new ClickFailSound());
			}
			return;
		}

		$source = new BlockSource($world, $this->position, $this->facing, $tile);
		$leftover = DispenseBehaviorRegistry::getInstance()->getDefault()->dispense($source, $sourceItem);
		$inventory->setItem($randomSlot, $leftover);
	}
}
