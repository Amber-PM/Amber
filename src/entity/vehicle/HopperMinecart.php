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

namespace pocketmine\entity\vehicle;

use pocketmine\block\tile\Container;
use pocketmine\block\VanillaBlocks;
use pocketmine\data\bedrock\item\SavedItemStackData;
use pocketmine\entity\Location;
use pocketmine\entity\object\ItemEntity;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\nbt\NBT;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\UnexpectedTagTypeException;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use function floor;
use function min;

class HopperMinecart extends Minecart implements ContainerVehicle{

	public const TAG_ITEMS = "Items";

	protected VehicleInventory $inventory;

	public function __construct(Location $location, ?CompoundTag $nbt = null){
		$this->inventory = new VehicleInventory($this, 5);
		parent::__construct($location, MinecartType::HOPPER, $nbt);
		$this->maxPassengers = 0;
	}

	public static function getNetworkTypeId() : string{
		return EntityIds::HOPPER_MINECART;
	}

	public function getInventory() : VehicleInventory{
		return $this->inventory;
	}

	/**
	 * @return Item[]
	 */
	public function getDrops() : array{
		return [
			VanillaItems::MINECART(),
			VanillaBlocks::HOPPER()->asItem()
		];
	}

	protected function initEntity(CompoundTag $nbt) : void{
		parent::initEntity($nbt);
		try{
			$inventoryTag = $nbt->getListTag(self::TAG_ITEMS, CompoundTag::class);
		}catch(UnexpectedTagTypeException){
			$inventoryTag = null;
		}
		if($inventoryTag !== null){
			$listeners = $this->inventory->getListeners()->toArray();
			$this->inventory->getListeners()->remove(...$listeners);
			$newContents = [];
			foreach($inventoryTag as $itemNBT){
				$slotId = $itemNBT->getByte(SavedItemStackData::TAG_SLOT);
				$newContents[$slotId] = Item::safeNbtDeserialize($itemNBT, "vehicle slot $slotId");
			}
			$this->inventory->setContents($newContents);
			$this->inventory->getListeners()->add(...$listeners);
		}
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT();
		$items = [];
		foreach($this->inventory->getContents() as $slot => $item){
			$items[] = $item->nbtSerialize($slot);
		}
		$nbt->setTag(self::TAG_ITEMS, new ListTag($items, NBT::TAG_Compound));
		return $nbt;
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		$hasUpdate = parent::entityBaseTick($tickDiff);

		$world = $this->getWorld();
		if($this->location->isValid() && $world->isLoaded()){
			// 1. Collect colliding ItemEntity
			$bb = $this->boundingBox->expandedCopy(0.2, 0.2, 0.2);
			foreach($world->getNearbyEntities($bb) as $entity){
				if($entity instanceof ItemEntity && !$entity->isFlaggedForDespawn() && !$entity->isClosed()){
					$item = $entity->getItem();
					$count = min($item->getCount(), $this->inventory->getAddableItemQuantity($item));
					if($count > 0){
						$this->inventory->addItem((clone $item)->setCount($count));
						if($count >= $item->getCount()){
							$entity->flagForDespawn();
						}else{
							$entity->setStackSize($item->getCount() - $count);
						}
					}
				}
			}

			// 2. Pull from container directly above
			$pos = $this->location->floor();
			$aboveY = (int) floor($this->boundingBox->maxY + 0.1);
			$tile = $world->getTileAt($pos->x, $aboveY, $pos->z);
			if(!($tile instanceof Container) || $tile->isClosed()){
				$tile = $world->getTileAt($pos->x, $pos->y + 1, $pos->z);
			}
			if($tile instanceof Container && !$tile->isClosed()){
				$sourceInv = $tile->getInventory();
				foreach($sourceInv->getContents() as $slot => $item){
					if(!$item->isNull()){
						$one = (clone $item)->setCount(1);
						if($this->inventory->canAddItem($one)){
							$this->inventory->addItem($one);
							$sourceInv->setItem($slot, $item->setCount($item->getCount() - 1));
							break;
						}
					}
				}
			}
		}

		return $hasUpdate;
	}

	public function onDestroy(bool $dropItem = true) : void{
		parent::onDestroy($dropItem);
		if($this->location->isValid()){
			foreach($this->inventory->getContents() as $item){
				$this->getWorld()->dropItem($this->location, $item);
			}
		}
		$this->inventory->clearAll();
	}

	protected function onDispose() : void{
		parent::onDispose();
	}
}
