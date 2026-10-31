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
use pocketmine\player\Player;
use pocketmine\world\particle\HappyVillagerParticle;
use pocketmine\world\particle\HeartParticle;
use function max;
use function min;

class SeaTurtle extends Living implements Ageable{

	public const TAG_HOME_POS_X = "HomePosX";
	public const TAG_HOME_POS_Y = "HomePosY";
	public const TAG_HOME_POS_Z = "HomePosZ";
	public const TAG_HAS_EGG = "HasEgg";
	public const TAG_AGE = "Age";
	public const TAG_IN_LOVE = "InLove";
	public const TAG_BREEDING_COOLDOWN = "BreedingCooldown";
	public const BREEDING_COOLDOWN_TICKS = 6000;

	protected ?Vector3 $homePos = null;
	protected bool $hasEgg = false;
	protected int $age = 0;
	protected int $inLoveTicks = 0;
	protected int $breedingCooldownTicks = 0;

	public static function getNetworkTypeId() : string{
		return EntityIds::TURTLE;
	}

	public function getName() : string{
		return "Sea Turtle";
	}

	public function getDefaultMaxHealth() : int{
		return 30;
	}

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return $this->isBaby() ? new EntitySizeInfo(0.12, 0.36) : new EntitySizeInfo(0.4, 1.2);
	}

	protected function initEntity(CompoundTag $nbt) : void{
		$this->setMaxHealth(30);
		parent::initEntity($nbt);
		if($nbt->getTag("Health") === null){
			$this->setHealth(30.0);
		}

		if($nbt->getTag(self::TAG_HOME_POS_X) !== null){
			$this->homePos = new Vector3(
				$nbt->getInt(self::TAG_HOME_POS_X),
				$nbt->getInt(self::TAG_HOME_POS_Y),
				$nbt->getInt(self::TAG_HOME_POS_Z)
			);
		}else{
			$this->homePos = $this->location->asVector3();
		}

		$this->hasEgg = $nbt->getByte(self::TAG_HAS_EGG, 0) !== 0;
		$this->age = $nbt->getInt(self::TAG_AGE, 0);
		$this->inLoveTicks = $nbt->getInt(self::TAG_IN_LOVE, 0);
		$this->breedingCooldownTicks = $nbt->getInt(self::TAG_BREEDING_COOLDOWN, 0);
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT();
		if($this->homePos !== null){
			$nbt->setInt(self::TAG_HOME_POS_X, (int) $this->homePos->x);
			$nbt->setInt(self::TAG_HOME_POS_Y, (int) $this->homePos->y);
			$nbt->setInt(self::TAG_HOME_POS_Z, (int) $this->homePos->z);
		}
		$nbt->setByte(self::TAG_HAS_EGG, $this->hasEgg ? 1 : 0);
		$nbt->setInt(self::TAG_AGE, $this->age);
		$nbt->setInt(self::TAG_IN_LOVE, $this->inLoveTicks);
		$nbt->setInt(self::TAG_BREEDING_COOLDOWN, $this->breedingCooldownTicks);
		return $nbt;
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);
		$properties->setGenericFlag(EntityMetadataFlags::BABY, $this->isBaby());
	}

	public function isBaby() : bool{
		return $this->age < 0;
	}

	public function setBaby(bool $baby) : void{
		$wasBaby = $this->isBaby();
		$this->age = $baby ? -24000 : 0;
		if($wasBaby !== $baby){
			$this->setScale($baby ? 0.3 : 1.0);
			$this->networkPropertiesDirty = true;
		}
	}

	public function getAge() : int{
		return $this->age;
	}

	public function setAge(int $age) : void{
		$wasBaby = $this->isBaby();
		$this->age = $age;
		if($wasBaby && $this->age >= 0){
			$this->growUp();
		}elseif($wasBaby !== $this->isBaby()){
			$this->setScale($this->isBaby() ? 0.3 : 1.0);
			$this->networkPropertiesDirty = true;
		}
	}

	public function getHomePos() : ?Vector3{
		return $this->homePos;
	}

	public function setHomePos(Vector3 $pos) : void{
		$this->homePos = clone $pos;
	}

	public function hasEgg() : bool{
		return $this->hasEgg;
	}

	public function setHasEgg(bool $hasEgg) : void{
		$this->hasEgg = $hasEgg;
	}

	public function canBreatheInWater() : bool{
		return true; // Turtles are amphibious, infinite air in water and land
	}

	public function isBreedingItem(Item $item) : bool{
		return $item->getTypeId() === ItemTypeIds::DRIED_KELP || $item->getName() === "Seagrass";
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

	public function growUp() : void{
		$this->setScale(1.0);
		$this->networkPropertiesDirty = true;

		// Baby turtle drops a Scute when growing into an adult!
		$this->getWorld()->dropItem($this->location, VanillaItems::SCUTE());
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
				if($this->age >= 0){
					$this->growUp();
				}
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

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		$hasUpdate = parent::entityBaseTick($tickDiff);

		if($this->age < 0){
			$this->age += $tickDiff;
			if($this->age >= 0){
				$this->growUp();
			}
			$hasUpdate = true;
		}

		if($this->inLoveTicks > 0){
			$this->inLoveTicks = max(0, $this->inLoveTicks - $tickDiff);
			if($this->inLoveTicks % 20 === 0 && $this->inLoveTicks > 0){
				$this->getWorld()->addParticle($this->location->add(0, 0.6, 0), new HeartParticle());
			}

			if(!$this->isBaby() && $this->inLoveTicks > 0){
				foreach($this->getWorld()->getNearbyEntities($this->boundingBox->expandedCopy(8, 8, 8), $this) as $nearby){
					if($nearby instanceof SeaTurtle && !$nearby->isBaby() && $nearby->isInLove() && $nearby->getId() !== $this->getId()){
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

	public function breedWith(SeaTurtle $partner) : void{
		$this->inLoveTicks = 0;
		$partner->inLoveTicks = 0;
		$this->breedingCooldownTicks = self::BREEDING_COOLDOWN_TICKS;
		$partner->breedingCooldownTicks = self::BREEDING_COOLDOWN_TICKS;

		// One of the parents gains an egg to carry back to its home beach
		$this->hasEgg = true;

		$this->getWorld()->addParticle($this->location->add(0, 0.6, 0), new HeartParticle());
		$this->getWorld()->addParticle($partner->location->add(0, 0.6, 0), new HeartParticle());
	}
}
