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

namespace pocketmine\entity\object;

use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Living;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\MobEquipmentPacket;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\network\mcpe\protocol\types\inventory\ContainerIds;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStackWrapper;
use pocketmine\player\Player;

class ArmorStand extends Living{

	public const TAG_POSE = "Pose";
	public const TAG_MAIN_HAND = "MainHand";
	public const TAG_LOCKED = "Locked";
	public const TAG_SHOW_BASE_PLATE = "ShowBasePlate";
	public const POSE_COUNT = 13;

	protected int $poseIndex = 0;
	protected bool $locked = false;
	protected bool $showBasePlate = true;
	protected Item $heldItem;

	public static function getNetworkTypeId() : string{
		return EntityIds::ARMOR_STAND;
	}

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(1.975, 0.5);
	}

	public function getName() : string{
		return "Armor Stand";
	}

	protected function getInitialDragMultiplier() : float{
		return 0.02;
	}

	protected function getInitialGravity() : float{
		return 0.08;
	}

	public function getPickedItem() : ?Item{
		return VanillaItems::ARMOR_STAND();
	}

	public function getPose() : int{
		return $this->poseIndex;
	}

	public function setPose(int $pose) : void{
		$this->poseIndex = ($pose % self::POSE_COUNT + self::POSE_COUNT) % self::POSE_COUNT;
		try{
			$this->getNetworkProperties()->setInt(EntityMetadataProperties::ARMOR_STAND_POSE_INDEX, $this->poseIndex);
		}catch(\Error){
			//networkProperties not initialized
		}
	}

	public function isLocked() : bool{
		return $this->locked;
	}

	public function setLocked(bool $locked) : void{
		$this->locked = $locked;
	}

	public function hasBasePlate() : bool{
		return $this->showBasePlate;
	}

	public function setBasePlate(bool $show) : void{
		$this->showBasePlate = $show;
		try{
			$this->getNetworkProperties()->setGenericFlag(EntityMetadataFlags::SHOWBASE, $show);
		}catch(\Error){
			//networkProperties not initialized
		}
	}

	public function getMainHandItem() : Item{
		if(!isset($this->heldItem)){
			$this->heldItem = VanillaItems::AIR();
		}
		return clone $this->heldItem;
	}

	public function setMainHandItem(Item $item) : void{
		$this->heldItem = clone $item;
		$this->broadcastMainHandItem();
	}

	public function broadcastMainHandItem() : void{
		if(!isset($this->heldItem)){
			$this->heldItem = VanillaItems::AIR();
		}
		foreach($this->getViewers() as $player){
			$session = $player->getNetworkSession();
			$session->sendDataPacket(MobEquipmentPacket::create(
				$this->getId(),
				ItemStackWrapper::legacy($session->getTypeConverter()->coreItemStackToNet($this->heldItem)),
				0,
				0,
				ContainerIds::INVENTORY
			));
		}
	}

	public function readSaveData(CompoundTag $nbt) : void{
		if(method_exists(parent::class, 'readSaveData')){
			parent::readSaveData($nbt);
		}
		$this->poseIndex = $nbt->getInt(self::TAG_POSE, 0);
		$this->locked = $nbt->getByte(self::TAG_LOCKED, 0) !== 0;
		$this->showBasePlate = $nbt->getByte(self::TAG_SHOW_BASE_PLATE, 1) !== 0;

		$mainHandTag = $nbt->getTag(self::TAG_MAIN_HAND);
		if($mainHandTag instanceof CompoundTag){
			$this->heldItem = Item::nbtDeserialize($mainHandTag);
		}else{
			$this->heldItem = VanillaItems::AIR();
		}
	}

	protected function initEntity(CompoundTag $nbt) : void{
		parent::initEntity($nbt);
		$this->readSaveData($nbt);
	}

	public function writeSaveData(CompoundTag $nbt) : void{
		if(method_exists(parent::class, 'writeSaveData')){
			parent::writeSaveData($nbt);
		}
		$nbt->setInt(self::TAG_POSE, $this->poseIndex);
		$nbt->setByte(self::TAG_LOCKED, $this->locked ? 1 : 0);
		$nbt->setByte(self::TAG_SHOW_BASE_PLATE, $this->showBasePlate ? 1 : 0);
		if(isset($this->heldItem) && !$this->heldItem->isNull()){
			$nbt->setTag(self::TAG_MAIN_HAND, $this->heldItem->nbtSerialize());
		}
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT();
		$this->writeSaveData($nbt);
		return $nbt;
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);

		$properties->setGenericFlag(EntityMetadataFlags::SHOWBASE, $this->showBasePlate);
		$properties->setInt(EntityMetadataProperties::ARMOR_STAND_POSE_INDEX, $this->poseIndex);
	}

	protected function sendSpawnPacket(Player $player) : void{
		parent::sendSpawnPacket($player);

		if(!isset($this->heldItem)){
			$this->heldItem = VanillaItems::AIR();
		}

		$session = $player->getNetworkSession();
		$session->sendDataPacket(MobEquipmentPacket::create(
			$this->getId(),
			ItemStackWrapper::legacy($session->getTypeConverter()->coreItemStackToNet($this->heldItem)),
			0,
			0,
			ContainerIds::INVENTORY
		));
	}
}
