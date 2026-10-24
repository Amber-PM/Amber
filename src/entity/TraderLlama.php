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

use pocketmine\block\utils\DyeColor;
use pocketmine\block\VanillaBlocks;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

class TraderLlama extends Llama{

	public static function getNetworkTypeId() : string{
		return EntityIds::TRADER_LLAMA;
	}

	public function getName() : string{
		return "Trader Llama";
	}

	protected function initEntity(CompoundTag $nbt) : void{
		parent::initEntity($nbt);

		// Naturally tamed
		$this->tamed = true;

		// Default to blue trader carpet if none set
		if($this->carpetItem === null){
			$this->carpetColor = DyeColor::BLUE;
			$this->carpetItem = VanillaBlocks::CARPET()->setColor(DyeColor::BLUE)->asItem();
		}
	}
}
