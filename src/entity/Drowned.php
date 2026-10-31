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

use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use function mt_rand;

class Drowned extends Zombie{

	public static function getNetworkTypeId() : string{
		return EntityIds::DROWNED;
	}

	public function getName() : string{
		return "Drowned";
	}

	public function canBreatheInWater() : bool{
		return true; // Drowned have infinite air underwater!
	}

	protected function initEntity(CompoundTag $nbt) : void{
		$this->setMaxHealth(20);
		parent::initEntity($nbt);
		if($nbt->getTag("Health") === null){
			$this->setHealth(20.0);
		}
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		$hasUpdate = parent::entityBaseTick($tickDiff);

		if(!$this->isAlive()){
			return $hasUpdate;
		}

		$world = $this->getWorld();

		// Sunlight burning only when NOT in water
		$time = $world->getTime() % 24000;
		if($time >= 0 && $time < 12000 && !$this->isUnderwater()){
			$highestBlockAtPos = $world->getHighestBlockAt((int) $this->location->x, (int) $this->location->z);
			if($highestBlockAtPos === null || $highestBlockAtPos <= $this->location->y){
				$this->setOnFire(8);
				$hasUpdate = true;
			}
		}

		return $hasUpdate;
	}

	public function getDrops() : array{
		$drops = [
			VanillaItems::ROTTEN_FLESH()->setCount(mt_rand(0, 2))
		];

		// 3% chance for Nautilus Shell
		if(mt_rand(0, 99) < 3){
			$drops[] = VanillaItems::NAUTILUS_SHELL();
		}

		// 11% chance for Copper Ingot
		if(mt_rand(0, 99) < 11){
			$drops[] = VanillaItems::COPPER_INGOT();
		}

		return $drops;
	}
}
