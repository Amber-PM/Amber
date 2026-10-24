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
use pocketmine\event\entity\EntityTameEvent;
use pocketmine\event\entity\PetSitChangeEvent;
use pocketmine\item\Item;
use pocketmine\item\ItemTypeIds;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\ShortTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\player\Player;
use pocketmine\world\particle\HeartParticle;
use pocketmine\world\particle\SmokeParticle;
use function max;
use function min;
use function mt_rand;

class Parrot extends TameableAnimal{

	public const TAG_VARIANT = "Variant";

	public const VARIANT_RED = 0;
	public const VARIANT_BLUE = 1;
	public const VARIANT_GREEN = 2;
	public const VARIANT_CYAN = 3;
	public const VARIANT_GRAY = 4;

	private const SEED_ITEM_IDS = [
		ItemTypeIds::WHEAT_SEEDS => true,
		ItemTypeIds::BEETROOT_SEEDS => true,
		ItemTypeIds::MELON_SEEDS => true,
		ItemTypeIds::PUMPKIN_SEEDS => true,
		ItemTypeIds::TORCHFLOWER_SEEDS => true,
		ItemTypeIds::PITCHER_POD => true,
	];

	protected int $variant = self::VARIANT_RED;
	protected bool $dancing = false;

	public static function getNetworkTypeId() : string{
		return EntityIds::PARROT;
	}

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(0.9, 0.5);
	}

	public function getName() : string{
		return "Parrot";
	}

	protected function addAttributes() : void{
		parent::addAttributes();
		$this->setMaxHealth(6);
		$this->setHealth(6.0);
	}

	protected function getInitialGravity() : float{
		return 0.04;
	}

	public function calculateFallDamage(float $fallDistance) : float{
		return 0.0;
	}

	public function getVariant() : int{
		return $this->variant;
	}

	public function setVariant(int $variant) : void{
		$this->variant = max(self::VARIANT_RED, min(self::VARIANT_GRAY, $variant));
		$this->networkPropertiesDirty = true;
		if(isset($this->networkProperties)){
			$this->networkProperties->setInt(EntityMetadataProperties::VARIANT, $this->variant);
		}
	}

	public function isDancing() : bool{
		return $this->dancing;
	}

	public function setDancing(bool $dancing = true) : void{
		$this->dancing = $dancing;
		$this->networkPropertiesDirty = true;
		if(isset($this->networkProperties)){
			$this->networkProperties->setGenericFlag(EntityMetadataFlags::DANCING, $dancing);
		}
	}

	public static function isSeed(Item $item) : bool{
		return isset(self::SEED_ITEM_IDS[$item->getTypeId()]);
	}

	public function tame(Player $player) : bool{
		$ev = new EntityTameEvent($this, $player);
		$ev->call();
		if($ev->isCancelled()){
			return false;
		}

		$this->setTamed(true);
		$this->setOwnerUUID($player->getUniqueId()->toString());
		$this->setOwnerName($player->getName());
		$this->setSitting(true);

		if(isset($this->networkProperties)){
			$this->networkProperties->setLong(EntityMetadataProperties::OWNER_EID, $player->getId());
		}

		$this->emitParticle(new HeartParticle());

		return true;
	}

	public function canBreed() : bool{
		// Parrots do not breed in vanilla Minecraft
		return false;
	}

	public function onInteract(Player $player, Vector3 $clickPos) : bool{
		$item = $player->getInventory()->getItemInHand();

		// Cookie poison: feeding a cookie fatally poisons/kills the parrot immediately
		if($item->getTypeId() === ItemTypeIds::COOKIE){
			$this->consumeHeldItem($player, $item);
			$this->emitParticle(new SmokeParticle());
			$ev = new EntityDamageEvent($this, EntityDamageEvent::CAUSE_MAGIC, 999.0);
			$this->attack($ev);
			return true;
		}

		if(!$this->isTamed()){
			if(self::isSeed($item)){
				$this->consumeHeldItem($player, $item);
				if(mt_rand(1, 3) === 1){
					$this->tame($player);
				}else{
					$this->emitParticle(new SmokeParticle());
				}
				return true;
			}
			return false;
		}

		$ownerUuid = $this->getOwnerUUID();
		if($ownerUuid !== null && $ownerUuid === $player->getUniqueId()->toString()){
			// Toggle sit/stand
			$ev = new PetSitChangeEvent($this, $player, !$this->isSitting());
			$ev->call();
			if(!$ev->isCancelled()){
				$this->setSitting($ev->isSitting());
				return true;
			}
			return false;
		}

		return false;
	}

	public function getDrops() : array{
		return [
			VanillaItems::FEATHER()->setCount(mt_rand(1, 2)),
		];
	}

	public function getXpDropAmount() : int{
		return mt_rand(1, 3);
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);

		$properties->setGenericFlag(EntityMetadataFlags::CAN_FLY, true);
		$properties->setGenericFlag(EntityMetadataFlags::DANCING, $this->dancing);
		$properties->setInt(EntityMetadataProperties::VARIANT, $this->variant);
	}

	protected function writeSaveData(CompoundTag $nbt) : void{
		parent::writeSaveData($nbt);
		$nbt->setInt(self::TAG_VARIANT, $this->variant);
	}

	protected function readSaveData(CompoundTag $nbt) : void{
		parent::readSaveData($nbt);
		$variantTag = $nbt->getTag(self::TAG_VARIANT);
		if($variantTag instanceof IntTag || $variantTag instanceof ByteTag || $variantTag instanceof ShortTag){
			$this->setVariant($variantTag->getValue());
		}else{
			$this->setVariant(mt_rand(self::VARIANT_RED, self::VARIANT_GRAY));
		}
	}
}
