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
use pocketmine\player\Player;
use pocketmine\world\Position;
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

	private const CRYSTAL_LINK_RANGE = 64.0;
	private const CRYSTAL_SEARCH_INTERVAL = 20;
	private const TARGET_RANGE = 64.0;
	private const BREATH_RANGE = 16.0;

	private int $phaseTicks = 0;
	private int $crystalSearchTicks = 0;

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

	protected function addAttributes() : void{
		parent::addAttributes();
		$this->knockbackResistanceAttr->setDefaultValue(1.0)->setValue(1.0);
	}

	protected function initEntity(CompoundTag $nbt) : void{
		$this->setMaxHealth(200);
		parent::initEntity($nbt);

		$phaseTag = $nbt->getTag(self::TAG_DRAGON_PHASE);
		if($phaseTag instanceof IntTag){
			$this->phase = $phaseTag->getValue();
		}

		$this->previouslyKilled = $this->getWorld()->hasKilledEnderDragon() || $nbt->getByte(self::TAG_PREVIOUSLY_KILLED, 0) !== 0;
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
		$this->phaseTicks = 0;
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
		if($this->linkedCrystalId !== $crystal->getId()){
			$this->unlinkCrystal();
			$this->linkedCrystalId = $crystal->getId();
		}
		$crystal->setBeamTarget($this->location);
	}

	public function unlinkCrystal() : void{
		if($this->linkedCrystalId !== null){
			$crystal = $this->getLinkedCrystal();
			if($crystal !== null && !$crystal->isClosed() && $crystal->isAlive()){
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

		if(!$this->isAlive() || $this->isClosed() || $this->isFlaggedForDespawn()){
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
			}elseif($this->location->distanceSquared($crystal->getPosition()) > self::CRYSTAL_LINK_RANGE ** 2){
				$this->unlinkCrystal();
			}else{
				if($crystal->getBeamTarget()?->distanceSquared($this->location) !== 0.0){
					$crystal->setBeamTarget($this->location);
				}
				// Regenerate 1.0 HP per tick
				if($this->getHealth() < $this->getMaxHealth()){
					$this->setHealth(min((float) $this->getMaxHealth(), $this->getHealth() + (1.0 * $tickDiff)));
					$hasUpdate = true;
				}
			}
		}

		if(!$this->isAlive() || $this->isClosed() || $this->isFlaggedForDespawn()){
			return $hasUpdate;
		}

		$this->crystalSearchTicks -= $tickDiff;
		if($this->linkedCrystalId === null && $this->crystalSearchTicks <= 0){
			$this->crystalSearchTicks = self::CRYSTAL_SEARCH_INTERVAL;
			$crystal = $this->getWorld()->getNearestEntity($this->location, self::CRYSTAL_LINK_RANGE, EndCrystal::class);
			if($crystal !== null){
				$this->linkCrystal($crystal);
			}
		}

		$this->tickCombat($tickDiff);
		return true; // Keep combat and crystal searches scheduled even when stationary.
	}

	private function tickCombat(int $tickDiff) : void{
		if($this->phase === self::PHASE_DYING){
			return;
		}
		$this->phaseTicks += $tickDiff;
		$duration = match($this->phase){
			self::PHASE_CIRCLING => 100,
			self::PHASE_STRAFING, self::PHASE_BREATH => 60,
			default => 40,
		};
		if($this->phaseTicks < $duration){
			return;
		}

		$target = $this->findCombatTarget();
		if($target === null){
			if($this->phase !== self::PHASE_CIRCLING){
				$this->setPhase(self::PHASE_CIRCLING);
			}else{
				$this->phaseTicks = 0;
			}
			return;
		}

		switch($this->phase){
			case self::PHASE_CIRCLING:
			case self::PHASE_CHARGING:
				$this->setPhase(self::PHASE_STRAFING);
				$this->shootFireball($target->getEyePos());
				break;
			case self::PHASE_STRAFING:
				if($this->location->distanceSquared($target->getPosition()) <= self::BREATH_RANGE ** 2){
					$this->setPhase(self::PHASE_PERCHING);
				}else{
					$this->setPhase(self::PHASE_CIRCLING);
				}
				break;
			case self::PHASE_PERCHING:
				if($this->location->distanceSquared($target->getPosition()) <= self::BREATH_RANGE ** 2){
					$this->setPhase(self::PHASE_BREATH);
					$this->sprayBreath($target->getPosition());
				}else{
					$this->setPhase(self::PHASE_CIRCLING);
				}
				break;
			default:
				$this->setPhase(self::PHASE_CIRCLING);
				break;
		}
	}

	private function findCombatTarget() : ?Player{
		$target = null;
		$bestDistance = self::TARGET_RANGE ** 2;
		foreach($this->getWorld()->getPlayers() as $player){
			if(!$player->isAlive() || $player->isCreative()){
				continue;
			}
			$distance = $this->location->distanceSquared($player->getPosition());
			if($distance < $bestDistance){
				$target = $player;
				$bestDistance = $distance;
			}
		}
		return $target;
	}

	protected function onDeath() : void{
		if(!$this->getWorld()->claimEnderDragonFirstKill()){
			$this->setPreviouslyKilled();
		}
		$this->setPhase(self::PHASE_DYING);
		$this->unlinkCrystal();
		parent::onDeath();
	}

	protected function setPosition(Vector3 $pos) : bool{
		if(!$this->isClosed() && $pos instanceof Position && $pos->getWorld() !== $this->getWorld()){
			$this->unlinkCrystal();
			$this->crystalSearchTicks = 0;
		}
		return parent::setPosition($pos);
	}

	protected function onDispose() : void{
		if($this->location->isValid()){
			$this->unlinkCrystal();
		}
		parent::onDispose();
	}

	public function sprayBreath(Vector3 $targetPos) : AreaEffectCloud{
		$cloud = new AreaEffectCloud(Location::fromObject($targetPos, $this->getWorld()));
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
