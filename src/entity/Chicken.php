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
use pocketmine\item\ItemTypeIds;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\world\sound\PopSound;
use function max;
use function mt_rand;

class Chicken extends Animal{

	public const TAG_EGG_LAY_TIME = "EggLayTime";

	public static function getNetworkTypeId() : string{
		return EntityIds::CHICKEN;
	}

	protected int $eggLayTime = 6000;

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(0.7, 0.4);
	}

	public function getName() : string{
		return "Chicken";
	}

	protected function addAttributes() : void{
		parent::addAttributes();
		$this->setMaxHealth(4);
		$this->setHealth(4.0);
	}

	protected function initEntity(CompoundTag $nbt) : void{
		parent::initEntity($nbt);
		if(($eggTag = $nbt->getTag(self::TAG_EGG_LAY_TIME)) instanceof IntTag){
			$this->eggLayTime = $eggTag->getValue();
		}else{
			$this->eggLayTime = mt_rand(6000, 12000);
		}
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT();
		$nbt->setInt(self::TAG_EGG_LAY_TIME, $this->eggLayTime);
		return $nbt;
	}

	public function calculateFallDamage(float $fallDistance) : float{
		return 0.0;
	}

	public function getEggLayTime() : int{
		return $this->eggLayTime;
	}

	public function setEggLayTime(int $ticks) : void{
		$this->eggLayTime = $ticks;
	}

	public function isBreedingItem(Item $item) : bool{
		$id = $item->getTypeId();
		return $id === ItemTypeIds::WHEAT_SEEDS
			|| $id === ItemTypeIds::BEETROOT_SEEDS
			|| $id === ItemTypeIds::MELON_SEEDS
			|| $id === ItemTypeIds::PUMPKIN_SEEDS;
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		$hasUpdate = parent::entityBaseTick($tickDiff);

		if(!$this->isAlive()){
			return $hasUpdate;
		}

		// Chicken slow fall flapping
		if(!$this->onGround && $this->motion->y < -0.05){
			$this->motion = new Vector3($this->motion->x, -0.05, $this->motion->z);
		}

		// Egg laying (adult only)
		if(!$this->isBaby()){
			$this->eggLayTime -= $tickDiff;
			if($this->eggLayTime <= 0){
				$this->getWorld()->dropItem($this->location, VanillaItems::EGG());
				$this->getWorld()->addSound($this->location, new PopSound());
				$this->eggLayTime = mt_rand(6000, 12000);
			}
		}

		return $hasUpdate;
	}

	public function getDrops() : array{
		$drops = [];
		$meat = $this->isOnFire() ? VanillaItems::COOKED_CHICKEN() : VanillaItems::RAW_CHICKEN();
		$drops[] = $meat;
		$featherCount = mt_rand(0, 2);
		if($featherCount > 0){
			$drops[] = VanillaItems::FEATHER()->setCount($featherCount);
		}
		return $drops;
	}
}
