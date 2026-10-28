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

use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\player\Player;
use pocketmine\world\particle\HeartParticle;
use pocketmine\world\sound\PopSound;

class Allay extends Living{

	public const TAG_ITEM = "Item";
	public const TAG_DANCING = "Dancing";

	protected ?Item $heldItem = null;
	protected bool $dancing = false;
	protected ?int $ownerId = null;

	public static function getNetworkTypeId() : string{
		return EntityIds::ALLAY;
	}

	public function getName() : string{
		return "Allay";
	}

	public function getDefaultMaxHealth() : int{
		return 20;
	}

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(0.6, 0.35);
	}

	protected function getInitialGravity() : float{
		return 0.0;
	}

	protected function getInitialDragMultiplier() : float{
		return 0.0;
	}

	protected function initEntity(CompoundTag $nbt) : void{
		$this->setMaxHealth(20);
		parent::initEntity($nbt);

		$itemTag = $nbt->getCompoundTag(self::TAG_ITEM);
		if($itemTag !== null){
			$this->heldItem = Item::nbtDeserialize($itemTag);
		}

		$this->dancing = $nbt->getByte(self::TAG_DANCING, 0) !== 0;

		if($nbt->getTag("Health") === null){
			$this->setHealth(20.0);
		}
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT();
		if($this->heldItem !== null && !$this->heldItem->isNull()){
			$nbt->setTag(self::TAG_ITEM, $this->heldItem->nbtSerialize());
		}
		$nbt->setByte(self::TAG_DANCING, $this->dancing ? 1 : 0);
		return $nbt;
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);
		$properties->setGenericFlag(EntityMetadataFlags::DANCING, $this->dancing);
	}

	public function getHeldItem() : ?Item{
		return $this->heldItem !== null ? clone $this->heldItem : null;
	}

	public function setHeldItem(?Item $item) : void{
		$this->heldItem = $item !== null ? clone $item : null;
	}

	public function isDancing() : bool{
		return $this->dancing;
	}

	public function setDancing(bool $dancing = true) : void{
		$this->dancing = $dancing;
		$this->networkPropertiesDirty = true;
	}

	public function getOwnerId() : ?int{
		return $this->ownerId;
	}

	public function setOwnerId(?int $ownerId) : void{
		$this->ownerId = $ownerId;
	}

	public function interact(Player $player, Vector3 $clickPos) : bool{
		$item = $player->getInventory()->getItemInHand();

		// Duplication with amethyst shard while dancing
		if($this->dancing && $item->getTypeId() === VanillaItems::AMETHYST_SHARD()->getTypeId()){
			$item->pop();
			$player->getInventory()->setItemInHand($item);

			$clone = new Allay($this->location);
			$clone->spawnToAll();

			$this->getWorld()->addParticle($this->location, new HeartParticle());
			$this->broadcastSound(new PopSound());
			return true;
		}

		// Giving item to Allay
		if($this->heldItem === null && !$item->isNull()){
			$given = clone $item;
			$given->setCount(1);
			$this->setHeldItem($given);

			$item->pop();
			$player->getInventory()->setItemInHand($item);

			$this->ownerId = $player->getId();
			$this->broadcastSound(new PopSound());
			return true;
		}

		// Taking item back from Allay (when sneaking)
		if($this->heldItem !== null && $player->isSneaking()){
			$player->getInventory()->addItem(clone $this->heldItem);
			$this->heldItem = null;
			$this->broadcastSound(new PopSound());
			return true;
		}

		return false;
	}

	public function attack(EntityDamageEvent $source) : void{
		// Friendly fire prevention from owner
		if($source instanceof EntityDamageByEntityEvent && $this->ownerId !== null){
			$damager = $source->getDamager();
			if($damager !== null && $damager->getId() === $this->ownerId){
				$source->cancel();
				return;
			}
			try{
				$prop = new \ReflectionProperty(EntityDamageByEntityEvent::class, "damagerEntityId");
				if($prop->getValue($source) === $this->ownerId){
					$source->cancel();
					return;
				}
			}catch(\ReflectionException){
				// Ignore
			}
		}

		parent::attack($source);
	}

	public function getDrops() : array{
		$drops = [];
		if($this->heldItem !== null && !$this->heldItem->isNull()){
			$drops[] = clone $this->heldItem;
		}
		return $drops;
	}

	public function getXpDropAmount() : int{
		return 0;
	}
}
