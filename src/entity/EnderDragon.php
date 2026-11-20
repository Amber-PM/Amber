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
use pocketmine\entity\object\AreaEffectCloud;
use pocketmine\entity\object\EndCrystal;
use pocketmine\entity\projectile\DragonFireball;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use function min;

class EnderDragon extends Living{

	public const TAG_DRAGON_PHASE = "DragonPhase";
	public const TAG_PREVIOUSLY_KILLED = "PreviouslyKilled";

	public const PHASE_CIRCLING = 0;
	public const PHASE_STRAFING = 1;
	public const PHASE_CHARGING = 2;
	public const PHASE_PERCHING = 3;
	public const PHASE_BREATH = 4;
	public const PHASE_DYING = 5;

	protected int $phase = self::PHASE_CIRCLING;
	protected bool $previouslyKilled = false;
	protected ?int $linkedCrystalId = null;

	public static function getNetworkTypeId() : string{
		return EntityIds::ENDER_DRAGON;
	}

	public function getName() : string{
		return "Ender Dragon";
	}

	public function getDefaultMaxHealth() : int{
		return 200;
	}

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(4.0, 13.0);
	}

	protected function getInitialGravity() : float{
		return 0.0;
	}

	protected function getInitialDragMultiplier() : float{
		return 0.0;
	}

	public function knockBack(float $x, float $z, float $force = 0.4, ?float $verticalLimit = null) : void{
		// 100% knockback resistance
	}

	protected function initEntity(CompoundTag $nbt) : void{
		$this->setMaxHealth(200);
		parent::initEntity($nbt);

		$phaseTag = $nbt->getTag(self::TAG_DRAGON_PHASE);
		if($phaseTag instanceof IntTag){
			$this->phase = $phaseTag->getValue();
		}

		$this->previouslyKilled = $nbt->getByte(self::TAG_PREVIOUSLY_KILLED, 0) !== 0;

		if($nbt->getTag("Health") === null){
			$this->setHealth(200.0);
		}
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT();
		$nbt->setInt(self::TAG_DRAGON_PHASE, $this->phase);
		$nbt->setByte(self::TAG_PREVIOUSLY_KILLED, $this->previouslyKilled ? 1 : 0);
		return $nbt;
	}

	public function getPhase() : int{
		return $this->phase;
	}

	public function setPhase(int $phase) : void{
		$this->phase = $phase;
		$this->networkPropertiesDirty = true;
	}

	public function isPerched() : bool{
		return $this->phase === self::PHASE_PERCHING || $this->phase === self::PHASE_BREATH;
	}

	public function isPreviouslyKilled() : bool{
		return $this->previouslyKilled;
	}

	public function setPreviouslyKilled(bool $killed = true) : void{
		$this->previouslyKilled = $killed;
	}

	public function linkCrystal(EndCrystal $crystal) : void{
		$this->linkedCrystalId = $crystal->getId();
		$crystal->setBeamTarget($this->location);
	}

	public function unlinkCrystal() : void{
		if($this->linkedCrystalId !== null){
			$crystal = $this->getLinkedCrystal();
			if($crystal !== null && $crystal->isAlive()){
				$crystal->setBeamTarget(null);
			}
			$this->linkedCrystalId = null;
		}
	}

	public function getLinkedCrystal() : ?EndCrystal{
		if($this->linkedCrystalId === null){
			return null;
		}
		$entity = $this->getWorld()->getEntity($this->linkedCrystalId);
		return $entity instanceof EndCrystal ? $entity : null;
	}

	public function attack(EntityDamageEvent $source) : void{
		// Fire, fall, freezing, and suffocation immunity
		if(
			$source->getCause() === EntityDamageEvent::CAUSE_FIRE ||
			$source->getCause() === EntityDamageEvent::CAUSE_FIRE_TICK ||
			$source->getCause() === EntityDamageEvent::CAUSE_LAVA ||
			$source->getCause() === EntityDamageEvent::CAUSE_FALL ||
			$source->getCause() === EntityDamageEvent::CAUSE_SUFFOCATION
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

		// Handle End Crystal healing
		if($this->linkedCrystalId !== null){
			$crystal = $this->getLinkedCrystal();
			if($crystal === null || !$crystal->isAlive() || $crystal->isClosed() || $crystal->isFlaggedForDespawn()){
				// Crystal was destroyed while healing -> Dragon takes 10 damage!
				$this->linkedCrystalId = null;
				$damageEv = new EntityDamageEvent($this, EntityDamageEvent::CAUSE_ENTITY_EXPLOSION, 10.0);
				$this->attack($damageEv);
			}else{
				// Regenerate 1.0 HP per tick
				if($this->getHealth() < $this->getMaxHealth()){
					$this->setHealth(min((float) $this->getMaxHealth(), $this->getHealth() + (1.0 * $tickDiff)));
					$hasUpdate = true;
				}
			}
		}

		return $hasUpdate;
	}

	public function sprayBreath(Vector3 $targetPos) : AreaEffectCloud{
		$headPos = $this->location->add(0, 1.0, 0);
		$cloud = new AreaEffectCloud(Location::fromObject($headPos, $this->getWorld()));
		$cloud->getEffects()->add(new EffectInstance(VanillaEffects::INSTANT_DAMAGE(), 1, 1));
		$cloud->spawnToAll();
		return $cloud;
	}

	public function shootFireball(Vector3 $targetPos) : DragonFireball{
		$headPos = $this->location->add(0, 1.5, 0);
		$direction = $targetPos->subtractVector($headPos)->normalize();

		$location = Location::fromObject($headPos, $this->getWorld(), 0.0, 0.0);
		$fireball = new DragonFireball($location);
		$fireball->setMotion($direction->multiply(1.5));
		$fireball->setOwningEntity($this);
		$fireball->spawnToAll();

		return $fireball;
	}

	public function getDrops() : array{
		return [
			VanillaBlocks::DRAGON_EGG()->asItem()
		];
	}

	public function getXpDropAmount() : int{
		return $this->previouslyKilled ? 500 : 12000;
	}
}
