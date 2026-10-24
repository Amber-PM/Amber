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
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\player\Player;
use function mt_rand;

class Guardian extends WaterAnimal{

	public const TAG_SPIKES_EXTENDED = "SpikesExtended";
	public const LASER_CHARGE_TICKS = 60; // 3 seconds to charge beam

	protected bool $spikesExtended = true;
	protected ?Living $targetEntity = null;
	protected int $laserChargeTicks = 0;

	public static function getNetworkTypeId() : string{
		return EntityIds::GUARDIAN;
	}

	public function getName() : string{
		return "Guardian";
	}

	public function getDefaultMaxHealth() : int{
		return 30;
	}

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(0.85, 0.85);
	}

	protected function initEntity(CompoundTag $nbt) : void{
		$this->setMaxHealth($this->getDefaultMaxHealth());
		parent::initEntity($nbt);
		if($nbt->getTag("Health") === null){
			$this->setHealth((float) $this->getDefaultMaxHealth());
		}

		$this->spikesExtended = $nbt->getByte(self::TAG_SPIKES_EXTENDED, 1) !== 0;
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT();
		$nbt->setByte(self::TAG_SPIKES_EXTENDED, $this->spikesExtended ? 1 : 0);
		return $nbt;
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);
		$properties->setLong(
			EntityMetadataProperties::TARGET_EID,
			$this->targetEntity !== null ? $this->targetEntity->getId() : 0
		);
	}

	public function areSpikesExtended() : bool{
		return $this->spikesExtended;
	}

	public function setSpikesExtended(bool $extended) : void{
		$this->spikesExtended = $extended;
	}

	public function getLaserTarget() : ?Living{
		return $this->targetEntity;
	}

	public function setLaserTarget(?Living $target) : void{
		$this->targetEntity = $target;
		$this->laserChargeTicks = 0;
		$this->networkPropertiesDirty = true;
	}

	public function getLaserChargeTicks() : int{
		return $this->laserChargeTicks;
	}

	public function getLaserDamage() : float{
		return 5.0;
	}

	public function attack(EntityDamageEvent $source) : void{
		parent::attack($source);

		// Thorns defense when spikes are extended
		if(!$source->isCancelled() && $this->spikesExtended && $source instanceof EntityDamageByEntityEvent){
			$attacker = $source->getDamager();
			if($attacker instanceof Living && $attacker->isAlive()){
				$thornsEv = new EntityDamageByEntityEvent($this, $attacker, EntityDamageEvent::CAUSE_MAGIC, 2.0);
				$attacker->attack($thornsEv);
			}
		}
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		$hasUpdate = parent::entityBaseTick($tickDiff);

		if(!$this->isAlive()){
			return $hasUpdate;
		}

		// Laser attack targeting
		if($this->targetEntity !== null){
			if(!$this->targetEntity->isAlive() || $this->targetEntity->isClosed() || $this->location->distanceSquared($this->targetEntity->getLocation()) > 256.0){
				$this->setLaserTarget(null);
			}else{
				$this->laserChargeTicks += $tickDiff;
				$hasUpdate = true;
				if($this->laserChargeTicks >= self::LASER_CHARGE_TICKS){
					// Fire laser!
					$damage = $this->getLaserDamage();
					$ev = new EntityDamageByEntityEvent($this, $this->targetEntity, EntityDamageEvent::CAUSE_MAGIC, $damage);
					$this->targetEntity->attack($ev);
					$this->setLaserTarget(null);
				}
			}
		}else{
			// Search for nearby target
			foreach($this->getWorld()->getNearbyEntities($this->boundingBox->expandedCopy(16, 16, 16), $this) as $nearby){
				if($nearby instanceof Player && $nearby->isAlive() && !$nearby->isCreative() && !$nearby->isSpectator()){
					$this->setLaserTarget($nearby);
					break;
				}
			}
		}

		return $hasUpdate;
	}

	public function getDrops() : array{
		return [
			VanillaItems::PRISMARINE_SHARD()->setCount(mt_rand(0, 2)),
			VanillaItems::RAW_FISH()->setCount(mt_rand(0, 1))
		];
	}

	public function getXpDropAmount() : int{
		return 10;
	}
}
