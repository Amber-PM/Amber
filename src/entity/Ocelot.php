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

use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityRegainHealthEvent;
use pocketmine\item\Item;
use pocketmine\item\ItemTypeIds;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\player\Player;
use pocketmine\world\particle\HappyVillagerParticle;
use pocketmine\world\particle\HeartParticle;
use pocketmine\world\particle\SmokeParticle;
use function max;
use function min;
use function mt_rand;
use function sqrt;
use function strtolower;

class Ocelot extends Living implements Ageable{

	public const TAG_TRUSTING = "Trusting";
	public const TAG_AGE = "Age";
	public const TAG_IN_LOVE = "InLove";
	public const TAG_BREEDING_COOLDOWN = "BreedingCooldown";
	public const BREEDING_COOLDOWN_TICKS = 6000;

	protected bool $trusting = false;
	protected int $age = 0;
	protected int $inLoveTicks = 0;
	protected int $breedingCooldownTicks = 0;
	protected int $creeperRepelTicks = 0;
	protected int $attackCooldownTicks = 0;
	protected ?Entity $attackTarget = null;

	protected function initEntity(CompoundTag $nbt) : void{
		parent::initEntity($nbt);
		$this->readSaveData($nbt);
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT();
		$this->writeSaveData($nbt);
		return $nbt;
	}

	public static function getNetworkTypeId() : string{
		return EntityIds::OCELOT;
	}

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(0.7, 0.6);
	}

	public function getName() : string{
		return "Ocelot";
	}

	protected function addAttributes() : void{
		parent::addAttributes();
		$this->setMaxHealth(10);
		$this->setHealth(10.0);
	}

	public function calculateFallDamage(float $fallDistance) : float{
		return 0.0;
	}

	public function isTrusting() : bool{
		return $this->trusting;
	}

	public function setTrusting(bool $trusting = true) : void{
		$this->trusting = $trusting;
		$this->networkPropertiesDirty = true;
		if(isset($this->networkProperties)){
			$this->networkProperties->setGenericFlag(EntityMetadataFlags::TRUSTING, $trusting);
		}
	}

	public function getAge() : int{
		return $this->age;
	}

	public function setAge(int $age) : void{
		$this->age = $age;
		$scale = $age < 0 ? 0.5 : 1.0;
		if($this->getScale() !== $scale){
			$this->setScale($scale);
		}
		if($age <= 0){
			$this->breedingCooldownTicks = 0;
		}else{
			$this->breedingCooldownTicks = $age;
		}
		$this->networkPropertiesDirty = true;
		if(isset($this->networkProperties)){
			$this->networkProperties->setGenericFlag(EntityMetadataFlags::BABY, $this->isBaby());
		}
	}

	public function isBaby() : bool{
		return $this->age < 0;
	}

	public function getBreedingCooldownTicks() : int{
		return $this->breedingCooldownTicks;
	}

	public function setBreedingCooldownTicks(int $ticks) : void{
		$this->breedingCooldownTicks = max(0, $ticks);
		if(!$this->isBaby()){
			$this->age = $this->breedingCooldownTicks;
		}
	}

	public function canBreed() : bool{
		return !$this->isBaby() && $this->isTrusting() && $this->breedingCooldownTicks <= 0 && $this->age <= 0;
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

	public static function isFish(Item $item) : bool{
		$id = $item->getTypeId();
		return $id === ItemTypeIds::RAW_FISH || $id === ItemTypeIds::RAW_SALMON;
	}

	protected function consumeHeldItem(Player $player, Item $held) : void{
		if($player->hasFiniteResources()){
			$held->pop();
			$player->getInventory()->setItemInHand($held);
		}
	}

	public function feedBaby() : bool{
		if(!$this->isBaby()){
			return false;
		}
		$newAge = $this->age + 2400;
		if($newAge >= 0){
			$newAge = 0;
		}
		$this->setAge($newAge);
		if(isset($this->location)){
			$world = $this->getWorld();
			if($world->isLoaded()){
				$world->addParticle($this->location->add(0, 0.5, 0), new HappyVillagerParticle());
			}
		}
		return true;
	}

	public function onInteract(Player $player, Vector3 $clickPos) : bool{
		$item = $player->getInventory()->getItemInHand();

		if(!self::isFish($item)){
			return false;
		}

		if($this->isBaby()){
			if($this->feedBaby()){
				$this->consumeHeldItem($player, $item);
				return true;
			}
			return false;
		}

		if(!$this->isTrusting()){
			$this->consumeHeldItem($player, $item);
			if(mt_rand(1, 3) === 1){
				$this->setTrusting(true);
				if(isset($this->location)){
					$this->getWorld()->addParticle($this->location->add(0, 0.5, 0), new HeartParticle());
				}
			}else{
				if(isset($this->location)){
					$this->getWorld()->addParticle($this->location->add(0, 0.5, 0), new SmokeParticle());
				}
			}
			return true;
		}

		// Trusting adult ocelot
		if($this->getHealth() < $this->getMaxHealth()){
			$healAmount = 2.0;
			$actualHeal = min((float) $this->getMaxHealth() - $this->getHealth(), $healAmount);
			$ev = new EntityRegainHealthEvent($this, $actualHeal, EntityRegainHealthEvent::CAUSE_EATING);
			$ev->call();
			if($ev->isCancelled()){
				return false;
			}
			$this->setHealth($this->getHealth() + $ev->getAmount());
			$this->consumeHeldItem($player, $item);
			if(isset($this->location)){
				$this->getWorld()->addParticle($this->location->add(0, 0.5, 0), new HeartParticle());
			}
			return true;
		}

		if($this->canBreed() && !$this->isInLove()){
			$this->setInLoveTicks(600);
			$this->consumeHeldItem($player, $item);
			if(isset($this->location)){
				$this->getWorld()->addParticle($this->location->add(0, 0.5, 0), new HeartParticle());
			}
			return true;
		}

		return false;
	}

	public function isRepelledMob(Entity $entity) : bool{
		if($entity->isClosed() || !$entity->isAlive()){
			return false;
		}
		$typeId = $entity::getNetworkTypeId();
		return $typeId === EntityIds::CREEPER
			|| $typeId === "minecraft:creeper"
			|| $typeId === EntityIds::PHANTOM
			|| $typeId === "minecraft:phantom"
			|| ($entity instanceof Living && (strtolower($entity->getName()) === "creeper" || strtolower($entity->getName()) === "phantom"));
	}

	public function repelCreepersAndPhantoms() : void{
		if(!isset($this->location) || !isset($this->boundingBox)){
			return;
		}
		$world = $this->getWorld();
		if(!$world->isLoaded()){
			return;
		}

		$radius = 10.0;
		$bb = $this->boundingBox->expandedCopy($radius, $radius, $radius);
		foreach($world->getNearbyEntities($bb, $this) as $nearby){
			if($this->isRepelledMob($nearby) && isset($nearby->location)){
				$distSq = $this->location->distanceSquared($nearby->location);
				if($distSq <= $radius * $radius){
					$dx = $nearby->location->x - $this->location->x;
					$dz = $nearby->location->z - $this->location->z;
					$hDist = sqrt($dx * $dx + $dz * $dz);
					if($hDist > 0.0001){
						$dx /= $hDist;
						$dz /= $hDist;
					}else{
						$dx = 1.0;
						$dz = 0.0;
					}
					$currentMotion = $nearby->getMotion();
					$nearby->setMotion(new Vector3($dx * 0.3, $currentMotion->y, $dz * 0.3));
				}
			}
		}
	}

	public function isPrey(Entity $entity) : bool{
		if($entity->isClosed() || !$entity->isAlive() || $entity === $this){
			return false;
		}
		$typeId = $entity::getNetworkTypeId();
		return $typeId === EntityIds::CHICKEN
			|| $typeId === "minecraft:chicken"
			|| ($entity instanceof Living && strtolower($entity->getName()) === "chicken");
	}

	public function findEligibleMate() : ?self{
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
				$nearby instanceof self &&
				$nearby !== $this &&
				$nearby->isAlive() &&
				!$nearby->isClosed() &&
				$nearby->isTrusting() &&
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

	public function breed(self $partner) : ?self{
		if(!isset($this->location) || !isset($partner->location)){
			return null;
		}

		$midX = ($this->location->x + $partner->location->x) / 2.0;
		$midY = ($this->location->y + $partner->location->y) / 2.0;
		$midZ = ($this->location->z + $partner->location->z) / 2.0;
		$midLocation = new Location($midX, $midY, $midZ, $this->getWorld(), 0.0, 0.0);

		$baby = new self($midLocation);
		$baby->setAge(-24000);
		$baby->setTrusting(true);
		$baby->spawnToAll();

		$this->setInLoveTicks(0);
		$partner->setInLoveTicks(0);
		$this->setBreedingCooldownTicks(self::BREEDING_COOLDOWN_TICKS);
		$partner->setBreedingCooldownTicks(self::BREEDING_COOLDOWN_TICKS);

		if(isset($this->location)){
			$world = $this->getWorld();
			if($world->isLoaded()){
				$world->addParticle($midLocation->add(0, 0.5, 0), new HeartParticle());
			}
		}

		return $baby;
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		$hasUpdate = parent::entityBaseTick($tickDiff);

		if(!$this->isAlive() || $this->closed){
			return $hasUpdate;
		}

		if($this->attackCooldownTicks > 0){
			$this->attackCooldownTicks = max(0, $this->attackCooldownTicks - $tickDiff);
		}

		if($this->age < 0){
			$newAge = $this->age + $tickDiff;
			if($newAge >= 0){
				$newAge = 0;
			}
			$this->setAge($newAge);
			$hasUpdate = true;
		}elseif($this->age > 0){
			$this->setAge(max(0, $this->age - $tickDiff));
			$hasUpdate = true;
		}elseif($this->breedingCooldownTicks > 0){
			$this->setBreedingCooldownTicks(max(0, $this->breedingCooldownTicks - $tickDiff));
			$hasUpdate = true;
		}

		$this->creeperRepelTicks += $tickDiff;
		if($this->creeperRepelTicks >= 10){
			$this->creeperRepelTicks %= 10;
			$this->repelCreepersAndPhantoms();
		}

		if($this->inLoveTicks > 0){
			$this->inLoveTicks = max(0, $this->inLoveTicks - $tickDiff);
			if($this->inLoveTicks % 20 === 0 && isset($this->location)){
				$this->getWorld()->addParticle($this->location->add(0, 0.5, 0), new HeartParticle());
			}

			$mate = $this->canBreed() ? $this->findEligibleMate() : null;
			if($mate !== null && isset($this->location) && isset($mate->location)){
				$dist = $this->location->distance($mate->location);
				if($dist <= 1.5){
					$this->breed($mate);
				}else{
					$dx = $mate->location->x - $this->location->x;
					$dz = $mate->location->z - $this->location->z;
					$hDist = sqrt($dx * $dx + $dz * $dz);
					if($hDist > 0.0001){
						$dx /= $hDist;
						$dz /= $hDist;
						$this->motion->x = $dx * 0.25;
						$this->motion->z = $dz * 0.25;
						$this->lookAt($mate->location);
						if($this->isCollidedHorizontally && $this->onGround){
							$this->motion->y = 0.42;
						}
					}
				}
				$hasUpdate = true;
			}
		}

		return $hasUpdate;
	}

	public function getDrops() : array{
		return [];
	}

	public function getXpDropAmount() : int{
		return mt_rand(1, 3);
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);

		$properties->setGenericFlag(EntityMetadataFlags::TRUSTING, $this->trusting);
		$properties->setGenericFlag(EntityMetadataFlags::BABY, $this->isBaby());
	}

	protected function writeSaveData(CompoundTag $nbt) : void{
		$nbt->setByte(self::TAG_TRUSTING, $this->trusting ? 1 : 0);
		$nbt->setInt(self::TAG_AGE, $this->age);
		$nbt->setInt(self::TAG_IN_LOVE, $this->inLoveTicks);
		$nbt->setInt(self::TAG_BREEDING_COOLDOWN, $this->breedingCooldownTicks);
	}

	protected function readSaveData(CompoundTag $nbt) : void{
		$trustingTag = $nbt->getTag(self::TAG_TRUSTING);
		$this->setTrusting($trustingTag instanceof ByteTag ? $trustingTag->getValue() !== 0 : false);

		$this->setAge($nbt->getInt(self::TAG_AGE, 0));
		$this->setInLoveTicks($nbt->getInt(self::TAG_IN_LOVE, 0));
		$this->setBreedingCooldownTicks($nbt->getInt(self::TAG_BREEDING_COOLDOWN, max(0, $this->age)));
	}
}
