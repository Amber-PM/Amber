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
use pocketmine\item\Item;
use pocketmine\item\ItemIdentifier;
use pocketmine\item\ItemTypeIds;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\ShortTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\player\Player;
use pocketmine\world\particle\HeartParticle;
use function max;
use function min;
use function mt_rand;
use function str_contains;
use function strtolower;

class Horse extends AbstractHorse{

	public const TAG_VARIANT = "Variant";
	public const TAG_ARMOR = "ArmorItem";

	public const COAT_WHITE = 0;
	public const COAT_CREAMY = 1;
	public const COAT_CHESTNUT = 2;
	public const COAT_BROWN = 3;
	public const COAT_BLACK = 4;
	public const COAT_GRAY = 5;
	public const COAT_DARK_BROWN = 6;

	public const MARKING_NONE = 0;
	public const MARKING_WHITE = 1;
	public const MARKING_WHITEFIELD = 2;
	public const MARKING_WHITE_DOTS = 3;
	public const MARKING_BLACK_DOTS = 4;

	protected int $variant = 0;
	protected ?Item $armor = null;

	public static function getNetworkTypeId() : string{
		return EntityIds::HORSE;
	}

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(1.6, 1.3965);
	}

	public function getName() : string{
		return "Horse";
	}

	protected function addAttributes() : void{
		parent::addAttributes();
		$hp = (float) mt_rand(15, 30);
		$this->setMaxHealth((int) $hp);
		$this->setHealth($hp);
		$this->jumpStrength = 0.4 + (mt_rand(0, 600) / 1000.0);
		$this->speedMultiplier = 0.1125 + (mt_rand(0, 2250) / 10000.0);
		$this->setVariant(mt_rand(self::COAT_WHITE, self::COAT_DARK_BROWN), mt_rand(self::MARKING_NONE, self::MARKING_BLACK_DOTS));
	}

	public function getVariant() : int{
		return $this->variant;
	}

	public function setVariant(int $coat, int $markings = 0) : void{
		$coat = max(self::COAT_WHITE, min(self::COAT_DARK_BROWN, $coat));
		$markings = max(self::MARKING_NONE, min(self::MARKING_BLACK_DOTS, $markings));
		$this->variant = $coat | ($markings << 8);
		$this->networkPropertiesDirty = true;
		if(isset($this->networkProperties)){
			$this->networkProperties->setInt(EntityMetadataProperties::VARIANT, $this->variant);
		}
	}

	public function getCoat() : int{
		return $this->variant & 0xFF;
	}

	public function getMarkings() : int{
		return ($this->variant >> 8) & 0xFF;
	}

	public function getArmor() : ?Item{
		return $this->armor !== null ? clone $this->armor : null;
	}

	public function setArmor(?Item $armor) : void{
		$this->armor = $armor !== null ? clone $armor : null;
	}

	public static function isHorseArmor(Item $item) : bool{
		$name = strtolower($item->getName());
		return str_contains($name, "horse armor") || str_contains($name, "horsearmor");
	}

	public function onInteract(Player $player, Vector3 $clickPos) : bool{
		$item = $player->getInventory()->getItemInHand();

		// Horse armor interaction for tamed horses
		if($this->isTamed()){
			if($this->armor === null && self::isHorseArmor($item)){
				$this->setArmor($item);
				$this->consumeHeldItem($player, $item);
				return true;
			}

			// Shears remove armor
			if($this->armor !== null && $item->getTypeId() === ItemTypeIds::SHEARS && $this->rider === null){
				$armor = $this->armor;
				$this->setArmor(null);
				$this->getWorld()->dropItem($this->location, $armor);
				return true;
			}
		}

		return parent::onInteract($player, $clickPos);
	}

	public function findEligibleMate() : ?AbstractHorse{
		if(!isset($this->location) || !$this->canBreed()){
			return null;
		}
		$world = $this->getWorld();
		if(!$world->isLoaded()){
			return null;
		}

		$candidates = isset($this->boundingBox)
			? $world->getNearbyEntities($this->boundingBox->expandedCopy(8.0, 8.0, 8.0), $this)
			: $world->getEntities();

		foreach($candidates as $nearby){
			if(
				$nearby instanceof AbstractHorse &&
				$nearby !== $this &&
				$nearby->isAlive() &&
				!$nearby->isClosed() &&
				$nearby->isTamed() &&
				$nearby->isInLove() &&
				$nearby->canBreed() &&
				isset($nearby->location) &&
				$this->location->distance($nearby->location) <= 8.0
			){
				return $nearby;
			}
		}

		return null;
	}

	public function breed(AbstractHorse $partner) : ?AbstractHorse{
		if(!isset($this->location) || !isset($partner->location)){
			return null;
		}

		$midX = ($this->location->x + $partner->location->x) / 2.0;
		$midY = ($this->location->y + $partner->location->y) / 2.0;
		$midZ = ($this->location->z + $partner->location->z) / 2.0;
		$midLocation = new Location($midX, $midY, $midZ, $this->getWorld(), 0.0, 0.0);

		// Breeding Horse + Donkey results in a Mule!
		if($partner instanceof Donkey){
			$baby = new Mule($midLocation);
		}else{
			$baby = new Horse($midLocation);
			if(mt_rand(0, 1) === 0){
				$baby->setVariant($this->getCoat(), $this->getMarkings());
			}
		}

		$baby->setAge(-24000);
		$baby->setTamed(true);
		$baby->setOwnerUUID($this->getOwnerUUID());
		$baby->setOwnerName($this->getOwnerName());
		$baby->spawnToAll();

		$this->setInLoveTicks(0);
		$partner->setInLoveTicks(0);
		$this->setBreedingCooldownTicks(self::BREEDING_COOLDOWN_TICKS);
		$partner->setBreedingCooldownTicks(self::BREEDING_COOLDOWN_TICKS);

		if(isset($this->location)){
			$world = $this->getWorld();
			if($world->isLoaded()){
				$world->addParticle($midLocation->add(0, 1.0, 0), new HeartParticle());
			}
		}

		return $baby;
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		$hasUpdate = parent::entityBaseTick($tickDiff);

		if($this->inLoveTicks > 0 && $this->canBreed()){
			$mate = $this->findEligibleMate();
			if($mate !== null && isset($this->location) && isset($mate->location)){
				$dist = $this->location->distance($mate->location);
				if($dist <= 1.5){
					$this->breed($mate);
				}
				$hasUpdate = true;
			}
		}

		return $hasUpdate;
	}

	public function getDrops() : array{
		$drops = parent::getDrops();
		if($this->armor !== null){
			$drops[] = clone $this->armor;
		}
		return $drops;
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);

		$properties->setInt(EntityMetadataProperties::VARIANT, $this->variant);
	}

	protected function writeSaveData(CompoundTag $nbt) : void{
		parent::writeSaveData($nbt);
		$nbt->setInt(self::TAG_VARIANT, $this->variant);
		if($this->armor !== null){
			$nbt->setTag(self::TAG_ARMOR, $this->armor->nbtSerialize());
		}
	}

	protected function readSaveData(CompoundTag $nbt) : void{
		parent::readSaveData($nbt);
		$variantTag = $nbt->getTag(self::TAG_VARIANT);
		if($variantTag instanceof IntTag || $variantTag instanceof ByteTag || $variantTag instanceof ShortTag){
			$this->variant = $variantTag->getValue();
		}

		$armorTag = $nbt->getCompoundTag(self::TAG_ARMOR);
		if($armorTag !== null){
			$this->armor = Item::nbtDeserialize($armorTag);
		}
	}
}
