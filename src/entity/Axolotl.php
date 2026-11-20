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
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityRegainHealthEvent;
use pocketmine\item\Item;
use pocketmine\item\ItemTypeIds;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\IntTag;
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

class Axolotl extends Living implements Ageable{

	public const VARIANT_LUCY = 0;
	public const VARIANT_WILD = 1;
	public const VARIANT_GOLD = 2;
	public const VARIANT_CYAN = 3;
	public const VARIANT_BLUE = 4;

	public const TAG_VARIANT = "Variant";
	public const TAG_PLAYING_DEAD = "PlayingDead";
	public const TAG_DRY_TICKS = "DryTicks";
	public const TAG_AGE = "Age";
	public const TAG_IN_LOVE = "InLove";
	public const TAG_BREEDING_COOLDOWN = "BreedingCooldown";
	public const BREEDING_COOLDOWN_TICKS = 6000;
	public const MAX_DRY_TICKS = 6000; // 5 minutes on land

	protected int $variant = self::VARIANT_LUCY;
	protected bool $playingDead = false;
	protected int $playDeadTicks = 0;
	protected int $dryTicks = self::MAX_DRY_TICKS;
	protected int $age = 0;
	protected int $inLoveTicks = 0;
	protected int $breedingCooldownTicks = 0;

	public static function getNetworkTypeId() : string{
		return EntityIds::AXOLOTL;
	}

	public function getName() : string{
		return "Axolotl";
	}

	public function getDefaultMaxHealth() : int{
		return 14;
	}

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return $this->isBaby() ? new EntitySizeInfo(0.21, 0.375) : new EntitySizeInfo(0.42, 0.75);
	}

	protected function initEntity(CompoundTag $nbt) : void{
		$this->setMaxHealth(14);
		parent::initEntity($nbt);
		if($nbt->getTag("Health") === null){
			$this->setHealth(14.0);
		}

		$this->variant = $nbt->getInt(self::TAG_VARIANT, self::VARIANT_LUCY);
		if($this->variant < 0 || $this->variant > 4){
			$this->variant = self::VARIANT_LUCY;
		}

		$this->playingDead = $nbt->getByte(self::TAG_PLAYING_DEAD, 0) !== 0;
		$this->dryTicks = $nbt->getInt(self::TAG_DRY_TICKS, self::MAX_DRY_TICKS);
		$this->age = $nbt->getInt(self::TAG_AGE, 0);
		$this->inLoveTicks = $nbt->getInt(self::TAG_IN_LOVE, 0);
		$this->breedingCooldownTicks = $nbt->getInt(self::TAG_BREEDING_COOLDOWN, 0);
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT();
		$nbt->setInt(self::TAG_VARIANT, $this->variant);
		$nbt->setByte(self::TAG_PLAYING_DEAD, $this->playingDead ? 1 : 0);
		$nbt->setInt(self::TAG_DRY_TICKS, $this->dryTicks);
		$nbt->setInt(self::TAG_AGE, $this->age);
		$nbt->setInt(self::TAG_IN_LOVE, $this->inLoveTicks);
		$nbt->setInt(self::TAG_BREEDING_COOLDOWN, $this->breedingCooldownTicks);
		return $nbt;
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);
		$properties->setInt(EntityMetadataProperties::VARIANT, $this->variant);
		$properties->setGenericFlag(EntityMetadataFlags::PLAYING_DEAD, $this->playingDead);
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
		if($variant < 0 || $variant > 4){
			throw new \InvalidArgumentException("Invalid axolotl variant $variant");
		}
		$this->variant = $variant;
		$this->networkPropertiesDirty = true;
	}

	public function isPlayingDead() : bool{
		return $this->playingDead;
	}

	public function setPlayingDead(bool $playingDead, int $durationTicks = 200) : void{
		$this->playingDead = $playingDead;
		$this->playDeadTicks = $playingDead ? $durationTicks : 0;
		if($playingDead){
			$this->getEffects()->add(new EffectInstance(VanillaEffects::REGENERATION(), $durationTicks, 0));
		}
		$this->networkPropertiesDirty = true;
	}

	public function canBreatheInWater() : bool{
		return true;
	}

	public function attack(EntityDamageEvent $source) : void{
		parent::attack($source);

		if($source->isCancelled()){
			return;
		}

		// Play dead if taking damage in water and health drops <= 50% (7 HP)
		if($this->isUnderwater() && !$this->playingDead && $this->getHealth() <= 7.0 && $this->isAlive()){
			$this->setPlayingDead(true, 200);
		}
	}

	public function isBreedingItem(Item $item) : bool{
		return $item->getTypeId() === ItemTypeIds::CLOWNFISH;
	}

	public function canBreed() : bool{
		return !$this->isBaby() && $this->inLoveTicks <= 0 && $this->breedingCooldownTicks <= 0 && !$this->playingDead;
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
				$this->heal(new EntityRegainHealthEvent($this, 2.0, EntityRegainHealthEvent::CAUSE_EATING));
				$item->pop();
				$player->getInventory()->setItemInHand($item);
				$this->getWorld()->addParticle($this->location->add(0, 0.4, 0), new HappyVillagerParticle());
				return true;
			}

			if($this->getHealth() < $this->getMaxHealth()){
				$this->heal(new EntityRegainHealthEvent($this, 2.0, EntityRegainHealthEvent::CAUSE_EATING));
				$item->pop();
				$player->getInventory()->setItemInHand($item);
				$this->getWorld()->addParticle($this->location->add(0, 0.4, 0), new HeartParticle());
				return true;
			}

			if($this->canBreed()){
				$this->setInLove();
				$item->pop();
				$player->getInventory()->setItemInHand($item);
				$this->getWorld()->addParticle($this->location->add(0, 0.4, 0), new HeartParticle());
				return true;
			}
		}

		return false;
	}

	public function assistPlayerCombat(Player $player) : void{
		// Grant Regeneration and remove Mining Fatigue when player helps axolotl
		$player->getEffects()->add(new EffectInstance(VanillaEffects::REGENERATION(), 100, 0, false));
		$player->getEffects()->remove(VanillaEffects::MINING_FATIGUE());
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

		if($this->playingDead){
			$this->playDeadTicks -= $tickDiff;
			if($this->playDeadTicks <= 0 || $this->getHealth() >= 10.0){
				$this->setPlayingDead(false);
			}
			$hasUpdate = true;
		}

		// Moisture / drying out on land
		if($this->isUnderwater()){
			$this->dryTicks = self::MAX_DRY_TICKS;
		}else{
			$this->dryTicks -= $tickDiff;
			if($this->dryTicks <= 0){
				$this->attack(new EntityDamageEvent($this, EntityDamageEvent::CAUSE_SUFFOCATION, 2.0));
				$this->dryTicks = 20;
			}
		}

		if($this->inLoveTicks > 0){
			$this->inLoveTicks = max(0, $this->inLoveTicks - $tickDiff);
			if($this->inLoveTicks % 20 === 0 && $this->inLoveTicks > 0){
				$this->getWorld()->addParticle($this->location->add(0, 0.5, 0), new HeartParticle());
			}

			if(!$this->isBaby() && $this->inLoveTicks > 0){
				foreach($this->getWorld()->getNearbyEntities($this->boundingBox->expandedCopy(8, 8, 8), $this) as $nearby){
					if($nearby instanceof Axolotl && !$nearby->isBaby() && $nearby->isInLove() && $nearby->getId() !== $this->getId()){
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

	public function breedWith(Axolotl $partner) : void{
		$this->inLoveTicks = 0;
		$partner->inLoveTicks = 0;
		$this->breedingCooldownTicks = self::BREEDING_COOLDOWN_TICKS;
		$partner->breedingCooldownTicks = self::BREEDING_COOLDOWN_TICKS;

		$baby = new Axolotl(Location::fromObject($this->location, $this->getWorld()));
		$baby->setBaby(true);

		// Rare 1 in 1200 blue axolotl chance
		if(mt_rand(1, 1200) === 1){
			$baby->setVariant(self::VARIANT_BLUE);
		}else{
			$baby->setVariant(mt_rand(0, 1) === 0 ? $this->variant : $partner->variant);
		}

		$baby->spawnToAll();

		$this->getWorld()->addParticle($this->location->add(0, 0.5, 0), new HeartParticle());
		$this->getWorld()->addParticle($partner->location->add(0, 0.5, 0), new HeartParticle());
	}
}
