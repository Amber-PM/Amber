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

use pocketmine\block\Block;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityRegainHealthEvent;
use pocketmine\item\Item;
use pocketmine\item\ItemTypeIds;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\player\Player;
use pocketmine\world\particle\HappyVillagerParticle;
use pocketmine\world\particle\HeartParticle;
use pocketmine\world\sound\BucketFillWaterSound;
use function max;
use function min;
use function mt_rand;

class Goat extends Living implements Ageable{

	public const VARIANT_DEFAULT = 0;
	public const VARIANT_SCREAMER = 1;

	public const TAG_VARIANT = "Variant";
	public const TAG_HORN_COUNT = "HornCount";
	public const TAG_AGE = "Age";
	public const TAG_IN_LOVE = "InLove";
	public const TAG_BREEDING_COOLDOWN = "BreedingCooldown";
	public const BREEDING_COOLDOWN_TICKS = 6000;

	protected int $variant = self::VARIANT_DEFAULT;
	protected int $hornCount = 2;
	protected int $ramCooldownTicks = 600;
	protected bool $isRamming = false;
	protected int $age = 0;
	protected int $inLoveTicks = 0;
	protected int $breedingCooldownTicks = 0;

	public static function getNetworkTypeId() : string{
		return EntityIds::GOAT;
	}

	public function getName() : string{
		return "Goat";
	}

	public function getDefaultMaxHealth() : int{
		return 10;
	}

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return $this->isBaby() ? new EntitySizeInfo(0.65, 0.45) : new EntitySizeInfo(1.3, 0.9);
	}

	protected function initEntity(CompoundTag $nbt) : void{
		$this->setMaxHealth(10);
		parent::initEntity($nbt);
		if($nbt->getTag("Health") === null){
			$this->setHealth(10.0);
		}

		$this->variant = $nbt->getInt(self::TAG_VARIANT, self::VARIANT_DEFAULT);
		if($this->variant !== self::VARIANT_DEFAULT && $this->variant !== self::VARIANT_SCREAMER){
			$this->variant = self::VARIANT_DEFAULT;
		}

		$this->hornCount = $nbt->getInt(self::TAG_HORN_COUNT, 2);
		if($this->hornCount < 0 || $this->hornCount > 2){
			$this->hornCount = 2;
		}

		$this->age = $nbt->getInt(self::TAG_AGE, 0);
		$this->inLoveTicks = $nbt->getInt(self::TAG_IN_LOVE, 0);
		$this->breedingCooldownTicks = $nbt->getInt(self::TAG_BREEDING_COOLDOWN, 0);

		$this->resetRamCooldown();
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT();
		$nbt->setInt(self::TAG_VARIANT, $this->variant);
		$nbt->setInt(self::TAG_HORN_COUNT, $this->hornCount);
		$nbt->setInt(self::TAG_AGE, $this->age);
		$nbt->setInt(self::TAG_IN_LOVE, $this->inLoveTicks);
		$nbt->setInt(self::TAG_BREEDING_COOLDOWN, $this->breedingCooldownTicks);
		return $nbt;
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);
		$properties->setInt(EntityMetadataProperties::VARIANT, $this->variant);
		$properties->setInt(EntityMetadataProperties::GOAT_HORN_COUNT, $this->hornCount);
		$properties->setGenericFlag(EntityMetadataFlags::RAM_ATTACK, $this->isRamming);
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
		if($variant !== self::VARIANT_DEFAULT && $variant !== self::VARIANT_SCREAMER){
			throw new \InvalidArgumentException("Invalid goat variant $variant");
		}
		$this->variant = $variant;
		$this->networkPropertiesDirty = true;
	}

	public function isScreamer() : bool{
		return $this->variant === self::VARIANT_SCREAMER;
	}

	public function getHornCount() : int{
		return $this->hornCount;
	}

	public function setHornCount(int $count) : void{
		$this->hornCount = max(0, min(2, $count));
		$this->networkPropertiesDirty = true;
	}

	public function isRamming() : bool{
		return $this->isRamming;
	}

	public function setRamming(bool $ramming) : void{
		$this->isRamming = $ramming;
		$this->networkPropertiesDirty = true;
	}

	public function resetRamCooldown() : void{
		if($this->isScreamer()){
			$this->ramCooldownTicks = mt_rand(100, 300); // 5 - 15 seconds
		}else{
			$this->ramCooldownTicks = mt_rand(600, 6000); // 30 - 300 seconds
		}
	}

	public function fall(float $fallDistance) : void{
		// Goats take 10 points less fall damage (can fall 13 blocks with 0 damage)
		$damage = (int) max(0, $fallDistance - 3.0 - 10.0);
		if($damage > 0){
			$ev = new EntityDamageEvent($this, EntityDamageEvent::CAUSE_FALL, $damage);
			$this->attack($ev);
		}
	}

	public function isBreedingItem(Item $item) : bool{
		return $item->getTypeId() === ItemTypeIds::WHEAT;
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

		// Milking mechanic
		if(!$this->isBaby() && $item->getTypeId() === ItemTypeIds::BUCKET){
			$item->pop();
			$player->getInventory()->setItemInHand($item);
			$player->getInventory()->addItem(VanillaItems::MILK_BUCKET());
			$this->getWorld()->addSound($this->location, new BucketFillWaterSound());
			return true;
		}

		// Wheat feeding / breeding
		if($this->isBreedingItem($item)){
			if($this->isBaby()){
				$this->age = (int) min(0, $this->age + 2400);
				$this->heal(new EntityRegainHealthEvent($this, 2.0, EntityRegainHealthEvent::CAUSE_EATING));
				$item->pop();
				$player->getInventory()->setItemInHand($item);
				$this->getWorld()->addParticle($this->location->add(0, 0.7, 0), new HappyVillagerParticle());
				return true;
			}

			if($this->getHealth() < $this->getMaxHealth()){
				$this->heal(new EntityRegainHealthEvent($this, 2.0, EntityRegainHealthEvent::CAUSE_EATING));
				$item->pop();
				$player->getInventory()->setItemInHand($item);
				$this->getWorld()->addParticle($this->location->add(0, 0.7, 0), new HeartParticle());
				return true;
			}

			if($this->canBreed()){
				$this->setInLove();
				$item->pop();
				$player->getInventory()->setItemInHand($item);
				$this->getWorld()->addParticle($this->location->add(0, 0.7, 0), new HeartParticle());
				return true;
			}
		}

		return false;
	}

	public function ramTarget(Entity $target) : void{
		$this->setRamming(true);

		// Knockback and damage
		$damage = $this->isBaby() ? 1.0 : 2.0;
		$ev = new EntityDamageByEntityEvent($this, $target, EntityDamageEvent::CAUSE_ENTITY_ATTACK, $damage);
		$target->attack($ev);

		if(!$ev->isCancelled()){
			$diff = $target->getLocation()->subtractVector($this->location);
			$diff->y = 0;
			$dist = $diff->length();
			if($dist > 0){
				$norm = $diff->divide($dist);
				$target->setMotion(new Vector3($norm->x * 2.5, 0.4, $norm->z * 2.5));
			}
		}

		$this->setRamming(false);
		$this->resetRamCooldown();
	}

	public function handleBlockImpact(Block $block) : void{
		if($this->isRamming && $this->hornCount > 0){
			if($block->isSolid()){
				$this->setHornCount($this->hornCount - 1);
				$this->getWorld()->dropItem($this->location, VanillaItems::GOAT_HORN());
				$this->setRamming(false);
				$this->resetRamCooldown();
			}
		}
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

		if($this->ramCooldownTicks > 0){
			$this->ramCooldownTicks = max(0, $this->ramCooldownTicks - $tickDiff);
		}

		if($this->inLoveTicks > 0){
			$this->inLoveTicks = max(0, $this->inLoveTicks - $tickDiff);
			if($this->inLoveTicks % 20 === 0 && $this->inLoveTicks > 0){
				$this->getWorld()->addParticle($this->location->add(0, 1.0, 0), new HeartParticle());
			}

			if(!$this->isBaby() && $this->inLoveTicks > 0){
				foreach($this->getWorld()->getNearbyEntities($this->boundingBox->expandedCopy(8, 8, 8), $this) as $nearby){
					if($nearby instanceof Goat && !$nearby->isBaby() && $nearby->isInLove() && $nearby->getId() !== $this->getId()){
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

	public function breedWith(Goat $partner) : void{
		$this->inLoveTicks = 0;
		$partner->inLoveTicks = 0;
		$this->breedingCooldownTicks = self::BREEDING_COOLDOWN_TICKS;
		$partner->breedingCooldownTicks = self::BREEDING_COOLDOWN_TICKS;

		$baby = new Goat(Location::fromObject($this->location, $this->getWorld()));
		$baby->setBaby(true);

		// Screamer chance: 2% base, or 50% if either parent is screamer
		if($this->isScreamer() || $partner->isScreamer()){
			$baby->setVariant(mt_rand(0, 1) === 0 ? self::VARIANT_SCREAMER : self::VARIANT_DEFAULT);
		}else{
			$baby->setVariant(mt_rand(1, 50) === 1 ? self::VARIANT_SCREAMER : self::VARIANT_DEFAULT);
		}

		$baby->setHornCount(2);
		$baby->spawnToAll();

		$this->getWorld()->addParticle($this->location->add(0, 1.0, 0), new HeartParticle());
		$this->getWorld()->addParticle($partner->location->add(0, 1.0, 0), new HeartParticle());
	}
}
