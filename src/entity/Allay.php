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

use pocketmine\entity\object\ItemEntity;
use pocketmine\event\entity\EntityRegainHealthEvent;
use pocketmine\item\Item;
use pocketmine\item\ItemTypeIds;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\player\Player;
use pocketmine\world\particle\HeartParticle;
use function max;

class Allay extends Living implements Ageable{

	public const TAG_HELD_ITEM = "HeldItem";
	public const TAG_OWNER_UUID = "OwnerUUID";
	public const TAG_DUPLICATION_COOLDOWN = "DuplicationCooldown";
	public const DUPLICATION_COOLDOWN_TICKS = 6000;

	protected ?Item $heldItem = null;
	/** @var Item[] */
	protected array $collectedItems = [];
	protected ?string $ownerUuid = null;
	protected int $duplicationCooldownTicks = 0;
	protected bool $dancing = false;
	protected int $regenTicks = 0;

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

	public function isBaby() : bool{
		return false;
	}

	protected function initEntity(CompoundTag $nbt) : void{
		$this->setMaxHealth(20);
		parent::initEntity($nbt);
		if($nbt->getTag("Health") === null){
			$this->setHealth(20.0);
		}

		$itemTag = $nbt->getCompoundTag(self::TAG_HELD_ITEM);
		if($itemTag !== null){
			$this->heldItem = Item::nbtDeserialize($itemTag);
		}

		$owner = $nbt->getString(self::TAG_OWNER_UUID, "");
		if($owner !== ""){
			$this->ownerUuid = $owner;
		}

		$this->duplicationCooldownTicks = $nbt->getInt(self::TAG_DUPLICATION_COOLDOWN, 0);
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT();
		if($this->heldItem !== null){
			$nbt->setTag(self::TAG_HELD_ITEM, $this->heldItem->nbtSerialize());
		}
		if($this->ownerUuid !== null){
			$nbt->setString(self::TAG_OWNER_UUID, $this->ownerUuid);
		}
		$nbt->setInt(self::TAG_DUPLICATION_COOLDOWN, $this->duplicationCooldownTicks);
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

	public function getOwnerUuid() : ?string{
		return $this->ownerUuid;
	}

	public function setOwnerUuid(?string $uuid) : void{
		$this->ownerUuid = $uuid;
	}

	public function isDancing() : bool{
		return $this->dancing;
	}

	public function setDancing(bool $dancing) : void{
		$this->dancing = $dancing;
		$this->networkPropertiesDirty = true;
	}

	public function getDuplicationCooldownTicks() : int{
		return $this->duplicationCooldownTicks;
	}

	public function setDuplicationCooldownTicks(int $ticks) : void{
		$this->duplicationCooldownTicks = $ticks;
	}

	public function canDuplicate() : bool{
		return $this->dancing && $this->duplicationCooldownTicks <= 0;
	}

	public function fall(float $fallDistance) : void{
		// Flying mob: takes no fall damage
	}

	public function interact(Player $player, Vector3 $clickPos) : bool{
		$item = $player->getInventory()->getItemInHand();

		// Duplication with amethyst shard while dancing
		if($item->getTypeId() === ItemTypeIds::AMETHYST_SHARD && $this->canDuplicate()){
			$item->pop();
			$player->getInventory()->setItemInHand($item);

			$this->duplicationCooldownTicks = self::DUPLICATION_COOLDOWN_TICKS;

			$clone = new Allay(Location::fromObject($this->location, $this->getWorld()));
			$clone->setOwnerUuid($player->getUniqueId()->toString());
			$clone->setDuplicationCooldownTicks(self::DUPLICATION_COOLDOWN_TICKS);
			$clone->spawnToAll();

			$this->getWorld()->addParticle($this->location->add(0, 0.5, 0), new HeartParticle());
			return true;
		}

		// Giving item to Allay
		if($this->heldItem === null && !$item->isNull()){
			$takeItem = $item->pop();
			$player->getInventory()->setItemInHand($item);
			$this->setHeldItem($takeItem);
			$this->setOwnerUuid($player->getUniqueId()->toString());
			return true;
		}

		// Taking item back from Allay with empty hand
		if($this->heldItem !== null && $item->isNull()){
			$returnedItem = clone $this->heldItem;
			$this->heldItem = null;
			$player->getInventory()->setItemInHand($returnedItem);

			// Also drop any collected matching items to the player
			foreach($this->collectedItems as $collected){
				$player->getInventory()->addItem($collected);
			}
			$this->collectedItems = [];
			return true;
		}

		return false;
	}

	public function duplicate() : Allay{
		$this->duplicationCooldownTicks = self::DUPLICATION_COOLDOWN_TICKS;

		$clone = new Allay(Location::fromObject($this->location, $this->getWorld()));
		$clone->setOwnerUuid($this->ownerUuid);
		$clone->setDuplicationCooldownTicks(self::DUPLICATION_COOLDOWN_TICKS);
		$clone->spawnToAll();

		$this->getWorld()->addParticle($this->location->add(0, 0.5, 0), new HeartParticle());
		return $clone;
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		$hasUpdate = parent::entityBaseTick($tickDiff);

		if($this->duplicationCooldownTicks > 0){
			$this->duplicationCooldownTicks = max(0, $this->duplicationCooldownTicks - $tickDiff);
		}

		// Passive health regeneration: +2 HP every 20 ticks (1 sec)
		$this->regenTicks += $tickDiff;
		if($this->regenTicks >= 20){
			$this->regenTicks = 0;
			if($this->getHealth() < $this->getMaxHealth() && $this->isAlive()){
				$this->heal(new EntityRegainHealthEvent($this, 2.0, EntityRegainHealthEvent::CAUSE_REGEN));
			}
		}

		// Search nearby items if holding an item
		if($this->heldItem !== null){
			foreach($this->getWorld()->getNearbyEntities($this->boundingBox->expandedCopy(32, 16, 32), $this) as $nearby){
				if($nearby instanceof ItemEntity && !$nearby->isFlaggedForDespawn()){
					$groundItem = $nearby->getItem();
					if($groundItem->equals($this->heldItem, true, false)){
						// Pickup item if close (within 2 blocks)
						if($this->location->distanceSquared($nearby->getLocation()) <= 4.0){
							$this->collectedItems[] = clone $groundItem;
							$nearby->flagForDespawn();
							break;
						}
					}
				}
			}

			// Deliver collected items to owner if owner is nearby
			if(count($this->collectedItems) > 0 && $this->ownerUuid !== null){
				foreach($this->getWorld()->getPlayers() as $player){
					if($player->getUniqueId()->toString() === $this->ownerUuid){
						if($this->location->distanceSquared($player->getLocation()) <= 9.0){
							foreach($this->collectedItems as $item){
								$this->getWorld()->dropItem($player->getLocation(), $item);
							}
							$this->collectedItems = [];
							break;
						}
					}
				}
			}
		}

		return $hasUpdate;
	}

	public function getDrops() : array{
		$drops = [];
		if($this->heldItem !== null && !$this->heldItem->isNull()){
			$drops[] = clone $this->heldItem;
		}
		foreach($this->collectedItems as $collected){
			$drops[] = clone $collected;
		}
		return $drops;
	}
}
