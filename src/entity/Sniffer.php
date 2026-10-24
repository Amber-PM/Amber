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
use pocketmine\item\VanillaItems;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use function mt_rand;

class Sniffer extends Living{

	protected bool $sniffing = false;
	protected bool $digging = false;
	protected bool $searching = false;

	public static function getNetworkTypeId() : string{
		return EntityIds::SNIFFER;
	}

	public function getName() : string{
		return "Sniffer";
	}

	public function getDefaultMaxHealth() : int{
		return 14;
	}

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(1.75, 1.9);
	}

	protected function initEntity(CompoundTag $nbt) : void{
		$this->setMaxHealth(14);
		parent::initEntity($nbt);
		if($nbt->getTag("Health") === null){
			$this->setHealth(14.0);
		}
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);

		$properties->setGenericFlag(EntityMetadataFlags::SNIFFING, $this->sniffing);
		$properties->setGenericFlag(EntityMetadataFlags::SCENTING, $this->sniffing);
		$properties->setGenericFlag(EntityMetadataFlags::DIGGING, $this->digging);
		$properties->setGenericFlag(EntityMetadataFlags::SEARCHING, $this->searching);
	}

	public function isSniffing() : bool{
		return $this->sniffing;
	}

	public function setSniffing(bool $sniffing = true) : void{
		$this->sniffing = $sniffing;
		$this->networkPropertiesDirty = true;
	}

	public function isDigging() : bool{
		return $this->digging;
	}

	public function setDigging(bool $digging = true) : void{
		$this->digging = $digging;
		$this->networkPropertiesDirty = true;
	}

	public function isSearching() : bool{
		return $this->searching;
	}

	public function setSearching(bool $searching = true) : void{
		$this->searching = $searching;
		$this->networkPropertiesDirty = true;
	}

	public function dig() : Item{
		return VanillaItems::TORCHFLOWER_SEEDS();
	}

	public function getDrops() : array{
		$moss = StringToItemParser::getInstance()->parse("moss_block")
			?? new Item(new ItemIdentifier(ItemTypeIds::FIRST_UNUSED_ITEM_ID), "Moss Block");
		return [
			$moss->setCount(1)
		];
	}

	public function getXpDropAmount() : int{
		return mt_rand(1, 3);
	}
}
