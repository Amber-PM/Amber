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
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\player\Player;
use pocketmine\world\sound\PopSound;
use function mt_rand;
use function strcasecmp;

class Pig extends Animal{

	public const TAG_SADDLED = "Saddle";

	public static function getNetworkTypeId() : string{
		return EntityIds::PIG;
	}

	protected bool $saddled = false;

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(0.9, 0.9);
	}

	public function getName() : string{
		return "Pig";
	}

	protected function addAttributes() : void{
		parent::addAttributes();
		$this->setMaxHealth(10);
		$this->setHealth(10.0);
	}

	public function isSaddled() : bool{
		return $this->saddled;
	}

	public function setSaddled(bool $saddled = true) : void{
		$this->saddled = $saddled;
		$this->networkPropertiesDirty = true;
	}

	protected function initEntity(CompoundTag $nbt) : void{
		parent::initEntity($nbt);
		if(($saddleTag = $nbt->getTag(self::TAG_SADDLED)) instanceof ByteTag){
			$this->saddled = $saddleTag->getValue() !== 0;
		}
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT();
		$nbt->setByte(self::TAG_SADDLED, $this->saddled ? 1 : 0);
		return $nbt;
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);
		$properties->setGenericFlag(EntityMetadataFlags::SADDLED, $this->saddled);
	}

	public function isBreedingItem(Item $item) : bool{
		$id = $item->getTypeId();
		return $id === ItemTypeIds::CARROT
			|| $id === ItemTypeIds::POTATO
			|| $id === ItemTypeIds::BEETROOT;
	}

	public function onInteract(Player $player, Vector3 $clickVector) : bool{
		$item = $player->getInventory()->getItemInHand();

		// Saddling interaction
		if(strcasecmp($item->getName(), "Saddle") === 0 && !$this->isSaddled() && !$this->isBaby()){
			$this->setSaddled(true);
			if($player->hasFiniteResources()){
				$item->pop();
				$player->getInventory()->setItemInHand($item);
			}
			$this->getWorld()->addSound($this->location, new PopSound());
			return true;
		}

		return parent::onInteract($player, $clickVector);
	}

	public function getDrops() : array{
		$drops = [];
		$meat = $this->isOnFire() ? VanillaItems::COOKED_PORKCHOP() : VanillaItems::RAW_PORKCHOP();
		$drops[] = $meat->setCount(mt_rand(1, 3));
		if($this->saddled){
			$drops[] = new Item(new ItemIdentifier(ItemTypeIds::newId()), "Saddle");
		}
		return $drops;
	}
}
