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
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\player\Player;
use pocketmine\world\sound\BucketFillWaterSound;
use function mt_rand;

class Cow extends Animal{

	public static function getNetworkTypeId() : string{
		return EntityIds::COW;
	}

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(1.3, 0.9);
	}

	public function getName() : string{
		return "Cow";
	}

	protected function addAttributes() : void{
		parent::addAttributes();
		$this->setMaxHealth(10);
		$this->setHealth(10.0);
	}

	public function isBreedingItem(Item $item) : bool{
		return $item->getTypeId() === ItemTypeIds::WHEAT;
	}

	public function onInteract(Player $player, Vector3 $clickVector) : bool{
		$item = $player->getInventory()->getItemInHand();

		// Milking interaction
		if($item->getTypeId() === ItemTypeIds::BUCKET && !$this->isBaby()){
			$milk = VanillaItems::MILK_BUCKET();
			if($player->hasFiniteResources()){
				if($item->getCount() === 1){
					$player->getInventory()->setItemInHand($milk);
				}else{
					$item->pop();
					$player->getInventory()->setItemInHand($item);
					$player->getInventory()->addItem($milk);
				}
			}
			$this->getWorld()->addSound($this->location, new BucketFillWaterSound());
			return true;
		}

		return parent::onInteract($player, $clickVector);
	}

	public function getDrops() : array{
		$drops = [];
		$meat = $this->isOnFire() ? VanillaItems::STEAK() : VanillaItems::RAW_BEEF();
		$drops[] = $meat->setCount(mt_rand(1, 3));
		$leatherCount = mt_rand(0, 2);
		if($leatherCount > 0){
			$drops[] = VanillaItems::LEATHER()->setCount($leatherCount);
		}
		return $drops;
	}
}
