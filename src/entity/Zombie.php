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
use pocketmine\nbt\tag\IntTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use function mt_rand;

class Zombie extends Living{

	public const TAG_DROWNED_CONVERSION_TIME = "DrownedConversionTime";
	public const SUBMERGED_TICKS_BEFORE_CONVERSION = 600; // 30 seconds
	public const CONVERSION_DURATION_TICKS = 300; // 15 seconds

	protected int $submergedTicks = 0;
	protected int $conversionTicks = -1;

	public static function getNetworkTypeId() : string{ return EntityIds::ZOMBIE; }

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(1.9, 0.6); //TODO: eye height ??
	}

	public function getName() : string{
		return "Zombie";
	}

	public function getDefaultMaxHealth() : int{
		return 20;
	}

	protected function initEntity(CompoundTag $nbt) : void{
		$this->setMaxHealth(20);
		parent::initEntity($nbt);
		if($nbt->getTag("Health") === null){
			$this->setHealth(20.0);
		}

		$this->conversionTicks = $nbt->getInt(self::TAG_DROWNED_CONVERSION_TIME, -1);
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT();
		$nbt->setInt(self::TAG_DROWNED_CONVERSION_TIME, $this->conversionTicks);
		return $nbt;
	}

	public function isConvertingToDrowned() : bool{
		return $this->conversionTicks >= 0;
	}

	public function getConversionTicks() : int{
		return $this->conversionTicks;
	}

	public function setConversionTicks(int $ticks) : void{
		$this->conversionTicks = $ticks;
	}

	public function getSubmergedTicks() : int{
		return $this->submergedTicks;
	}

	public function setSubmergedTicks(int $ticks) : void{
		$this->submergedTicks = $ticks;
	}

	public function convertToDrowned() : Drowned{
		$drowned = new Drowned($this->location);
		$drowned->setHealth($this->getHealth());
		$this->flagForDespawn();
		$drowned->spawnToAll();
		return $drowned;
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

		// Drowning conversion to Drowned
		if($this->isUnderwater()){
			if($this->conversionTicks >= 0){
				$this->conversionTicks -= $tickDiff;
				$hasUpdate = true;
				if($this->conversionTicks <= 0){
					$this->convertToDrowned();
					return false;
				}
			}else{
				$this->submergedTicks += $tickDiff;
				$hasUpdate = true;
				if($this->submergedTicks >= self::SUBMERGED_TICKS_BEFORE_CONVERSION){
					$this->conversionTicks = self::CONVERSION_DURATION_TICKS;
				}
			}
		}else{
			$this->submergedTicks = 0;
			$this->conversionTicks = -1;
		}

		return $hasUpdate;
	}

	public function getDrops() : array{
		$drops = [
			VanillaItems::ROTTEN_FLESH()->setCount(mt_rand(0, 2))
		];

		if(mt_rand(0, 199) < 5){
			switch(mt_rand(0, 2)){
				case 0:
					$drops[] = VanillaItems::IRON_INGOT();
					break;
				case 1:
					$drops[] = VanillaItems::CARROT();
					break;
				case 2:
					$drops[] = VanillaItems::POTATO();
					break;
			}
		}

		return $drops;
	}

	public function getXpDropAmount() : int{
		//TODO: check for equipment and whether it's a baby
		return 5;
	}

	public function getPickedItem() : ?Item{
		return VanillaItems::ZOMBIE_SPAWN_EGG();
	}
}
