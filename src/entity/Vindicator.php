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
use pocketmine\entity\raid\Raider;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\player\Player;
use pocketmine\world\raid\Raid;
use pocketmine\world\raid\RaiderDrops;
use pocketmine\world\sound\EntityAttackSound;
use function atan2;
use function min;
use function mt_rand;
use function strcasecmp;
use function sqrt;
use const M_PI;

class Vindicator extends Living implements Raider{

	public static function getNetworkTypeId() : string{ return EntityIds::VINDICATOR; }

	private const TAG_IS_JOHNNY = "IsJohnny";
	private const TAG_IS_CAPTAIN = "IsCaptain";
	private const TAG_WAVE = "Wave";

	private bool $isJohnny = false;
	private bool $isCaptain = false;
	private ?Raid $raid = null;
	private int $wave = 0;
	private bool $celebrating = false;

	private int $attackCooldown = 0;
	private ?Living $combatTarget = null;
	private float $movementSpeed = 0.35;
	private float $attackDamage = 8.0;

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(1.9, 0.6, 1.75);
	}

	public function getName() : string{
		return "Vindicator";
	}

	protected function initEntity(CompoundTag $nbt) : void{
		$this->setMaxHealth(24);
		parent::initEntity($nbt);

		$this->isJohnny = $nbt->getByte(self::TAG_IS_JOHNNY, 0) === 1;
		$this->isCaptain = $nbt->getByte(self::TAG_IS_CAPTAIN, 0) === 1;
		$this->wave = $nbt->getInt(self::TAG_WAVE, 0);

		if(strcasecmp($this->getNameTag(), "Johnny") === 0){
			$this->isJohnny = true;
		}
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT();
		$nbt->setByte(self::TAG_IS_JOHNNY, $this->isJohnny ? 1 : 0);
		$nbt->setByte(self::TAG_IS_CAPTAIN, $this->isCaptain ? 1 : 0);
		$nbt->setInt(self::TAG_WAVE, $this->wave);
		return $nbt;
	}

	public function isJohnny() : bool{
		return $this->isJohnny;
	}

	public function setJohnny(bool $johnny) : void{
		$this->isJohnny = $johnny;
	}

	public function isRaidCaptain() : bool{
		return $this->isCaptain;
	}

	public function setRaidCaptain(bool $isCaptain) : void{
		$this->isCaptain = $isCaptain;
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

	public function setNameTag(string $name) : void{
		parent::setNameTag($name);
		if(strcasecmp($name, "Johnny") === 0){
			$this->isJohnny = true;
		}
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

		if($this->attackCooldown > 0){
			$this->attackCooldown -= $tickDiff;
		}

		// Retarget if current target died or got too far
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
			$yDist = ($targetPos->y + $this->combatTarget->getEyeHeight()) - ($this->location->y + $this->getEyeHeight());
			$horizDist = sqrt($xDist * $xDist + $zDist * $zDist);
			$pitch = -atan2($yDist, $horizDist) * 180 / M_PI;
			$this->setRotation($yaw, $pitch);

			// Melee attack range
			if($dist <= 1.8 && $this->attackCooldown <= 0){
				$this->performMeleeAttack($this->combatTarget);
				$this->attackCooldown = 20; // 1 second
			}elseif($dist > 1.2 && $this->onGround){
				// Sprint towards target
				$speed = $this->movementSpeed * 1.2;
				$motionX = ($xDist / $dist) * $speed * 0.35;
				$motionZ = ($zDist / $dist) * $speed * 0.35;
				$this->setMotion(new Vector3($motionX, $this->motion->y, $motionZ));
			}
		}elseif($this->raid !== null && $this->onGround){
			// Navigate to village center
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

	private function performMeleeAttack(Living $target) : void{
		$ev = new EntityDamageByEntityEvent($this, $target, EntityDamageEvent::CAUSE_ENTITY_ATTACK, $this->attackDamage);
		$target->attack($ev);
		$this->getWorld()->addSound($this->location, new EntityAttackSound());
	}

	private function findTarget() : ?Living{
		$bestTarget = null;
		$bestDist = 32.0;

		$bb = $this->boundingBox->expandedCopy(32, 16, 32);
		foreach($this->getWorld()->getNearbyEntities($bb, $this) as $entity){
			if(!$entity instanceof Living || !$entity->isAlive() || $entity instanceof Raider){
				continue;
			}

			if($this->isJohnny){
				// Johnny attacks any living mob (except other illagers / raiders)
				$d = $this->location->distance($entity->getLocation());
				if($d < $bestDist){
					$bestDist = $d;
					$bestTarget = $entity;
				}
			}else{
				if($entity instanceof Player || ($entity instanceof Villager && !$entity->isBaby()) || $entity->getName() === "IronGolem" || $entity->getName() === "WanderingTrader"){
					$d = $this->location->distance($entity->getLocation());
					if($d < $bestDist){
						$bestDist = $d;
						$bestTarget = $entity;
					}
				}
			}
		}

		return $bestTarget;
	}

	public function attack(EntityDamageEvent $source) : void{
		parent::attack($source);

		if($source instanceof EntityDamageByEntityEvent){
			$damager = $source->getDamager();
			if($damager instanceof Living && !$damager instanceof Raider){
				$this->combatTarget = $damager;
			}
		}
	}

	protected function onDeath() : void{
		parent::onDeath();

		$lastDamage = $this->getLastDamageCause();
		$killer = $lastDamage instanceof EntityDamageByEntityEvent ? $lastDamage->getDamager() : null;

		if($this->isCaptain && $killer instanceof Player){
			$effects = $killer->getEffects();
			$currentOmen = $effects->get(VanillaEffects::BAD_OMEN());
			$amp = $currentOmen !== null ? min(4, $currentOmen->getAmplifier() + 1) : 0;
			$effects->add(new EffectInstance(VanillaEffects::BAD_OMEN(), 120000, $amp));
		}

		if($this->raid !== null){
			$this->raid->onRaiderDeath($this, $killer);
		}
	}

	public function getDrops() : array{
		$drops = [];

		// 0-1 Emerald
		if(mt_rand(0, 1) === 1){
			$drops[] = VanillaItems::EMERALD();
		}

		// 8.5% Iron axe
		if(mt_rand(1, 1000) <= 85){
			$drops[] = VanillaItems::IRON_AXE();
		}

		if($this->isCaptain){
			$drops[] = VanillaBlocks::OMINOUS_BANNER()->asItem();
		}

		if($this->raid !== null){
			foreach(RaiderDrops::getDrops() as $d){
				$drops[] = $d;
			}
		}

		return $drops;
	}

	public function getXpDropAmount() : int{
		return 5;
	}

	public function getPickedItem() : ?Item{
		return VanillaItems::IRON_AXE();
	}
}
