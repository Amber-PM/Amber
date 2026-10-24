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

use pocketmine\block\utils\FroglightType;
use pocketmine\block\VanillaBlocks;
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
use pocketmine\world\particle\HeartParticle;
use function max;
use function min;
use function mt_rand;

class Frog extends Living implements Ageable{

	public const VARIANT_TEMPERATE = 0;
	public const VARIANT_COLD = 1;
	public const VARIANT_WARM = 2;

	public const TAG_VARIANT = "Variant";
	public const TAG_IN_LOVE = "InLove";
	public const TAG_BREEDING_COOLDOWN = "BreedingCooldown";
	public const BREEDING_COOLDOWN_TICKS = 6000;

	protected int $variant = self::VARIANT_TEMPERATE;
	protected int $inLoveTicks = 0;
	protected int $breedingCooldownTicks = 0;
	protected int $eatCooldownTicks = 0;

	public static function getNetworkTypeId() : string{
		return EntityIds::FROG;
	}

	public function getName() : string{
		return "Frog";
	}

	public function getDefaultMaxHealth() : int{
		return 10;
	}

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(0.55, 0.5);
	}

	public function isBaby() : bool{
		return false; // Frogs are never babies; their juvenile form is Tadpole
	}

	protected function initEntity(CompoundTag $nbt) : void{
		$this->setMaxHealth(10);
		parent::initEntity($nbt);
		if($nbt->getTag("Health") === null){
			$this->setHealth(10.0);
		}

		$this->variant = $nbt->getInt(self::TAG_VARIANT, self::VARIANT_TEMPERATE);
		if($this->variant < 0 || $this->variant > 2){
			$this->variant = self::VARIANT_TEMPERATE;
		}

		$this->inLoveTicks = $nbt->getInt(self::TAG_IN_LOVE, 0);
		$this->breedingCooldownTicks = $nbt->getInt(self::TAG_BREEDING_COOLDOWN, 0);
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT();
		$nbt->setInt(self::TAG_VARIANT, $this->variant);
		$nbt->setInt(self::TAG_IN_LOVE, $this->inLoveTicks);
		$nbt->setInt(self::TAG_BREEDING_COOLDOWN, $this->breedingCooldownTicks);
		return $nbt;
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);
		$properties->setInt(EntityMetadataProperties::VARIANT, $this->variant);
	}

	public function getVariant() : int{
		return $this->variant;
	}

	public function setVariant(int $variant) : void{
		if($variant < 0 || $variant > 2){
			throw new \InvalidArgumentException("Invalid frog variant $variant");
		}
		$this->variant = $variant;
		$this->networkPropertiesDirty = true;
	}

	public function fall(float $fallDistance) : void{
		// Frogs take 10 points less fall damage
		$damage = (int) max(0, $fallDistance - 3.0 - 10.0);
		if($damage > 0){
			$ev = new EntityDamageEvent($this, EntityDamageEvent::CAUSE_FALL, $damage);
			$this->attack($ev);
		}
	}

	public function canBreatheInWater() : bool{
		return true;
	}

	public function isBreedingItem(Item $item) : bool{
		return $item->getTypeId() === ItemTypeIds::SLIMEBALL;
	}

	public function canBreed() : bool{
		return $this->inLoveTicks <= 0 && $this->breedingCooldownTicks <= 0;
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
			if($this->getHealth() < $this->getMaxHealth()){
				$this->heal(new EntityRegainHealthEvent($this, 2.0, EntityRegainHealthEvent::CAUSE_EATING));
				$item->pop();
				$player->getInventory()->setItemInHand($item);
				$this->getWorld()->addParticle($this->location->add(0, 0.5, 0), new HeartParticle());
				return true;
			}

			if($this->canBreed()){
				$this->setInLove();
				$item->pop();
				$player->getInventory()->setItemInHand($item);
				$this->getWorld()->addParticle($this->location->add(0, 0.5, 0), new HeartParticle());
				return true;
			}
		}

		return false;
	}

	public function eatSmallMob(Living $mob) : bool{
		$networkId = $mob::getNetworkTypeId();

		if($networkId === "minecraft:slime"){
			// Small slime eaten -> drops slimeball
			$mob->flagForDespawn();
			$this->getWorld()->dropItem($mob->getLocation(), VanillaItems::SLIMEBALL());
			return true;
		}

		if($networkId === "minecraft:magma_cube"){
			// Small magma cube eaten -> drops froglight based on variant
			$mob->flagForDespawn();
			$froglightType = match($this->variant){
				self::VARIANT_COLD => FroglightType::VERDANT,
				self::VARIANT_WARM => FroglightType::PEARLESCENT,
				default => FroglightType::OCHRE
			};
			$froglightItem = VanillaBlocks::FROGLIGHT()->setFroglightType($froglightType)->asItem();
			$this->getWorld()->dropItem($mob->getLocation(), $froglightItem);
			return true;
		}

		return false;
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		$hasUpdate = parent::entityBaseTick($tickDiff);

		if($this->eatCooldownTicks > 0){
			$this->eatCooldownTicks = max(0, $this->eatCooldownTicks - $tickDiff);
		}

		if($this->inLoveTicks > 0){
			$this->inLoveTicks = max(0, $this->inLoveTicks - $tickDiff);
			if($this->inLoveTicks % 20 === 0 && $this->inLoveTicks > 0){
				$this->getWorld()->addParticle($this->location->add(0, 0.6, 0), new HeartParticle());
			}

			if($this->inLoveTicks > 0){
				foreach($this->getWorld()->getNearbyEntities($this->boundingBox->expandedCopy(8, 8, 8), $this) as $nearby){
					if($nearby instanceof Frog && $nearby->isInLove() && $nearby->getId() !== $this->getId()){
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

		// Look for small slime or magma cube to eat
		if($this->eatCooldownTicks <= 0){
			foreach($this->getWorld()->getNearbyEntities($this->boundingBox->expandedCopy(6, 3, 6), $this) as $nearby){
				if($nearby instanceof Living && $nearby->isAlive()){
					$typeId = $nearby::getNetworkTypeId();
					if($typeId === "minecraft:slime" || $typeId === "minecraft:magma_cube"){
						// Check small size (width <= 0.6)
						if($nearby->getInitialSizeInfo()->getWidth() <= 0.65){
							$this->eatSmallMob($nearby);
							$this->eatCooldownTicks = 60; // 3 sec cooldown
							break;
						}
					}
				}
			}
		}

		return $hasUpdate;
	}

	public function breedWith(Frog $partner) : void{
		$this->inLoveTicks = 0;
		$partner->inLoveTicks = 0;
		$this->breedingCooldownTicks = self::BREEDING_COOLDOWN_TICKS;
		$partner->breedingCooldownTicks = self::BREEDING_COOLDOWN_TICKS;

		$tadpole = new Tadpole(Location::fromObject($this->location, $this->getWorld()));
		$tadpole->spawnToAll();

		$this->getWorld()->addParticle($this->location->add(0, 0.6, 0), new HeartParticle());
		$this->getWorld()->addParticle($partner->location->add(0, 0.6, 0), new HeartParticle());
	}
}
