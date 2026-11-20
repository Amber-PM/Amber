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

namespace pocketmine\entity;

use pocketmine\item\VanillaItems;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

class Cod extends WaterAnimal{

	public static function getNetworkTypeId() : string{
		return EntityIds::COD;
	}

	public function getName() : string{
		return "Cod";
	}

	public function getDefaultMaxHealth() : int{
		return 3;
	}

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(0.3, 0.5);
	}

	protected function initEntity(CompoundTag $nbt) : void{
		$this->setMaxHealth(3);
		parent::initEntity($nbt);
		if($nbt->getTag("Health") === null){
			$this->setHealth(3.0);
		}
	}

	public function getDrops() : array{
		return [VanillaItems::RAW_FISH()];
	}
}
