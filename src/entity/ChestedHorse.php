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

use pocketmine\block\VanillaBlocks;
use pocketmine\inventory\HorseInventory;
use pocketmine\item\Item;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\player\Player;
use pocketmine\world\sound\ItemFrameAddItemSound;
use function strtolower;

abstract class ChestedHorse extends AbstractHorse{

	public const TAG_CHESTED = "Chested";
	public const TAG_ITEMS = "Items";

	protected bool $chested = false;
	protected ?HorseInventory $chestInventory = null;

	public function isChested() : bool{
		return $this->chested;
	}

	public function setChested(bool $chested = true) : void{
		$this->chested = $chested;
		if($chested){
			if($this->chestInventory === null){
				$this->chestInventory = new HorseInventory($this, 15);
			}
		}else{
			$this->chestInventory = null;
		}
		$this->networkPropertiesDirty = true;
		if(isset($this->networkProperties)){
			$this->networkProperties->setGenericFlag(EntityMetadataFlags::CHESTED, $chested);
		}
	}

	public function getChestInventory() : ?HorseInventory{
		return $this->chestInventory;
	}

	public static function isChestItem(Item $item) : bool{
		return strtolower($item->getName()) === "chest" || $item->getTypeId() === VanillaBlocks::CHEST()->asItem()->getTypeId();
	}

	public function onInteract(Player $player, Vector3 $clickPos) : bool{
		$item = $player->getInventory()->getItemInHand();

		if($this->isTamed()){
			// Attach chest
			if(!$this->isChested() && self::isChestItem($item)){
				$this->setChested(true);
				$this->consumeHeldItem($player, $item);
				$this->broadcastSound(new ItemFrameAddItemSound());
				return true;
			}

			// Open chest inventory if sneaking
			if($this->isChested() && $player->isSneaking() && $this->chestInventory !== null){
				$player->setCurrentWindow($this->chestInventory);
				return true;
			}
		}

		return parent::onInteract($player, $clickPos);
	}

	public function getDrops() : array{
		$drops = parent::getDrops();
		if($this->chested){
			$drops[] = VanillaBlocks::CHEST()->asItem();
			if($this->chestInventory !== null){
				foreach($this->chestInventory->getContents() as $item){
					$drops[] = $item;
				}
			}
		}
		return $drops;
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);

		$properties->setGenericFlag(EntityMetadataFlags::CHESTED, $this->chested);
	}

	protected function writeSaveData(CompoundTag $nbt) : void{
		parent::writeSaveData($nbt);
		$nbt->setByte(self::TAG_CHESTED, $this->chested ? 1 : 0);

		if($this->chested && $this->chestInventory !== null){
			$itemList = new ListTag();
			foreach($this->chestInventory->getContents() as $slot => $item){
				$itemList->push($item->nbtSerialize($slot));
			}
			$nbt->setTag(self::TAG_ITEMS, $itemList);
		}
	}

	protected function readSaveData(CompoundTag $nbt) : void{
		parent::readSaveData($nbt);
		$chestedTag = $nbt->getTag(self::TAG_CHESTED);
		$this->setChested($chestedTag instanceof ByteTag ? $chestedTag->getValue() !== 0 : false);

		if($this->chested && $this->chestInventory !== null){
			$itemList = $nbt->getListTag(self::TAG_ITEMS);
			if($itemList !== null){
				/** @var CompoundTag $itemTag */
				foreach($itemList as $itemTag){
					$slot = $itemTag->getByte("Slot");
					$this->chestInventory->setItem($slot, Item::nbtDeserialize($itemTag));
				}
			}
		}
	}
}
