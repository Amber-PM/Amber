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
use pocketmine\item\VanillaItems;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\player\Player;
use function max;
use function mt_rand;

class PolarBear extends Living implements Ageable{

	public const TAG_AGE = "Age";
	public const TAG_ANGER_TICKS = "AngerTicks";

	protected int $age = 0;
	protected int $angerTicks = 0;
	protected ?int $targetEntityId = null;

	public static function getNetworkTypeId() : string{
		return EntityIds::POLAR_BEAR;
	}

	public function getName() : string{
		return "Polar Bear";
	}

	public function getDefaultMaxHealth() : int{
		return 30;
	}

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return $this->isBaby() ? new EntitySizeInfo(0.7, 0.7) : new EntitySizeInfo(1.4, 1.4);
	}

	protected function initEntity(CompoundTag $nbt) : void{
		$this->setMaxHealth(30);
		parent::initEntity($nbt);
		if($nbt->getTag("Health") === null){
			$this->setHealth(30.0);
		}

		$this->age = $nbt->getInt(self::TAG_AGE, 0);
		$this->angerTicks = $nbt->getInt(self::TAG_ANGER_TICKS, 0);
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT();
		$nbt->setInt(self::TAG_AGE, $this->age);
		$nbt->setInt(self::TAG_ANGER_TICKS, $this->angerTicks);
		return $nbt;
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);
		$isAngry = $this->isAngry();
		$properties->setGenericFlag(EntityMetadataFlags::ANGRY, $isAngry);
		$properties->setGenericFlag(EntityMetadataFlags::REARING, $isAngry); // Standing on hind legs
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

	public function isAngry() : bool{
		return $this->angerTicks > 0 && !$this->isBaby();
	}

	public function setAngry(int $ticks = 500) : void{
		if($this->isBaby()){
			return;
		}
		$wasAngry = $this->isAngry();
		$this->angerTicks = $ticks;
		if(!$wasAngry){
			$this->networkPropertiesDirty = true;
		}
	}

	public function calmDown() : void{
		$wasAngry = $this->isAngry();
		$this->angerTicks = 0;
		$this->targetEntityId = null;
		if($wasAngry){
			$this->networkPropertiesDirty = true;
		}
	}

	public function getTargetEntityId() : ?int{
		return $this->targetEntityId;
	}

	public function setTargetEntityId(?int $id) : void{
		$this->targetEntityId = $id;
	}

	public function attack(EntityDamageEvent $source) : void{
		parent::attack($source);

		if($source->isCancelled()){
			return;
		}

		if($source instanceof EntityDamageByEntityEvent){
			$damager = $source->getDamager();
			if($damager !== null){
				if($this->isBaby()){
					// Alert nearby adult polar bears within 41 blocks
					foreach($this->getWorld()->getNearbyEntities($this->boundingBox->expandedCopy(41, 20, 41), $this) as $nearby){
						if($nearby instanceof PolarBear && !$nearby->isBaby()){
							$nearby->setAngry(500);
							$nearby->setTargetEntityId($damager->getId());
						}
					}
				}else{
					$this->setAngry(500);
					$this->setTargetEntityId($damager->getId());
				}
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

		if($this->angerTicks > 0){
			$this->angerTicks = max(0, $this->angerTicks - $tickDiff);
			if($this->angerTicks === 0){
				$this->targetEntityId = null;
				$this->networkPropertiesDirty = true;
			}
			$hasUpdate = true;
		}

		// Check if baby is nearby to turn aggressive toward players
		if(!$this->isBaby() && !$this->isAngry()){
			$hasNearbyBaby = false;
			foreach($this->getWorld()->getNearbyEntities($this->boundingBox->expandedCopy(16, 8, 16), $this) as $nearby){
				if($nearby instanceof PolarBear && $nearby->isBaby()){
					$hasNearbyBaby = true;
					break;
				}
			}

			if($hasNearbyBaby){
				foreach($this->getWorld()->getNearbyEntities($this->boundingBox->expandedCopy(16, 8, 16), $this) as $nearby){
					if($nearby instanceof Player && $nearby->isAlive() && !$nearby->isSpectator() && !$nearby->isCreative()){
						$this->setAngry(400);
						$this->setTargetEntityId($nearby->getId());
						break;
					}
				}
			}
		}

		return $hasUpdate;
	}

	public function getDrops() : array{
		$drops = [];
		if(!$this->isBaby()){
			$fishCount = mt_rand(0, 2);
			if($fishCount > 0){
				$drops[] = VanillaItems::RAW_FISH()->setCount($fishCount);
			}
			$salmonCount = mt_rand(0, 2);
			if($salmonCount > 0){
				$drops[] = VanillaItems::RAW_SALMON()->setCount($salmonCount);
			}
		}
		return $drops;
	}
}
