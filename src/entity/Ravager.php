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
use pocketmine\item\ItemIdentifier;
use pocketmine\item\ItemTypeIds;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\LevelSoundEventPacket;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\network\mcpe\protocol\types\LevelSoundEvent;
use pocketmine\player\Player;
use pocketmine\world\raid\Raid;
use pocketmine\world\raid\RaiderDrops;
use pocketmine\world\sound\EntityAttackSound;
use function atan2;
use function count;
use function sqrt;
use const M_PI;

class Ravager extends Living implements Raider{

	public static function getNetworkTypeId() : string{ return EntityIds::RAVAGER; }

	private const TAG_STUN_TICKS = "StunTicks";
	private const TAG_WAVE = "Wave";

	private ?Raid $raid = null;
	private int $wave = 0;
	private bool $celebrating = false;

	private int $attackCooldown = 0;
	private int $stunTicks = 0;
	private ?Living $combatTarget = null;
	private float $movementSpeed = 0.4;
	private float $attackDamage = 12.0;

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(2.2, 1.95, 1.9);
	}

	public function getName() : string{
		return "Ravager";
	}

	protected function initEntity(CompoundTag $nbt) : void{
		$this->setMaxHealth(100);
		parent::initEntity($nbt);

		$this->stunTicks = $nbt->getInt(self::TAG_STUN_TICKS, 0);
		$this->wave = $nbt->getInt(self::TAG_WAVE, 0);
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT();
		$nbt->setInt(self::TAG_STUN_TICKS, $this->stunTicks);
		$nbt->setInt(self::TAG_WAVE, $this->wave);
		return $nbt;
	}

	public function isStunned() : bool{
		return $this->stunTicks > 0;
	}

	public function getStunTicks() : int{
		return $this->stunTicks;
	}

	public function setStunTicks(int $ticks) : void{
		$this->stunTicks = $ticks;
	}

	public function isRaidCaptain() : bool{
		return false;
	}

	public function setRaidCaptain(bool $isCaptain) : void{
		// Ravagers are mounts/heavy units, not captains
	}

	public function getRaid() : ?Raid{
		return $this->raid;
	}

	public function setRaid(?Raid $raid) : void{
		$this->raid = $raid;
	}

	public function getWave() : int{
		return $this->wave;
	}

	public function setWave(int $wave) : void{
		$this->wave = $wave;
	}

	public function isCelebrating() : bool{
		return $this->celebrating;
	}

	public function setCelebrating(bool $celebrating) : void{
		$this->celebrating = $celebrating;
		$this->networkPropertiesDirty = true;
	}

	public function hasRaidGoal() : bool{
		return $this->raid !== null && !$this->raid->isFinished();
	}

	public function getCombatTarget() : ?Living{
		return $this->combatTarget;
	}

	public function setCombatTarget(?Living $target) : void{
		$this->combatTarget = $target;
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);
		$properties->setGenericFlag(EntityMetadataFlags::CELEBRATING, $this->celebrating);
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		$hasUpdate = parent::entityBaseTick($tickDiff);

		if(!$this->isAlive()){
			return $hasUpdate;
		}

		if($this->stunTicks > 0){
			$this->stunTicks -= $tickDiff;
			if($this->stunTicks <= 0){
				$this->stunTicks = 0;
				$this->performKnockbackRoar();
			}
			return true;
		}

		if($this->attackCooldown > 0){
			$this->attackCooldown -= $tickDiff;
		}

		// Retargeting
		if($this->combatTarget === null || !$this->combatTarget->isAlive() || $this->combatTarget->isClosed() || $this->combatTarget->getPosition()->distance($this->location) > 32){
			$this->combatTarget = $this->findTarget();
		}

		if($this->combatTarget !== null){
			$targetPos = $this->combatTarget->getLocation();
			$dist = $this->location->distance($targetPos);

			// Look at target
			$xDist = $targetPos->x - $this->location->x;
			$zDist = $targetPos->z - $this->location->z;
			$yaw = atan2($zDist, $xDist) * 180 / M_PI - 90;
			$this->setRotation($yaw, 0.0);

			if($dist <= 2.8 && $this->attackCooldown <= 0){
				$this->performHeadbuttAttack($this->combatTarget);
				$this->attackCooldown = 25; // 1.25s
			}elseif($dist > 2.0 && $this->onGround){
				$motionX = ($xDist / $dist) * $this->movementSpeed * 0.4;
				$motionZ = ($zDist / $dist) * $this->movementSpeed * 0.4;
				$this->setMotion(new Vector3($motionX, $this->motion->y, $motionZ));
			}
		}elseif($this->raid !== null && $this->onGround){
			$center = $this->raid->getCenter();
			$cDist = $this->location->distance($center);
			if($cDist > 4.0){
				$xDist = $center->x - $this->location->x;
				$zDist = $center->z - $this->location->z;
				$motionX = ($xDist / $cDist) * $this->movementSpeed * 0.25;
				$motionZ = ($zDist / $cDist) * $this->movementSpeed * 0.25;
				$this->setMotion(new Vector3($motionX, $this->motion->y, $motionZ));
			}
		}

		return true;
	}

	private function performHeadbuttAttack(Living $target) : void{
		// Check if target player blocked with a shield
		$blocked = false;
		if($target instanceof Player && $target->isBlocking()){
			$blocked = true;
		}

		$ev = new EntityDamageByEntityEvent($this, $target, EntityDamageEvent::CAUSE_ENTITY_ATTACK, $this->attackDamage);
		$target->attack($ev);

		if($blocked || $ev->isCancelled()){
			// Ravager gets stunned by shield block!
			$this->stunTicks = 40; // 2 seconds
			$this->getWorld()->broadcastPacketToViewers($this->location, LevelSoundEventPacket::nonActorSound(LevelSoundEvent::STUN, $this->location, false));
			$this->setMotion(new Vector3(-$this->getDirectionVector()->x * 0.5, 0.2, -$this->getDirectionVector()->z * 0.5));
			return;
		}

		// Apply heavy headbutt knockback
		$dir = $target->getLocation()->subtractVector($this->location)->normalize();
		$target->setMotion(new Vector3($dir->x * 1.5, 0.4, $dir->z * 1.5));
		$this->getWorld()->addSound($this->location, new EntityAttackSound());
	}

	public function performKnockbackRoar() : void{
		// Deals 6 damage and 3.0 knockback to all non-illager / non-raider entities in 4 blocks
		$this->getWorld()->broadcastPacketToViewers($this->location, LevelSoundEventPacket::nonActorSound(LevelSoundEvent::ROAR, $this->location, false));

		$bb = $this->boundingBox->expandedCopy(4.0, 2.0, 4.0);
		foreach($this->getWorld()->getNearbyEntities($bb, $this) as $entity){
			if(!$entity instanceof Living || !$entity->isAlive() || $entity instanceof Raider || $entity instanceof Ravager){
				continue;
			}

			$ev = new EntityDamageByEntityEvent($this, $entity, EntityDamageEvent::CAUSE_ENTITY_ATTACK, 6.0);
			$entity->attack($ev);

			$dir = $entity->getLocation()->subtractVector($this->location)->normalize();
			$entity->setMotion(new Vector3($dir->x * 2.5, 0.45, $dir->z * 2.5));
		}
	}

	private function findTarget() : ?Living{
		$bestTarget = null;
		$bestDist = 32.0;

		$bb = $this->boundingBox->expandedCopy(32, 16, 32);
		foreach($this->getWorld()->getNearbyEntities($bb, $this) as $entity){
			if(!$entity instanceof Living || !$entity->isAlive() || $entity instanceof Raider || $entity instanceof Ravager){
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
		// 75% Knockback Resistance (matching BDS knockback_resistance 0.75)
		parent::attack($source);

		if($source instanceof EntityDamageByEntityEvent){
			$damager = $source->getDamager();
			if($damager instanceof Living && !$damager instanceof Raider && !$damager instanceof Ravager){
				$this->combatTarget = $damager;
			}
		}
	}

	protected function onDeath() : void{
		parent::onDeath();

		$lastDamage = $this->getLastDamageCause();
		$killer = $lastDamage instanceof EntityDamageByEntityEvent ? $lastDamage->getDamager() : null;

		if($this->raid !== null){
			$this->raid->onRaiderDeath($this, $killer);
		}
	}

	public function getDrops() : array{
		$drops = [
			new Item(new ItemIdentifier(ItemTypeIds::newId()), "Saddle")
		];

		if($this->raid !== null){
			foreach(RaiderDrops::getDrops() as $d){
				$drops[] = $d;
			}
		}

		return $drops;
	}

	public function getXpDropAmount() : int{
		return 20;
	}

	public function getPickedItem() : ?Item{
		return new Item(new ItemIdentifier(ItemTypeIds::newId()), "Saddle");
	}
}
