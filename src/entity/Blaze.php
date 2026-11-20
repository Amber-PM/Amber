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

use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\world\sound\BlazeShootSound;
use function mt_rand;

class Blaze extends Living{

	public static function getNetworkTypeId() : string{
		return EntityIds::BLAZE;
	}

	public function getName() : string{
		return "Blaze";
	}

	public function getDefaultMaxHealth() : int{
		return 20;
	}

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(1.8, 0.5);
	}

	protected function initEntity(CompoundTag $nbt) : void{
		$this->setMaxHealth(20);
		parent::initEntity($nbt);
		if($nbt->getTag("Health") === null){
			$this->setHealth(20.0);
		}
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);
		$properties->setGenericFlag(EntityMetadataFlags::ONFIRE, true);
	}

	public function attack(EntityDamageEvent $source) : void{
		// Fire, lava, and fall immunity
		if(
			$source->getCause() === EntityDamageEvent::CAUSE_FIRE ||
			$source->getCause() === EntityDamageEvent::CAUSE_FIRE_TICK ||
			$source->getCause() === EntityDamageEvent::CAUSE_LAVA ||
			$source->getCause() === EntityDamageEvent::CAUSE_FALL
		){
			$source->cancel();
			return;
		}

		parent::attack($source);
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		$hasUpdate = parent::entityBaseTick($tickDiff);

		if(!$this->isAlive()){
			return $hasUpdate;
		}

		// Water hurts Blaze (1 damage per tick)
		if($this->isUnderwater()){
			$ev = new EntityDamageEvent($this, EntityDamageEvent::CAUSE_DROWNING, 1.0);
			$this->attack($ev);
			$hasUpdate = true;
		}

		return $hasUpdate;
	}

	public function shootFireball() : void{
		$this->broadcastSound(new BlazeShootSound());
	}

	public function getDrops() : array{
		return [
			VanillaItems::BLAZE_ROD()->setCount(mt_rand(0, 1))
		];
	}

	public function getXpDropAmount() : int{
		return 10;
	}
}
