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

use pocketmine\data\bedrock\item\SavedItemStackData;
use pocketmine\entity\Location;
use pocketmine\item\Item;
use pocketmine\item\StringToItemParser;
use pocketmine\nbt\NBT;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\UnexpectedTagTypeException;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

class ChestBoat extends Boat implements ContainerVehicle{

	public const TAG_ITEMS = "Items";

	protected VehicleInventory $inventory;

	public function __construct(
		Location $location,
		BoatType $boatType = BoatType::OAK,
		?CompoundTag $nbt = null
	){
		$this->inventory = new VehicleInventory($this, 27);
		parent::__construct($location, $boatType, $nbt);
		// In vanilla, the chest occupies the rear passenger seat
		$this->maxPassengers = 1;
	}

	public static function getNetworkTypeId() : string{
		return EntityIds::CHEST_BOAT;
	}

	public function getInventory() : VehicleInventory{
		return $this->inventory;
	}

	public function getDropItem() : ?Item{
		$name = $this->boatType->isRaft() ? "bamboo_chest_raft" : ($this->boatType->value . "_chest_boat");
		return StringToItemParser::getInstance()->parse($name);
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
