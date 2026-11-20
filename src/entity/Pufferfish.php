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
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\VanillaItems;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\player\Player;
use function max;
use function min;

class Pufferfish extends WaterAnimal{

	public const PUFF_DEFLATED = 0;
	public const PUFF_HALF = 1;
	public const PUFF_FULL = 2;

	public const TAG_PUFF_STATE = "PuffState";

	protected int $puffState = self::PUFF_DEFLATED;
	protected int $deflateCooldownTicks = 0;

	public static function getNetworkTypeId() : string{
		return EntityIds::PUFFERFISH;
	}

	public function getName() : string{
		return "Pufferfish";
	}

	public function getDefaultMaxHealth() : int{
		return 3;
	}

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(0.35, 0.35);
	}

	protected function initEntity(CompoundTag $nbt) : void{
		$this->setMaxHealth(3);
		parent::initEntity($nbt);
		if($nbt->getTag("Health") === null){
			$this->setHealth(3.0);
		}

		$this->puffState = $nbt->getInt(self::TAG_PUFF_STATE, self::PUFF_DEFLATED);
		if($this->puffState < 0 || $this->puffState > 2){
			$this->puffState = self::PUFF_DEFLATED;
		}
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT();
		$nbt->setInt(self::TAG_PUFF_STATE, $this->puffState);
		return $nbt;
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);
		$properties->setByte(EntityMetadataProperties::PUFFERFISH_SIZE, $this->puffState);
	}

	public function getPuffState() : int{
		return $this->puffState;
	}

	public function setPuffState(int $state) : void{
		$this->puffState = max(0, min(2, $state));
		$this->networkPropertiesDirty = true;
	}

	public function sting(Living $target) : void{
		$ev = new EntityDamageByEntityEvent($this, $target, EntityDamageEvent::CAUSE_ENTITY_ATTACK, 2.0);
		$target->attack($ev);

		if(!$ev->isCancelled()){
			$target->getEffects()->add(new EffectInstance(VanillaEffects::POISON(), 140, 1));
		}
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		$hasUpdate = parent::entityBaseTick($tickDiff);

		$closestDistSq = 999.0;
		$closestEntity = null;

		foreach($this->getWorld()->getNearbyEntities($this->boundingBox->expandedCopy(5, 5, 5), $this) as $nearby){
			if($nearby instanceof Player && $nearby->isAlive() && !$nearby->isCreative() && !$nearby->isSpectator()){
				$distSq = $this->location->distanceSquared($nearby->getLocation());
				if($distSq < $closestDistSq){
					$closestDistSq = $distSq;
					$closestEntity = $nearby;
				}
			}
		}

		if($closestEntity !== null){
			if($closestDistSq <= 9.0){ // within 3 blocks
				if($this->puffState !== self::PUFF_FULL){
					$this->setPuffState(self::PUFF_FULL);
				}
				// Sting if within 1.5 blocks (2.25 distSq)
				if($closestDistSq <= 2.25){
					$this->sting($closestEntity);
				}
			}elseif($closestDistSq <= 25.0){ // between 3 and 5 blocks
				if($this->puffState !== self::PUFF_HALF){
					$this->setPuffState(self::PUFF_HALF);
				}
			}
			$this->deflateCooldownTicks = 60; // 3 seconds before deflating
		}else{
			if($this->deflateCooldownTicks > 0){
				$this->deflateCooldownTicks -= $tickDiff;
			}elseif($this->puffState !== self::PUFF_DEFLATED){
				$this->setPuffState(self::PUFF_DEFLATED);
			}
		}

		return $hasUpdate;
	}

	public function getDrops() : array{
		return [VanillaItems::PUFFERFISH()];
	}
}
