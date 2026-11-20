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

use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\event\entity\EntityRegainHealthEvent;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\player\Player;
use pocketmine\world\sound\BottleEmptySound;
use pocketmine\world\sound\PotionSplashSound;
use function mt_rand;

class Witch extends Living{

	public const TAG_DRINKING_POTION = "DrinkingPotion";
	public const TAG_DRINK_COOLDOWN = "DrinkCooldown";

	protected bool $isDrinking = false;
	protected int $drinkingTicks = 0;
	protected int $drinkCooldown = 0;
	protected int $attackCooldown = 0;

	public static function getNetworkTypeId() : string{
		return EntityIds::WITCH;
	}

	public function getName() : string{
		return "Witch";
	}

	public function getDefaultMaxHealth() : int{
		return 26;
	}

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(1.9, 0.6);
	}

	protected function initEntity(CompoundTag $nbt) : void{
		$this->setMaxHealth(26);
		parent::initEntity($nbt);
		if($nbt->getTag("Health") === null){
			$this->setHealth(26.0);
		}

		$this->isDrinking = $nbt->getByte(self::TAG_DRINKING_POTION, 0) !== 0;
		$this->drinkCooldown = $nbt->getInt(self::TAG_DRINK_COOLDOWN, 0);
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT();
		$nbt->setByte(self::TAG_DRINKING_POTION, $this->isDrinking ? 1 : 0);
		$nbt->setInt(self::TAG_DRINK_COOLDOWN, $this->drinkCooldown);
		return $nbt;
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);
		$properties->setGenericFlag(EntityMetadataFlags::CHARGED, $this->isDrinking);
	}

	public function isDrinkingPotion() : bool{
		return $this->isDrinking;
	}

	public function startDrinkingPotion() : void{
		$this->isDrinking = true;
		$this->drinkingTicks = 32; // 1.6s to drink
		$this->networkPropertiesDirty = true;
		$this->broadcastSound(new BottleEmptySound());
	}

	public function finishDrinkingPotion() : void{
		$this->isDrinking = false;
		$this->drinkCooldown = 60; // 3 second cooldown between drinks
		$this->networkPropertiesDirty = true;

		// Select potion to drink based on status
		if($this->isOnFire() && !$this->getEffects()->has(VanillaEffects::FIRE_RESISTANCE())){
			$this->getEffects()->add(new EffectInstance(VanillaEffects::FIRE_RESISTANCE(), 3600, 0));
		}elseif($this->isUnderwater() && !$this->getEffects()->has(VanillaEffects::WATER_BREATHING())){
			$this->getEffects()->add(new EffectInstance(VanillaEffects::WATER_BREATHING(), 3600, 0));
		}elseif($this->getHealth() < $this->getMaxHealth()){
			$this->heal(new EntityRegainHealthEvent($this, 4.0, EntityRegainHealthEvent::CAUSE_CUSTOM));
		}elseif(!$this->getEffects()->has(VanillaEffects::SPEED())){
			$this->getEffects()->add(new EffectInstance(VanillaEffects::SPEED(), 1800, 0));
		}
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		$hasUpdate = parent::entityBaseTick($tickDiff);

		if(!$this->isAlive()){
			return $hasUpdate;
		}

		if($this->isDrinking){
			$this->drinkingTicks -= $tickDiff;
			$hasUpdate = true;
			if($this->drinkingTicks <= 0){
				$this->finishDrinkingPotion();
			}
			return $hasUpdate;
		}

		if($this->drinkCooldown > 0){
			$this->drinkCooldown -= $tickDiff;
		}else{
			// Check if should start drinking
			if($this->isOnFire() && !$this->getEffects()->has(VanillaEffects::FIRE_RESISTANCE())){
				$this->startDrinkingPotion();
				return true;
			}
			if($this->isUnderwater() && !$this->getEffects()->has(VanillaEffects::WATER_BREATHING())){
				$this->startDrinkingPotion();
				return true;
			}
			if($this->getHealth() < ($this->getMaxHealth() - 4)){
				$this->startDrinkingPotion();
				return true;
			}
		}

		// Ranged attack target player
		if($this->attackCooldown > 0){
			$this->attackCooldown -= $tickDiff;
		}else{
			foreach($this->getWorld()->getNearbyEntities($this->boundingBox->expandedCopy(10, 5, 10), $this) as $nearby){
				if($nearby instanceof Player && $nearby->isAlive() && !$nearby->isCreative() && !$nearby->isSpectator()){
					$distSq = $this->location->distanceSquared($nearby->getLocation());
					if($distSq <= 100.0){ // within 10 blocks
						$this->attackTarget($nearby, $distSq);
						$this->attackCooldown = 60; // 3 second attack interval in BDS
						break;
					}
				}
			}
		}

		return $hasUpdate;
	}

	public function attackTarget(Player $target, float $distSq) : void{
		$effects = $target->getEffects();
		$this->broadcastSound(new PotionSplashSound());

		if($distSq >= 64.0 && !$effects->has(VanillaEffects::SLOWNESS())){ // >= 8 blocks away
			$effects->add(new EffectInstance(VanillaEffects::SLOWNESS(), 600, 0));
		}elseif($target->getHealth() >= 8.0 && !$effects->has(VanillaEffects::POISON())){
			$effects->add(new EffectInstance(VanillaEffects::POISON(), 600, 0));
		}elseif(mt_rand(0, 3) === 0 && !$effects->has(VanillaEffects::WEAKNESS())){
			$effects->add(new EffectInstance(VanillaEffects::WEAKNESS(), 600, 0));
		}else{
			$target->getEffects()->add(new EffectInstance(VanillaEffects::INSTANT_DAMAGE(), 1, 0));
		}
	}

	public function getDrops() : array{
		$drops = [];
		$possibleDrops = [
			VanillaItems::GLASS_BOTTLE(),
			VanillaItems::GLOWSTONE_DUST(),
			VanillaItems::GUNPOWDER(),
			VanillaItems::REDSTONE_DUST(),
			VanillaItems::SPIDER_EYE(),
			VanillaItems::STICK(),
			VanillaItems::SUGAR()
		];

		$numDrops = mt_rand(1, 3);
		for($i = 0; $i < $numDrops; ++$i){
			$item = clone $possibleDrops[mt_rand(0, count($possibleDrops) - 1)];
			$drops[] = $item->setCount(mt_rand(0, 2));
		}

		return $drops;
	}

	public function getXpDropAmount() : int{
		return 5;
	}
}
