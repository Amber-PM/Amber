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

use pocketmine\entity\raid\Raider;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\player\Player;
use pocketmine\world\sound\EntityAttackSound;
use function atan2;
use function mt_rand;
use function sqrt;
use const M_PI;

class Vex extends Living{

	public static function getNetworkTypeId() : string{ return EntityIds::VEX; }

	private const TAG_DECAY_TIMER = "DecayTimer";

	protected bool $gravityEnabled = false;

	private int $decayTimer = -1;
	private int $decayTickCounter = 0;

	private bool $isCharging = false;
	private int $attackCooldown = 0;
	private ?Living $combatTarget = null;
	private float $movementSpeed = 0.8;
	private float $attackDamage = 3.0;

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(0.8, 0.4, 0.65);
	}

	public function getName() : string{
		return "Vex";
	}

	public function isFireProof() : bool{
		return true;
	}

	protected function initEntity(CompoundTag $nbt) : void{
		$this->setMaxHealth(14);
		parent::initEntity($nbt);

		$this->decayTimer = $nbt->getInt(self::TAG_DECAY_TIMER, -1);
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT();
		$nbt->setInt(self::TAG_DECAY_TIMER, $this->decayTimer);
		return $nbt;
	}

	public function getDecayTimer() : int{
		return $this->decayTimer;
	}

	public function setDecayTimer(int $ticks) : void{
		$this->decayTimer = $ticks;
	}

	public function isCharging() : bool{
		return $this->isCharging;
	}

	public function getCombatTarget() : ?Living{
		return $this->combatTarget;
	}

	public function setCombatTarget(?Living $target) : void{
		$this->combatTarget = $target;
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);
		$properties->setGenericFlag(EntityMetadataFlags::CHARGE_ATTACK, $this->isCharging);
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		$hasUpdate = parent::entityBaseTick($tickDiff);

		if(!$this->isAlive()){
			return $hasUpdate;
		}

		// Handle decay timer (takes 1 damage/s when expired)
		if($this->decayTimer > 0){
			$this->decayTimer -= $tickDiff;
		}elseif($this->decayTimer === 0){
			$this->decayTickCounter += $tickDiff;
			if($this->decayTickCounter >= 20){
				$this->decayTickCounter = 0;
				$this->attack(new EntityDamageEvent($this, EntityDamageEvent::CAUSE_MAGIC, 1.0));
			}
		}

		if($this->attackCooldown > 0){
			$this->attackCooldown -= $tickDiff;
		}

		// Update targeting
		if($this->combatTarget === null || !$this->combatTarget->isAlive() || $this->combatTarget->isClosed() || $this->combatTarget->getPosition()->distance($this->location) > 40){
			$this->combatTarget = $this->findTarget();
		}

		if($this->combatTarget !== null){
			$targetEye = $this->combatTarget->getLocation()->add(0, $this->combatTarget->getEyeHeight() * 0.7, 0);
			$dist = $this->location->distance($targetEye);

			// Look at target
			$xDist = $targetEye->x - $this->location->x;
			$yDist = $targetEye->y - ($this->location->y + $this->getEyeHeight());
			$zDist = $targetEye->z - $this->location->z;
			$yaw = atan2($zDist, $xDist) * 180 / M_PI - 90;
			$horizDist = sqrt($xDist * $xDist + $zDist * $zDist);
			$pitch = -atan2($yDist, $horizDist) * 180 / M_PI;
			$this->setRotation($yaw, $pitch);

			if($dist <= 1.6 && $this->attackCooldown <= 0){
				// Strike!
				$this->performAttack($this->combatTarget);
				$this->isCharging = false;
				$this->attackCooldown = 25; // 1.25s cooldown before recharge
				$this->networkPropertiesDirty = true;
				// Bounce back after strike
				$this->setMotion(new Vector3(-$xDist * 0.2, 0.3, -$zDist * 0.2));
			}elseif($this->attackCooldown <= 0){
				// Charge at target
				$this->isCharging = true;
				$this->networkPropertiesDirty = true;
				$dir = $targetEye->subtractVector($this->location)->normalize();
				$this->setMotion($dir->multiply($this->movementSpeed * 0.6));
			}else{
				// Circling/hovering near target while cooling down
				$this->isCharging = false;
				$this->networkPropertiesDirty = true;
				$hoverY = sin($this->ticksLived * 0.1) * 0.05;
				$this->setMotion(new Vector3(
					(mt_rand(-20, 20) / 100.0) * 0.2,
					$hoverY,
					(mt_rand(-20, 20) / 100.0) * 0.2
				));
			}
		}else{
			$this->isCharging = false;
			// Passive hovering
			$hoverY = sin($this->ticksLived * 0.05) * 0.03;
			$this->setMotion(new Vector3(0, $hoverY, 0));
		}

		return true;
	}

	private function performAttack(Living $target) : void{
		$ev = new EntityDamageByEntityEvent($this, $target, EntityDamageEvent::CAUSE_ENTITY_ATTACK, $this->attackDamage);
		$target->attack($ev);
		$this->getWorld()->addSound($this->location, new EntityAttackSound());
	}

	private function findTarget() : ?Living{
		$bestTarget = null;
		$bestDist = 32.0;

		$bb = $this->boundingBox->expandedCopy(32, 24, 32);
		foreach($this->getWorld()->getNearbyEntities($bb, $this) as $entity){
			if(!$entity instanceof Living || !$entity->isAlive() || $entity instanceof Raider || $entity instanceof Vex || $entity instanceof EvocationIllager){
				continue;
			}
			if($entity instanceof Player || ($entity instanceof Villager && !$entity->isBaby()) || $entity->getName() === "IronGolem" || $entity->getName() === "WanderingTrader"){
				$d = $this->location->distance($entity->getLocation());
				if($d < $bestDist){
					$bestDist = $d;
					$bestTarget = $entity;
				}
			}
		}

		return $bestTarget;
	}

	public function attack(EntityDamageEvent $source) : void{
		parent::attack($source);

		if($source instanceof EntityDamageByEntityEvent){
			$damager = $source->getDamager();
			if($damager instanceof Living && !$damager instanceof Raider && !$damager instanceof Vex && !$damager instanceof EvocationIllager){
				$this->combatTarget = $damager;
			}
		}
	}

	public function getDrops() : array{
		$drops = [];
		// Rare chance (8.5%) for iron sword
		if(mt_rand(1, 1000) <= 85){
			$drops[] = VanillaItems::IRON_SWORD();
		}
		return $drops;
	}

	public function getXpDropAmount() : int{
		return 5;
	}

	public function getPickedItem() : ?Item{
		return VanillaItems::IRON_SWORD();
	}
}
