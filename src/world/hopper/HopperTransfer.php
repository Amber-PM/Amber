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

namespace pocketmine\world\hopper;

use pocketmine\block\Hopper;
use pocketmine\block\tile\Container;
use pocketmine\block\tile\Hopper as HopperTile;
use pocketmine\entity\object\ItemEntity;
use pocketmine\inventory\Inventory;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Facing;
use pocketmine\world\World;
use WeakMap;
use function min;

final class HopperTransfer{
	private ContainerTransfer $containers;
	private WeakMap $itemScans;

	public function __construct(private World $world, private ContainerTransferPolicy $policy){
		$this->containers = new ContainerTransfer($policy);
		$this->itemScans = new WeakMap();
	}

	public function transfer(HopperTile $tile, Hopper $block, int $currentTick) : HopperTransferResult{
		$pos = $tile->getPosition();
		$x = $pos->getFloorX();
		$y = $pos->getFloorY();
		$z = $pos->getFloorZ();
		$inventory = $tile->getInventory();
		$moved = $this->push($inventory, $x, $y, $z, $block->getFacing(), $currentTick);
		$pull = $this->pullFromAbove($inventory, $x, $y, $z);
		$moved += $pull->moved;
		if(!$pull->sourceFound){
			$moved += $this->collectItems($inventory, $x, $y, $z);
		}
		return new HopperTransferResult($moved, $pull->sourceFound);
	}

	public function forgetInventory(Inventory $inventory) : void{
		unset($this->itemScans[$inventory]);
	}

	public function clear() : void{
		$this->itemScans = new WeakMap();
	}

	private function push(Inventory $hopper, int $x, int $y, int $z, int $facing, int $currentTick) : int{
		[$dx, $dy, $dz] = Facing::OFFSET[$facing];
		if(!$this->world->isInWorld($x + $dx, $y + $dy, $z + $dz) || !$this->world->isChunkLoaded(($x + $dx) >> 4, ($z + $dz) >> 4)){
			return 0;
		}
		$target = $this->world->getTileAt($x + $dx, $y + $dy, $z + $dz);
		if(!$target instanceof Container || !$this->policy->isSupported($target)){
			return 0;
		}
		for($slot = 0, $size = $hopper->getSize(); $slot < $size; ++$slot){
			$item = $hopper->getItem($slot);
			if($item->isNull()){
				continue;
			}
			$wasEmpty = $target->getInventory()->getContents() === [];
			if($this->containers->insertOne($target, $item, Facing::opposite($facing))){
				$hopper->setItem($slot, $item->setCount($item->getCount() - 1));
				if($wasEmpty && $target instanceof HopperTile){
					$target->startTransferCooldown($currentTick, HopperTicker::TRANSFER_INTERVAL);
				}
				return 1;
			}
		}
		return 0;
	}

	private function pullFromAbove(Inventory $hopper, int $x, int $y, int $z) : HopperTransferResult{
		if(!$this->world->isInWorld($x, $y + 1, $z) || !$this->world->isChunkLoaded($x >> 4, $z >> 4)){
			return new HopperTransferResult(0, false);
		}
		$source = $this->world->getTileAt($x, $y + 1, $z);
		if(!$source instanceof Container || $source->isClosed() || !$this->policy->isSupported($source)){
			return new HopperTransferResult(0, $source instanceof Container);
		}
		if($source instanceof HopperTile){
			$above = $this->world->getBlockAt($x, $y + 1, $z);
			if($above instanceof Hopper && $above->getFacing() === Facing::DOWN){
				return new HopperTransferResult(0, true);
			}
		}
		$inventory = $source->getInventory();
		$slots = $this->policy->getExtractableSlots($source);
		foreach($slots as $slot){
			$item = $inventory->getItem($slot);
			if($item->isNull() || !$this->policy->canExtract($source, $slot, $item)){
				continue;
			}
			$one = (clone $item)->setCount(1);
			if($hopper->canAddItem($one)){
				$hopper->addItem($one);
				$inventory->setItem($slot, $item->setCount($item->getCount() - 1));
				return new HopperTransferResult(1, true);
			}
		}
		return new HopperTransferResult(0, true);
	}

	private function collectItems(Inventory $hopper, int $x, int $y, int $z) : int{
		if(!$this->world->isInWorld($x, $y + 1, $z) || !$this->world->isChunkLoaded($x >> 4, $z >> 4)){
			return 0;
		}
		$above = $this->world->getBlockAt($x, $y + 1, $z);
		if($above->isSolid() && $above->isFullCube()){
			return 0;
		}
		$area = new AxisAlignedBB($x, $y + 0.625, $z, $x + 1, $y + 2, $z + 1);
		$scan = $this->itemScans[$hopper] ??= $this->world->iterateEntityCandidates($area);
		for($scanned = 0; $scanned < 64 && $scan->valid(); ++$scanned){
			$entity = $scan->current();
			$scan->next();
			if(!$entity instanceof ItemEntity || $entity->isFlaggedForDespawn() || $entity->isClosed() || $entity->getWorld() !== $this->world || !$entity->getBoundingBox()->intersectsWith($area)){
				continue;
			}
			$item = $entity->getItem();
			$count = min($item->getCount(), $hopper->getAddableItemQuantity($item));
			if($count <= 0){
				continue;
			}
			$hopper->addItem((clone $item)->setCount($count));
			if($count >= $item->getCount()){
				$entity->flagForDespawn();
			}else{
				$entity->setStackSize($item->getCount() - $count);
			}
			unset($this->itemScans[$hopper]);
			return $count;
		}
		if(!$scan->valid()){
			unset($this->itemScans[$hopper]);
		}
		return 0;
	}

}
