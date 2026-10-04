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

use pocketmine\entity\Entity;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Location;
use pocketmine\item\BoatType;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

class Boat extends Entity{
	public static function getNetworkTypeId() : string{ return EntityIds::BOAT; }

	protected BoatType $boatType;

	public function __construct(Location $location, BoatType|CompoundTag|null $typeOrNbt = null, ?CompoundTag $nbt = null){
		if($typeOrNbt instanceof BoatType){
			$this->boatType = $typeOrNbt;
		}else{
			$this->boatType = BoatType::OAK;
			if($typeOrNbt instanceof CompoundTag){
				$nbt = $typeOrNbt;
			}
		}
		parent::__construct($location, $nbt);
	}

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo($this->boatType->isRaft() ? 0.45 : 0.6, 1.4);
	}

	protected function getInitialDragMultiplier() : float{ return 0.05; }

	protected function getInitialGravity() : float{ return 0.04; }

	public function getBoatType() : BoatType{
		return $this->boatType;
	}

	protected function initEntity(CompoundTag $nbt) : void{
		parent::initEntity($nbt);
		if($nbt->getTag("Type") !== null){
			$typeName = $nbt->getString("Type", "oak");
			foreach(BoatType::cases() as $case){
				if(strtolower($case->name) === strtolower($typeName)){
					$this->boatType = $case;
					break;
				}
			}
		}
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT();
		$nbt->setString("Type", strtolower($this->boatType->name));
		return $nbt;
	}
}
