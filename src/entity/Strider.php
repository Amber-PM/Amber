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

use pocketmine\block\BlockTypeIds;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\Item;
use pocketmine\item\ItemIdentifier;
use pocketmine\item\ItemTypeIds;
use pocketmine\item\StringToItemParser;
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
use function str_contains;
use function strtolower;

class Strider extends Living{

	public const TAG_SADDLED = "Saddled";
	public const TAG_SHIVERING = "Shivering";

	protected bool $saddled = false;
	protected bool $shivering = false;

	public static function getNetworkTypeId() : string{
		return EntityIds::STRIDER;
	}

	public function getName() : string{
		return "Strider";
	}

	public function getDefaultMaxHealth() : int{
		return 20;
	}

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(1.7, 0.9);
	}

	protected function initEntity(CompoundTag $nbt) : void{
		$this->setMaxHealth(20);
		parent::initEntity($nbt);
		if($nbt->getTag("Health") === null){
			$this->setHealth(20.0);
		}

		$this->saddled = $nbt->getByte(self::TAG_SADDLED, 0) !== 0;
		$this->shivering = $nbt->getByte(self::TAG_SHIVERING, 0) !== 0;
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT();
		$nbt->setByte(self::TAG_SADDLED, $this->saddled ? 1 : 0);
		$nbt->setByte(self::TAG_SHIVERING, $this->shivering ? 1 : 0);
		return $nbt;
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);
		$properties->setGenericFlag(EntityMetadataFlags::SADDLED, $this->saddled);
	}

	public function isSaddled() : bool{
		return $this->saddled;
	}

	public function setSaddled(bool $saddled = true) : void{
		$this->saddled = $saddled;
		$this->networkPropertiesDirty = true;
	}

	public function isShivering() : bool{
		return $this->shivering;
	}

	public function setShivering(bool $shivering = true) : void{
		$this->shivering = $shivering;
	}

	public function attack(EntityDamageEvent $source) : void{
		// Complete fire and lava immunity
		if(
			$source->getCause() === EntityDamageEvent::CAUSE_FIRE ||
			$source->getCause() === EntityDamageEvent::CAUSE_FIRE_TICK ||
			$source->getCause() === EntityDamageEvent::CAUSE_LAVA
		){
			$source->cancel();
			return;
		}

		parent::attack($source);
	}

	public function interact(Player $player, Vector3 $clickPos) : bool{
		$item = $player->getInventory()->getItemInHand();

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

		return false;
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		$hasUpdate = parent::entityBaseTick($tickDiff);

		if(!$this->isAlive()){
			return $hasUpdate;
		}

		// Shivering when out of lava
		$block = $this->getWorld()->getBlockAt((int) $this->location->x, (int) $this->location->y, (int) $this->location->z);
		$inLava = $block->getTypeId() === BlockTypeIds::LAVA;

		if(!$inLava && !$this->shivering){
			$this->setShivering(true);
			$hasUpdate = true;
		}elseif($inLava && $this->shivering){
			$this->setShivering(false);
			$hasUpdate = true;
		}

		return $hasUpdate;
	}

	public function getDrops() : array{
		$drops = [
			VanillaItems::STRING()->setCount(mt_rand(2, 5))
		];

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
