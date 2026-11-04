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
use pocketmine\item\ItemIdentifier;
use pocketmine\item\ItemTypeIds;
use pocketmine\item\StringToItemParser;
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

class Camel extends Living{

	public const TAG_SADDLED = "Saddled";
	public const TAG_SITTING = "Sitting";

	protected bool $saddled = false;
	protected bool $sitting = false;
	protected bool $dashing = false;

	public static function getNetworkTypeId() : string{
		return EntityIds::CAMEL;
	}

	public function getName() : string{
		return "Camel";
	}

	public function getDefaultMaxHealth() : int{
		return 32;
	}

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(2.375, 1.7);
	}

	protected function initEntity(CompoundTag $nbt) : void{
		$this->setMaxHealth(32);
		parent::initEntity($nbt);

		$this->saddled = $nbt->getByte(self::TAG_SADDLED, 0) !== 0;
		$this->sitting = $nbt->getByte(self::TAG_SITTING, 0) !== 0;

		if($nbt->getTag("Health") === null){
			$this->setHealth(32.0);
		}
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT();
		$nbt->setByte(self::TAG_SADDLED, $this->saddled ? 1 : 0);
		$nbt->setByte(self::TAG_SITTING, $this->sitting ? 1 : 0);
		return $nbt;
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);

		$properties->setGenericFlag(EntityMetadataFlags::SADDLED, $this->saddled);
		$properties->setGenericFlag(EntityMetadataFlags::SITTING, $this->sitting);
		$properties->setGenericFlag(EntityMetadataFlags::CAN_DASH, $this->dashing);
	}

	public function isSaddled() : bool{
		return $this->saddled;
	}

	public function setSaddled(bool $saddled = true) : void{
		$this->saddled = $saddled;
		$this->networkPropertiesDirty = true;
	}

	public function isSitting() : bool{
		return $this->sitting;
	}

	public function setSitting(bool $sitting = true) : void{
		$this->sitting = $sitting;
		$this->networkPropertiesDirty = true;
	}

	public function isDashing() : bool{
		return $this->dashing;
	}

	public function setDashing(bool $dashing = true) : void{
		$this->dashing = $dashing;
		$this->networkPropertiesDirty = true;
	}

	public function interact(Player $player, Vector3 $clickPos) : bool{
		$item = $player->getInventory()->getItemInHand();

		// Saddling interaction
		if(
			(
				$item->getVanillaName() === "Saddle" ||
				str_contains(strtolower($item->getName()), "saddle")
			) && !$this->saddled
		){
			$item->pop();
			$player->getInventory()->setItemInHand($item);
			$this->setSaddled(true);
			$this->broadcastSound(new PopSound());
			return true;
		}

		// Sitting toggle when sneaking
		if($player->isSneaking()){
			$this->setSitting(!$this->sitting);
			return true;
		}

		return false;
	}

	public function getDrops() : array{
		$drops = [];
		if($this->saddled){
			$saddle = StringToItemParser::getInstance()->parse("saddle");
			$drops[] = $saddle ?? new Item(new ItemIdentifier(ItemTypeIds::FIRST_UNUSED_ITEM_ID), "Saddle");
		}
		return $drops;
	}

	public function getXpDropAmount() : int{
		return mt_rand(1, 3);
	}
}
