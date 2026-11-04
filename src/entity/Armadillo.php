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
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\player\Player;
use pocketmine\world\sound\PopSound;
use function mt_rand;
use function str_contains;
use function strtolower;

class Armadillo extends Living{

	public const TAG_ROLLED_UP = "RolledUp";

	protected bool $rolledUp = false;

	public static function getNetworkTypeId() : string{
		return EntityIds::ARMADILLO;
	}

	public function getName() : string{
		return "Armadillo";
	}

	public function getDefaultMaxHealth() : int{
		return 12;
	}

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(0.8, 0.8);
	}

	protected function initEntity(CompoundTag $nbt) : void{
		$this->setMaxHealth(12);
		parent::initEntity($nbt);

		$this->rolledUp = $nbt->getByte(self::TAG_ROLLED_UP, 0) !== 0;

		if($nbt->getTag("Health") === null){
			$this->setHealth(12.0);
		}
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT();
		$nbt->setByte(self::TAG_ROLLED_UP, $this->rolledUp ? 1 : 0);
		return $nbt;
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);
		$properties->setGenericFlag(EntityMetadataFlags::SCARED, $this->rolledUp);
	}

	public function isRolledUp() : bool{
		return $this->rolledUp;
	}

	public function setRolledUp(bool $rolled = true) : void{
		$this->rolledUp = $rolled;
		$this->networkPropertiesDirty = true;
	}

	public function attack(EntityDamageEvent $source) : void{
		if($this->rolledUp){
			// Shell reduces incoming damage by 50%
			$source->setBaseDamage($source->getBaseDamage() * 0.5);
		}

		parent::attack($source);

		if(!$source->isCancelled()){
			// Threat triggers curling into ball
			$this->setRolledUp(true);
		}
	}

	public function interact(Player $player, Vector3 $clickPos) : bool{
		$item = $player->getInventory()->getItemInHand();

		// Brushing interaction drops an Armadillo Scute
		if(str_contains(strtolower($item->getVanillaName()), "brush") || str_contains(strtolower($item->getName()), "brush")){
			$scute = VanillaItems::SCUTE();
			$this->getWorld()->dropItem($this->location, $scute);
			$this->broadcastSound(new PopSound());
			return true;
		}

		return false;
	}

	public function getDrops() : array{
		return [
			VanillaItems::SCUTE()->setCount(mt_rand(0, 1))
		];
	}

	public function getXpDropAmount() : int{
		return mt_rand(1, 3);
	}
}
