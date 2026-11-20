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

use pocketmine\entity\object\ExperienceOrb;
use pocketmine\item\Item;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\player\Player;
use pocketmine\world\particle\HappyVillagerParticle;
use pocketmine\world\particle\HeartParticle;
use function atan2;
use function cos;
use function max;
use function min;
use function mt_rand;
use function sin;
use const M_PI;

/**
 * @phpstan-consistent-constructor
 */
abstract class Animal extends Living implements Ageable{

	public const TAG_AGE = "Age";
	public const TAG_IN_LOVE = "InLove";
	public const TAG_BREEDING_COOLDOWN = "BreedingCooldown";

	public const BABY_DURATION_TICKS = 24000;
	public const BREEDING_COOLDOWN_TICKS = 6000;
	public const IN_LOVE_TICKS = 600;

	protected int $age = 0;
	protected int $inLoveTicks = 0;
	protected int $breedingCooldownTicks = 0;

	protected function initEntity(CompoundTag $nbt) : void{
		parent::initEntity($nbt);
		if(($ageTag = $nbt->getTag(self::TAG_AGE)) instanceof IntTag){
			$this->setAge($ageTag->getValue());
		}
		if(($inLoveTag = $nbt->getTag(self::TAG_IN_LOVE)) instanceof IntTag){
			$this->setInLoveTicks($inLoveTag->getValue());
		}
		if(($cooldownTag = $nbt->getTag(self::TAG_BREEDING_COOLDOWN)) instanceof IntTag){
			$this->setBreedingCooldownTicks($cooldownTag->getValue());
		}
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT();
		$nbt->setInt(self::TAG_AGE, $this->age);
		$nbt->setInt(self::TAG_IN_LOVE, $this->inLoveTicks);
		$nbt->setInt(self::TAG_BREEDING_COOLDOWN, $this->breedingCooldownTicks);
		return $nbt;
	}

	public function isBaby() : bool{
		return $this->age < 0;
	}

	public function setBaby(bool $baby = true) : void{
		$this->setAge($baby ? -self::BABY_DURATION_TICKS : 0);
	}

	public function getAge() : int{
		return $this->age;
	}

	public function setAge(int $age) : void{
		$wasBaby = $this->isBaby();
		$this->age = $age;
		if($this->age > 0){
			$this->breedingCooldownTicks = $this->age;
		}
		if($wasBaby !== $this->isBaby()){
			$this->updateBabyState();
		}
	}

	protected function updateBabyState() : void{
		$this->networkPropertiesDirty = true;
		$initial = $this->getInitialSizeInfo();
		if($this->isBaby()){
			$this->setSize(new EntitySizeInfo($initial->getHeight() * 0.5, $initial->getWidth() * 0.5));
		}else{
			$this->setSize($initial);
		}
	}

	public function getInLoveTicks() : int{
		return $this->inLoveTicks;
	}

	public function setInLoveTicks(int $ticks) : void{
		$this->inLoveTicks = max(0, $ticks);
	}

	public function isInLove() : bool{
		return $this->inLoveTicks > 0;
	}

	public function setInLove(int $ticks = self::IN_LOVE_TICKS) : void{
		$this->setInLoveTicks($ticks);
	}

	public function getBreedingCooldown() : int{
		return $this->breedingCooldownTicks;
	}

	public function setBreedingCooldownTicks(int $ticks) : void{
		$this->breedingCooldownTicks = max(0, $ticks);
	}

	public function canBreed() : bool{
		return !$this->isBaby() && $this->breedingCooldownTicks <= 0 && $this->inLoveTicks > 0;
	}

	abstract public function isBreedingItem(Item $item) : bool;

	public function onInteract(Player $player, Vector3 $clickVector) : bool{
		$item = $player->getInventory()->getItemInHand();
		if($this->isBreedingItem($item)){
			if($this->isBaby()){
				// Accelerate growth by 10% (2400 ticks)
				$newAge = min(0, $this->age + 2400);
				$this->setAge($newAge);
				if($player->hasFiniteResources()){
					$item->pop();
					$player->getInventory()->setItemInHand($item);
				}
				$this->getWorld()->addParticle($this->location->add(0, $this->size->getHeight() * 0.5, 0), new HappyVillagerParticle());
				return true;
			}

			if($this->breedingCooldownTicks <= 0 && !$this->isInLove()){
				$this->setInLove();
				if($player->hasFiniteResources()){
					$item->pop();
					$player->getInventory()->setItemInHand($item);
				}
				$this->getWorld()->addParticle($this->location->add(0, $this->size->getHeight() + 0.5, 0), new HeartParticle());
				return true;
			}
		}

		return false;
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);
		$properties->setGenericFlag(EntityMetadataFlags::BABY, $this->isBaby());
		$properties->setFloat(EntityMetadataProperties::SCALE, $this->isBaby() ? 0.5 : 1.0);
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		$hasUpdate = parent::entityBaseTick($tickDiff);

		if(!$this->isAlive()){
			return $hasUpdate;
		}

		// Baby growth
		if($this->age < 0){
			$this->age += $tickDiff;
			if($this->age >= 0){
				$this->age = 0;
				$this->updateBabyState();
			}
		}

		// Breeding cooldown
		if($this->breedingCooldownTicks > 0){
			$this->breedingCooldownTicks = max(0, $this->breedingCooldownTicks - $tickDiff);
		}

		// Love mode
		if($this->inLoveTicks > 0){
			$this->inLoveTicks -= $tickDiff;
			if(($this->inLoveTicks % 20) === 0 && isset($this->location)){
				$this->getWorld()->addParticle($this->location->add(0, $this->size->getHeight() + 0.5, 0), new HeartParticle());
			}

			if($this->canBreed()){
				$partner = $this->findBreedingPartner();
				if($partner !== null){
					$distSq = $this->location->distanceSquared($partner->getLocation());
					if($distSq <= 4.0){
						$this->breed($partner);
					}else{
						// Move towards partner
						$pLoc = $partner->getLocation();
						$xDist = $pLoc->x - $this->location->x;
						$zDist = $pLoc->z - $this->location->z;
						$yaw = atan2($zDist, $xDist) * 180 / M_PI - 90;
						$this->location->yaw = $yaw;
						$speed = 0.15;
						$rad = $yaw * M_PI / 180;
						$this->setMotion(new Vector3(-sin($rad) * $speed, $this->motion->y, cos($rad) * $speed));
					}
				}
			}
		}

		return $hasUpdate;
	}

	protected function findBreedingPartner() : ?static{
		$closest = null;
		$closestDist = 64.0; // 8 blocks squared
		foreach($this->getWorld()->getNearbyEntities($this->boundingBox->expandedCopy(8, 4, 8)) as $entity){
			if($entity instanceof static && $entity !== $this && $entity->canBreed() && $entity->isAlive()){
				$dist = $this->location->distanceSquared($entity->getLocation());
				if($dist < $closestDist){
					$closestDist = $dist;
					$closest = $entity;
				}
			}
		}
		return $closest;
	}

	public function breed(self $partner) : ?self{
		if(!isset($this->location) || !isset($partner->location)){
			return null;
		}

		$midX = ($this->location->x + $partner->location->x) / 2.0;
		$midY = ($this->location->y + $partner->location->y) / 2.0;
		$midZ = ($this->location->z + $partner->location->z) / 2.0;
		$midLoc = new Location($midX, $midY, $midZ, $this->getWorld(), 0.0, 0.0);

		$baby = $this->createBabyEntity($midLoc);
		$baby->setBaby(true);
		$baby->spawnToAll();

		$this->setInLoveTicks(0);
		$partner->setInLoveTicks(0);
		$this->setBreedingCooldownTicks(self::BREEDING_COOLDOWN_TICKS);
		$partner->setBreedingCooldownTicks(self::BREEDING_COOLDOWN_TICKS);

		$world = $this->getWorld();
		$world->addParticle($midLoc->add(0, 0.5, 0), new HeartParticle());
		$world->dropExperience($midLoc, mt_rand(1, 7));

		return $baby;
	}

	protected function createBabyEntity(Location $location) : Animal{
		return new static($location);
	}

	public function getXpDropAmount() : int{
		return $this->isBaby() ? 0 : mt_rand(1, 3);
	}
}
