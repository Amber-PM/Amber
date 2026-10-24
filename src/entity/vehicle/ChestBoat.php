<?php

/*
 *
 *     _             _               
 *    / \\   _ __ ___ | |__   ___ _ __ 
 *   / _ \\ | '_ ` _ \\| '_ \\ / _ \\ '__|
 *  / ___ \\| | | | | | |_) |  __/ |   
 * /_/   \\_\\_| |_| |_|_.__/ \\___|_|   
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

use pocketmine\entity\Location;
use pocketmine\inventory\Inventory;
use pocketmine\inventory\SimpleInventory;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

class ChestBoat extends Boat{

	protected SimpleInventory $inventory;

	public function __construct(
		Location $location,
		BoatType $boatType = BoatType::OAK,
		?CompoundTag $nbt = null
	){
		$this->inventory = new SimpleInventory(27);
		parent::__construct($location, $boatType, $nbt);
		// In vanilla, the chest occupies the rear passenger seat
		$this->maxPassengers = 1;
	}

	public static function getNetworkTypeId() : string{
		return EntityIds::CHEST_BOAT;
	}

	public function getInventory() : Inventory{
		return $this->inventory;
	}

	protected function onDispose() : void{
		foreach($this->inventory->getContents() as $item){
			$this->getWorld()->dropItem($this->location, $item);
		}
		$this->inventory->clearAll();
		parent::onDispose();
	}
}
