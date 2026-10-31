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

use pocketmine\event\entity\EntityDamageByBlockEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityRegainHealthEvent;
use pocketmine\item\Item;
use pocketmine\item\ItemTypeIds;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\player\Player;
use pocketmine\world\particle\HappyVillagerParticle;
use pocketmine\world\particle\HeartParticle;
use function max;
use function min;
use function mt_rand;

class Fox extends Living implements Ageable{

	public const VARIANT_RED = 0;
	public const VARIANT_ARCTIC = 1;

	public const TAG_VARIANT = "Variant";
	public const TAG_ITEM_IN_MOUTH = "ItemInMouth";
	public const TAG_TRUSTED_UUID = "TrustedUUID";
	public const TAG_SLEEPING = "Sleeping";
	public const TAG_AGE = "Age";
	public const TAG_IN_LOVE = "InLove";
	public const TAG_BREEDING_COOLDOWN = "BreedingCooldown";
	public const BREEDING_COOLDOWN_TICKS = 6000;

	protected int $variant = self::VARIANT_RED;
	protected ?Item $itemInMouth = null;
	protected ?string $trustedUuid = null;
	protected bool $sleeping = false;
	protected int $age = 0;
	protected int $inLoveTicks = 0;
	protected int $breedingCooldownTicks = 0;

	public static function getNetworkTypeId() : string{
		return EntityIds::FOX;
	}

	public function getName() : string{
		return "Fox";
	}

	public function getDefaultMaxHealth() : int{
		return 10;
	}

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return $this->isBaby() ? new EntitySizeInfo(0.42, 0.36) : new EntitySizeInfo(0.7, 0.6);
	}

	protected function initEntity(CompoundTag $nbt) : void{
		$this->setMaxHealth(10);
		parent::initEntity($nbt);
		if($nbt->getTag("Health") === null){
			$this->setHealth(10.0);
		}

		$this->variant = $nbt->getInt(self::TAG_VARIANT, self::VARIANT_RED);
		if($this->variant !== self::VARIANT_RED && $this->variant !== self::VARIANT_ARCTIC){
			$this->variant = self::VARIANT_RED;
		}

		$itemTag = $nbt->getCompoundTag(self::TAG_ITEM_IN_MOUTH);
		if($itemTag !== null){
			$this->itemInMouth = Item::nbtDeserialize($itemTag);
		}

		$trusted = $nbt->getString(self::TAG_TRUSTED_UUID, "");
		if($trusted !== ""){
			$this->trustedUuid = $trusted;
		}

		$this->sleeping = $nbt->getByte(self::TAG_SLEEPING, 0) !== 0;
		$this->age = $nbt->getInt(self::TAG_AGE, 0);
		$this->inLoveTicks = $nbt->getInt(self::TAG_IN_LOVE, 0);
		$this->breedingCooldownTicks = $nbt->getInt(self::TAG_BREEDING_COOLDOWN, 0);
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT();
		$nbt->setInt(self::TAG_VARIANT, $this->variant);
		if($this->itemInMouth !== null){
			$nbt->setTag(self::TAG_ITEM_IN_MOUTH, $this->itemInMouth->nbtSerialize());
		}
		if($this->trustedUuid !== null){
			$nbt->setString(self::TAG_TRUSTED_UUID, $this->trustedUuid);
		}
		$nbt->setByte(self::TAG_SLEEPING, $this->sleeping ? 1 : 0);
		$nbt->setInt(self::TAG_AGE, $this->age);
		$nbt->setInt(self::TAG_IN_LOVE, $this->inLoveTicks);
		$nbt->setInt(self::TAG_BREEDING_COOLDOWN, $this->breedingCooldownTicks);
		return $nbt;
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);
		$properties->setInt(EntityMetadataProperties::VARIANT, $this->variant);
		$properties->setGenericFlag(EntityMetadataFlags::SLEEPING, $this->sleeping);
		$properties->setGenericFlag(EntityMetadataFlags::TRUSTING, $this->trustedUuid !== null);
		$properties->setGenericFlag(EntityMetadataFlags::BABY, $this->isBaby());
	}

	public function isBaby() : bool{
		return $this->age < 0;
	}

	public function setBaby(bool $baby) : void{
		$wasBaby = $this->isBaby();
		$this->age = $baby ? -24000 : 0;
		if($wasBaby !== $baby){
			$this->setScale($baby ? 0.5 : 1.0);
			$this->networkPropertiesDirty = true;
		}
	}

	public function getAge() : int{
		return $this->age;
	}

	public function setAge(int $age) : void{
		$wasBaby = $this->isBaby();
		$this->age = $age;
		if($wasBaby !== $this->isBaby()){
			$this->setScale($this->isBaby() ? 0.5 : 1.0);
			$this->networkPropertiesDirty = true;
		}
	}

	public function getVariant() : int{
		return $this->variant;
	}

	public function setVariant(int $variant) : void{
		if($variant !== self::VARIANT_RED && $variant !== self::VARIANT_ARCTIC){
			throw new \InvalidArgumentException("Invalid fox variant $variant");
		}
		$this->variant = $variant;
		$this->networkPropertiesDirty = true;
	}

	public function getItemInMouth() : ?Item{
		return $this->itemInMouth !== null ? clone $this->itemInMouth : null;
	}

	public function setItemInMouth(?Item $item) : void{
		$this->itemInMouth = $item !== null ? clone $item : null;
	}

	public function getTrustedUuid() : ?string{
		return $this->trustedUuid;
	}

	public function setTrustedUuid(?string $uuid) : void{
		$this->trustedUuid = $uuid;
		$this->networkPropertiesDirty = true;
	}

	public function isTrusted(Player $player) : bool{
		return $this->trustedUuid !== null && $this->trustedUuid === $player->getUniqueId()->toString();
	}

	public function isSleeping() : bool{
		return $this->sleeping;
	}

	public function setSleeping(bool $sleeping) : void{
		$this->sleeping = $sleeping;
		$this->networkPropertiesDirty = true;
	}

	public function fall(float $fallDistance) : void{
		// Foxes are agile and immune to fall damage in Bedrock Edition
	}

	public function attack(EntityDamageEvent $source) : void{
		if($source instanceof EntityDamageByBlockEvent){
			$block = $source->getDamager();
			if($block->getName() === "Sweet Berry Bush"){
				$source->cancel();
				return;
			}
		}

		if($this->sleeping){
			$this->setSleeping(false);
		}

		parent::attack($source);
	}

	public function isBreedingItem(Item $item) : bool{
		return $item->getTypeId() === ItemTypeIds::SWEET_BERRIES || $item->getTypeId() === ItemTypeIds::GLOW_BERRIES;
	}

	public function canBreed() : bool{
		return !$this->isBaby() && $this->inLoveTicks <= 0 && $this->breedingCooldownTicks <= 0;
	}

	public function isInLove() : bool{
		return $this->inLoveTicks > 0;
	}

	public function setInLove(int $ticks = 600) : void{
		$this->inLoveTicks = $ticks;
	}

	public function getBreedingCooldownTicks() : int{
		return $this->breedingCooldownTicks;
	}

	public function setBreedingCooldownTicks(int $ticks) : void{
		$this->breedingCooldownTicks = $ticks;
	}

	public function interact(Player $player, Vector3 $clickPos) : bool{
		$item = $player->getInventory()->getItemInHand();

		if($this->isBreedingItem($item)){
			if($this->isBaby()){
				$this->age = (int) min(0, $this->age + 2400);
				$healEvent = new EntityRegainHealthEvent($this, 2.0, EntityRegainHealthEvent::CAUSE_EATING);
				$this->heal($healEvent);
				$item->pop();
				$player->getInventory()->setItemInHand($item);
				$this->broadcastAnimation(new \pocketmine\entity\animation\ArmSwingAnimation($this));
				$this->getWorld()->addParticle($this->location->add(0, 0.5, 0), new HappyVillagerParticle());
				if($this->sleeping){
					$this->setSleeping(false);
				}
				return true;
			}

			if($this->getHealth() < $this->getMaxHealth()){
				$healEvent = new EntityRegainHealthEvent($this, 2.0, EntityRegainHealthEvent::CAUSE_EATING);
				$this->heal($healEvent);
				$item->pop();
				$player->getInventory()->setItemInHand($item);
				$this->getWorld()->addParticle($this->location->add(0, 0.5, 0), new HeartParticle());
				if($this->sleeping){
					$this->setSleeping(false);
				}
				return true;
			}

			if($this->canBreed()){
				$this->setInLove();
				$item->pop();
				$player->getInventory()->setItemInHand($item);
				$this->getWorld()->addParticle($this->location->add(0, 0.5, 0), new HeartParticle());
				if($this->trustedUuid === null){
					$this->setTrustedUuid($player->getUniqueId()->toString());
				}
				if($this->sleeping){
					$this->setSleeping(false);
				}
				return true;
			}
		}

		if($item->isNull() && $this->itemInMouth !== null && $this->isTrusted($player)){
			$mouthItem = clone $this->itemInMouth;
			$this->itemInMouth = null;
			$player->getInventory()->addItem($mouthItem);
			return true;
		}

		if(!$item->isNull() && $this->itemInMouth === null){
			$takeItem = $item->pop();
			$player->getInventory()->setItemInHand($item);
			$this->setItemInMouth($takeItem);
			if($this->sleeping){
				$this->setSleeping(false);
			}
			return true;
		}

		return false;
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		$hasUpdate = parent::entityBaseTick($tickDiff);

		if($this->age < 0){
			$wasBaby = $this->isBaby();
			$this->age += $tickDiff;
			if($wasBaby && !$this->isBaby()){
				$this->setScale(1.0);
				$this->networkPropertiesDirty = true;
			}
			$hasUpdate = true;
		}

		if($this->inLoveTicks > 0){
			$this->inLoveTicks = max(0, $this->inLoveTicks - $tickDiff);
			if($this->inLoveTicks % 20 === 0 && $this->inLoveTicks > 0){
				$this->getWorld()->addParticle($this->location->add(0, 0.7, 0), new HeartParticle());
			}

			if(!$this->isBaby() && $this->inLoveTicks > 0){
				foreach($this->getWorld()->getNearbyEntities($this->boundingBox->expandedCopy(8, 8, 8), $this) as $nearby){
					if($nearby instanceof Fox && !$nearby->isBaby() && $nearby->isInLove() && $nearby->getId() !== $this->getId()){
						$this->breedWith($nearby);
						break;
					}
				}
			}
			$hasUpdate = true;
		}

		if($this->breedingCooldownTicks > 0){
			$this->breedingCooldownTicks = max(0, $this->breedingCooldownTicks - $tickDiff);
			$hasUpdate = true;
		}

		return $hasUpdate;
	}

	public function breedWith(Fox $partner) : void{
		$this->inLoveTicks = 0;
		$partner->inLoveTicks = 0;
		$this->breedingCooldownTicks = self::BREEDING_COOLDOWN_TICKS;
		$partner->breedingCooldownTicks = self::BREEDING_COOLDOWN_TICKS;

		$baby = new Fox(Location::fromObject($this->location, $this->getWorld()));
		$baby->setBaby(true);

		// Arctic if both parents are arctic, or 50% chance if one parent is arctic
		if($this->variant === self::VARIANT_ARCTIC && $partner->variant === self::VARIANT_ARCTIC){
			$baby->setVariant(self::VARIANT_ARCTIC);
		}elseif($this->variant === self::VARIANT_ARCTIC || $partner->variant === self::VARIANT_ARCTIC){
			$baby->setVariant(mt_rand(0, 1) === 0 ? self::VARIANT_ARCTIC : self::VARIANT_RED);
		}else{
			$baby->setVariant(self::VARIANT_RED);
		}

		// Baby trusts the trusted player of either parent
		if($this->trustedUuid !== null){
			$baby->setTrustedUuid($this->trustedUuid);
		}elseif($partner->trustedUuid !== null){
			$baby->setTrustedUuid($partner->trustedUuid);
		}

		$baby->spawnToAll();

		$this->getWorld()->addParticle($this->location->add(0, 0.7, 0), new HeartParticle());
		$this->getWorld()->addParticle($partner->location->add(0, 0.7, 0), new HeartParticle());
	}

	public function getDrops() : array{
		$drops = [];
		if($this->itemInMouth !== null && !$this->itemInMouth->isNull()){
			$drops[] = clone $this->itemInMouth;
		}
		return $drops;
	}
}
