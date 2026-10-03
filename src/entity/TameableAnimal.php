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

use pocketmine\block\utils\DyeColor;
use pocketmine\data\bedrock\DyeColorIdMap;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\player\Player;
use pocketmine\Server;
use Ramsey\Uuid\Uuid;

abstract class TameableAnimal extends Living implements Ageable{

	public const TAG_OWNER_UUID = "OwnerUUID";
	public const TAG_OWNER_NAME = "OwnerName";
	public const TAG_SITTING = "Sitting";
	public const TAG_COLLAR_COLOR = "CollarColor";
	public const TAG_IN_LOVE = "InLove";
	public const TAG_AGE = "Age";

	protected ?string $ownerUUID = null;
	protected ?string $ownerName = null;
	protected bool $sitting = false;
	protected DyeColor $collarColor = DyeColor::RED;
	protected int $inLoveTicks = 0;
	protected int $age = 0;
	protected bool $tamed = false;

	protected function initEntity(CompoundTag $nbt) : void{
		parent::initEntity($nbt);
		$this->readSaveData($nbt);
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT();
		$this->writeSaveData($nbt);
		return $nbt;
	}

	public function isTamed() : bool{
		return $this->tamed;
	}

	public function setTamed(bool $tamed = true) : void{
		$this->tamed = $tamed;
		$this->networkPropertiesDirty = true;
		if(isset($this->networkProperties)){
			$this->networkProperties->setGenericFlag(EntityMetadataFlags::TAMED, $tamed);
		}
	}

	public function getOwnerUUID() : ?string{
		return $this->ownerUUID;
	}

	public function setOwnerUUID(?string $uuid) : void{
		$this->ownerUUID = $uuid;
	}

	public function getOwnerName() : ?string{
		return $this->ownerName;
	}

	public function setOwnerName(?string $name) : void{
		$this->ownerName = $name;
	}

	public function getOwner() : ?Player{
		if($this->ownerUUID === null){
			return null;
		}

		if(isset($this->location)){
			$world = $this->location->getWorld();
			if($world->isLoaded()){
				foreach($world->getPlayers() as $player){
					if($player->getUniqueId()->toString() === $this->ownerUUID){
						return $player;
					}
				}
			}
		}

		if(Server::hasInstance() && Uuid::isValid($this->ownerUUID)){
			try{
				$player = Server::getInstance()->getPlayerByUUID(Uuid::fromString($this->ownerUUID));
				if($player !== null){
					return $player;
				}
			}catch(\Throwable){
				// Server or UUID lookup error
			}
		}

		return null;
	}

	public function isSitting() : bool{
		return $this->sitting;
	}

	public function setSitting(bool $sitting = true) : void{
		$this->sitting = $sitting;
		$this->networkPropertiesDirty = true;
		if(isset($this->networkProperties)){
			$this->networkProperties->setGenericFlag(EntityMetadataFlags::SITTING, $sitting);
		}
	}

	public function getCollarColor() : DyeColor{
		return $this->collarColor;
	}

	public function setCollarColor(DyeColor $color) : void{
		$this->collarColor = $color;
		$this->networkPropertiesDirty = true;
		if(isset($this->networkProperties)){
			$this->networkProperties->setByte(EntityMetadataProperties::COLOR, DyeColorIdMap::getInstance()->toId($color));
		}
	}

	public function getAge() : int{
		return $this->age;
	}

	public function setAge(int $age) : void{
		$this->age = $age;
		$this->networkPropertiesDirty = true;
		if(isset($this->networkProperties)){
			$this->networkProperties->setGenericFlag(EntityMetadataFlags::BABY, $this->isBaby());
		}
	}

	public function isBaby() : bool{
		return $this->age < 0;
	}

	public function getInLoveTicks() : int{
		return $this->inLoveTicks;
	}

	public function setInLoveTicks(int $ticks) : void{
		$this->inLoveTicks = $ticks;
	}

	public function isInLove() : bool{
		return $this->inLoveTicks > 0;
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);

		$properties->setGenericFlag(EntityMetadataFlags::TAMED, $this->tamed);
		$properties->setGenericFlag(EntityMetadataFlags::SITTING, $this->sitting);
		$properties->setGenericFlag(EntityMetadataFlags::BABY, $this->isBaby());
		$properties->setByte(EntityMetadataProperties::COLOR, DyeColorIdMap::getInstance()->toId($this->collarColor));

		$owner = $this->getOwner();
		if($owner !== null){
			$properties->setLong(EntityMetadataProperties::OWNER_EID, $owner->getId());
		}else{
			$properties->setLong(EntityMetadataProperties::OWNER_EID, 0);
		}
	}

	protected function writeSaveData(CompoundTag $nbt) : void{
		if($this->ownerUUID !== null){
			$nbt->setString(self::TAG_OWNER_UUID, $this->ownerUUID);
			if($this->ownerName !== null){
				$nbt->setString(self::TAG_OWNER_NAME, $this->ownerName);
			}
		}
		$nbt->setByte(self::TAG_SITTING, $this->sitting ? 1 : 0);
		$nbt->setByte(self::TAG_COLLAR_COLOR, DyeColorIdMap::getInstance()->toId($this->collarColor));
		$nbt->setInt(self::TAG_AGE, $this->age);
		$nbt->setInt(self::TAG_IN_LOVE, $this->inLoveTicks);
	}

	public function saveNBTData(CompoundTag $nbt) : void{
		$this->writeSaveData($nbt);
	}

	protected function readSaveData(CompoundTag $nbt) : void{
		$ownerUUID = $nbt->getTag(self::TAG_OWNER_UUID);
		if($ownerUUID instanceof StringTag && $ownerUUID->getValue() !== ""){
			$this->ownerUUID = $ownerUUID->getValue();
			$this->setTamed(true);
		}else{
			$this->ownerUUID = null;
		}

		$ownerName = $nbt->getTag(self::TAG_OWNER_NAME);
		if($ownerName instanceof StringTag && $ownerName->getValue() !== ""){
			$this->ownerName = $ownerName->getValue();
		}else{
			$this->ownerName = null;
		}

		$sittingTag = $nbt->getTag(self::TAG_SITTING);
		$this->setSitting($sittingTag instanceof ByteTag ? $sittingTag->getValue() !== 0 : false);

		$colorTag = $nbt->getTag(self::TAG_COLLAR_COLOR);
		$this->setCollarColor($colorTag instanceof ByteTag ? self::colorFromId($colorTag->getValue()) : DyeColor::RED);

		$this->setAge($nbt->getInt(self::TAG_AGE, 0));
		$this->setInLoveTicks($nbt->getInt(self::TAG_IN_LOVE, 0));
	}

	public function readNBTData(CompoundTag $nbt) : void{
		$this->readSaveData($nbt);
	}

	private static function colorFromId(int $id) : DyeColor{
		$mapped = DyeColorIdMap::getInstance()->fromId($id);
		if($mapped !== null){
			return $mapped;
		}
		foreach(DyeColor::cases() as $case){
			if($case->id() === $id){
				return $case;
			}
		}
		return DyeColor::RED;
	}
}
