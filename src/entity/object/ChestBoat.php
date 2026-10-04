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

namespace pocketmine\entity\object;

use pocketmine\data\bedrock\item\SavedItemStackData;
use pocketmine\inventory\InventoryHolder;
use pocketmine\item\BoatType;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\nbt\NBT;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\player\Player;

class ChestBoat extends Boat implements InventoryHolder{
	public const TAG_ITEMS = "Items";

	protected ChestBoatInventory $inventory;

	public static function getNetworkTypeId() : string{
		return "minecraft:chest_boat";
	}

	protected function initBoatProperties() : void{
		parent::initBoatProperties();
		$this->inventory = new ChestBoatInventory($this);
	}

	public function getMaxRiders() : int{
		return 1;
	}

	public function getInventory() : ChestBoatInventory{
		return $this->inventory;
	}

	public function getRealInventory() : ChestBoatInventory{
		return $this->inventory;
	}

	public function getDropItem() : Item{
		return match($this->boatType){
			BoatType::OAK => VanillaItems::OAK_CHEST_BOAT(),
			BoatType::SPRUCE => VanillaItems::SPRUCE_CHEST_BOAT(),
			BoatType::BIRCH => VanillaItems::BIRCH_CHEST_BOAT(),
			BoatType::JUNGLE => VanillaItems::JUNGLE_CHEST_BOAT(),
			BoatType::ACACIA => VanillaItems::ACACIA_CHEST_BOAT(),
			BoatType::DARK_OAK => VanillaItems::DARK_OAK_CHEST_BOAT(),
			BoatType::MANGROVE => VanillaItems::MANGROVE_CHEST_BOAT(),
			BoatType::BAMBOO => VanillaItems::BAMBOO_CHEST_RAFT(),
			BoatType::CHERRY => VanillaItems::CHERRY_CHEST_BOAT(),
			BoatType::PALE_OAK => VanillaItems::PALE_OAK_CHEST_BOAT(),
		};
	}

	protected function destroyBoat() : void{
		$world = $this->getWorld();
		if($world->isLoaded()){
			foreach($this->inventory->getContents() as $item){
				$world->dropItem($this->location, $item);
			}
			$this->inventory->clearAll();
		}

		parent::destroyBoat();
	}

	public function onInteract(Player $player, Vector3 $clickPos) : bool{
		if($player->isSneaking()){
			if($this->isRider($player)){
				$this->removeRider($player);
				return true;
			}
			$player->setCurrentWindow($this->inventory);
			return true;
		}

		if(!$this->isRider($player)){
			if(!$this->isFull()){
				$this->addRider($player);
				return true;
			}
			$player->setCurrentWindow($this->inventory);
			return true;
		}

		return false;
	}

	protected function readSaveData(CompoundTag $nbt) : void{
		parent::readSaveData($nbt);

		$itemsTag = $nbt->getListTag(self::TAG_ITEMS);
		if($itemsTag !== null){
			$newContents = [];
			foreach($itemsTag as $itemNBT){
				if($itemNBT instanceof CompoundTag){
					$slot = $itemNBT->getByte(SavedItemStackData::TAG_SLOT, -1);
					if($slot >= 0 && $slot < $this->inventory->getSize()){
						$newContents[$slot] = Item::nbtDeserialize($itemNBT);
					}
				}
			}
			$this->inventory->setContents($newContents);
		}
	}

	protected function writeSaveData(CompoundTag $nbt) : void{
		parent::writeSaveData($nbt);

		$items = [];
		foreach($this->inventory->getContents() as $slot => $item){
			$items[] = $item->nbtSerialize($slot);
		}
		$nbt->setTag(self::TAG_ITEMS, new ListTag($items, NBT::TAG_Compound));
	}
}
