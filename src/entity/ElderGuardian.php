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

use pocketmine\block\VanillaBlocks;
use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\player\Player;
use function mt_rand;

class ElderGuardian extends Guardian{

	public const CURSE_INTERVAL_TICKS = 1200; // 60 seconds

	protected int $curseCooldown = 0;

	public static function getNetworkTypeId() : string{
		return EntityIds::ELDER_GUARDIAN;
	}

	public function getName() : string{
		return "Elder Guardian";
	}

	public function getDefaultMaxHealth() : int{
		return 80;
	}

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(1.9975, 1.9975);
	}

	protected function initEntity(CompoundTag $nbt) : void{
		parent::initEntity($nbt);
		$this->setMaxHealth(80);
		if($nbt->getTag("Health") === null){
			$this->setHealth(80.0);
		}
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);
		$properties->setGenericFlag(EntityMetadataFlags::ELDER, true);
	}

	public function getLaserDamage() : float{
		return 8.0;
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		$hasUpdate = parent::entityBaseTick($tickDiff);

		if(!$this->isAlive()){
			return $hasUpdate;
		}

		// Mining fatigue ghost curse to players within 50 blocks
		if($this->curseCooldown > 0){
			$this->curseCooldown -= $tickDiff;
		}else{
			$this->curseCooldown = self::CURSE_INTERVAL_TICKS;
			foreach($this->getWorld()->getNearbyEntities($this->boundingBox->expandedCopy(50, 50, 50), $this) as $nearby){
				if($nearby instanceof Player && $nearby->isAlive() && !$nearby->isCreative() && !$nearby->isSpectator()){
					$effects = $nearby->getEffects();
					$fatigue = $effects->get(VanillaEffects::MINING_FATIGUE());
					if($fatigue === null || $fatigue->getAmplifier() < 2){
						// Mining Fatigue III (amplifier 2) for 6000 ticks (5 minutes)
						$effects->add(new EffectInstance(VanillaEffects::MINING_FATIGUE(), 6000, 2));
					}
				}
			}
		}

		return $hasUpdate;
	}

	public function getDrops() : array{
		return [
			VanillaItems::PRISMARINE_SHARD()->setCount(mt_rand(0, 2)),
			VanillaBlocks::SPONGE()->setWet(true)->asItem(),
			VanillaItems::RAW_FISH()->setCount(mt_rand(0, 1))
		];
	}

	public function getXpDropAmount() : int{
		return 20;
	}
}
