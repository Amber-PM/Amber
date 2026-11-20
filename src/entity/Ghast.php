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

use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\player\Player;
use pocketmine\world\sound\GhastShootSound;
use pocketmine\world\sound\GhastSound;
use function mt_rand;

class Ghast extends Living{

	public const TAG_CHARGING = "Charging";

	protected bool $charging = false;
	protected int $chargeTicks = 0;
	protected int $shootCooldown = 0;

	public static function getNetworkTypeId() : string{
		return EntityIds::GHAST;
	}

	public function getName() : string{
		return "Ghast";
	}

	public function getDefaultMaxHealth() : int{
		return 10;
	}

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(4.0, 4.02);
	}

	protected function getInitialGravity() : float{
		return 0.0; // Ghasts float and fly!
	}

	protected function initEntity(CompoundTag $nbt) : void{
		$this->setMaxHealth(10);
		parent::initEntity($nbt);
		if($nbt->getTag("Health") === null){
			$this->setHealth(10.0);
		}

		$this->charging = $nbt->getByte(self::TAG_CHARGING, 0) !== 0;
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT();
		$nbt->setByte(self::TAG_CHARGING, $this->charging ? 1 : 0);
		return $nbt;
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);
		$properties->setGenericFlag(EntityMetadataFlags::CHARGED, $this->charging);
	}

	public function isChargingFireball() : bool{
		return $this->charging;
	}

	public function setChargingFireball(bool $charging = true) : void{
		$this->charging = $charging;
		$this->chargeTicks = $charging ? 20 : 0;
		$this->networkPropertiesDirty = true;
		if($charging){
			$this->broadcastSound(new GhastShootSound());
		}
	}

	public function attack(EntityDamageEvent $source) : void{
		// Fire, lava, and fall immunity
		if(
			$source->getCause() === EntityDamageEvent::CAUSE_FIRE ||
			$source->getCause() === EntityDamageEvent::CAUSE_FIRE_TICK ||
			$source->getCause() === EntityDamageEvent::CAUSE_LAVA ||
			$source->getCause() === EntityDamageEvent::CAUSE_FALL
		){
			$source->cancel();
			return;
		}

		parent::attack($source);
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		$hasUpdate = parent::entityBaseTick($tickDiff);

		if(!$this->isAlive()){
			return $hasUpdate;
		}

		if($this->charging){
			$this->chargeTicks -= $tickDiff;
			$hasUpdate = true;
			if($this->chargeTicks <= 0){
				$this->shootFireball();
			}
		}elseif($this->shootCooldown > 0){
			$this->shootCooldown -= $tickDiff;
		}else{
			// Search for target player within 64 blocks
			foreach($this->getWorld()->getNearbyEntities($this->boundingBox->expandedCopy(64, 64, 64), $this) as $nearby){
				if($nearby instanceof Player && $nearby->isAlive() && !$nearby->isCreative() && !$nearby->isSpectator()){
					$this->setChargingFireball(true);
					break;
				}
			}
		}

		return $hasUpdate;
	}

	public function shootFireball() : void{
		$this->charging = false;
		$this->shootCooldown = 60; // 3 seconds between shots
		$this->networkPropertiesDirty = true;
		$this->broadcastSound(new GhastSound());
	}

	public function getDrops() : array{
		return [
			VanillaItems::GUNPOWDER()->setCount(mt_rand(0, 2)),
			VanillaItems::GHAST_TEAR()->setCount(mt_rand(0, 1))
		];
	}

	public function getXpDropAmount() : int{
		return 5;
	}
}
