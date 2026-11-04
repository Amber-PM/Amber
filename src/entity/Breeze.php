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

use pocketmine\entity\projectile\WindCharge;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\Item;
use pocketmine\item\ItemIdentifier;
use pocketmine\item\ItemTypeIds;
use pocketmine\item\StringToItemParser;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\world\sound\PopSound;
use function mt_rand;

class Breeze extends Living{

	public static function getNetworkTypeId() : string{
		return EntityIds::BREEZE;
	}

	public function getName() : string{
		return "Breeze";
	}

	public function getDefaultMaxHealth() : int{
		return 30;
	}

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(1.77, 0.6);
	}

	protected function initEntity(CompoundTag $nbt) : void{
		$this->setMaxHealth(30);
		parent::initEntity($nbt);
		if($nbt->getTag("Health") === null){
			$this->setHealth(30.0);
		}
	}

	public function attack(EntityDamageEvent $source) : void{
		// Fall damage immunity
		if($source->getCause() === EntityDamageEvent::CAUSE_FALL){
			$source->cancel();
			return;
		}

		// Complete projectile deflection immunity
		if($source->getCause() === EntityDamageEvent::CAUSE_PROJECTILE){
			$source->cancel();
			$this->broadcastSound(new PopSound());
			return;
		}

		parent::attack($source);
	}

	public function shootWindCharge(Vector3 $targetPos) : WindCharge{
		$headPos = $this->location->add(0, 1.2, 0);
		$direction = $targetPos->subtractVector($headPos)->normalize();

		$location = Location::fromObject($headPos, $this->getWorld(), 0.0, 0.0);
		$windCharge = new WindCharge($location, $this);
		$windCharge->setMotion($direction->multiply(1.5));
		$windCharge->spawnToAll();

		return $windCharge;
	}

	public function getDrops() : array{
		$breezeRod = StringToItemParser::getInstance()->parse("breeze_rod")
			?? new Item(new ItemIdentifier(ItemTypeIds::FIRST_UNUSED_ITEM_ID), "Breeze Rod");
		return [
			$breezeRod->setCount(mt_rand(1, 2))
		];
	}

	public function getXpDropAmount() : int{
		return 5;
	}
}
