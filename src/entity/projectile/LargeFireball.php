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

namespace pocketmine\entity\projectile;

use pocketmine\entity\Entity;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\event\entity\EntityDamageByChildEntityEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\ProjectileHitEntityEvent;
use pocketmine\event\entity\ProjectileHitEvent;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\player\Player;
use pocketmine\world\Explosion;
use pocketmine\world\particle\FlameParticle;
use pocketmine\world\particle\SmokeParticle;

class LargeFireball extends Projectile{

	public static function getNetworkTypeId() : string{
		return EntityIds::FIREBALL;
	}

	protected float $damage = 6.0;
	protected float $explosionPower = 1.0;

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(1.0, 1.0);
	}

	protected function getInitialGravity() : float{
		return 0.0;
	}

	protected function getInitialDragMultiplier() : float{
		return 0.0;
	}

	public function canBeCollidedWith() : bool{
		return true;
	}

	public function attack(EntityDamageEvent $source) : void{
		if($source instanceof EntityDamageByEntityEvent){
			$damager = $source->getDamager();
			if($damager !== null){
				$this->setOwningEntity($damager);
				$dir = $damager instanceof Player ? $damager->getDirectionVector() : $damager->getMotion()->normalize();
				if($dir->lengthSquared() < 0.001){
					$dir = $this->getMotion()->multiply(-1.0);
				}
				$this->setMotion($dir->multiply(1.0));
				return;
			}
		}

		if($source->getCause() === EntityDamageEvent::CAUSE_VOID){
			parent::attack($source);
		}
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		$hasUpdate = parent::entityBaseTick($tickDiff);

		if(!$this->isClosed() && !$this->isFlaggedForDespawn()){
			$this->getWorld()->addParticle($this->location, new FlameParticle());
			$this->getWorld()->addParticle($this->location, new SmokeParticle());
		}

		return $hasUpdate;
	}

	protected function onHit(ProjectileHitEvent $event) : void{
		$owner = $this->getOwningEntity();
		if($event instanceof ProjectileHitEntityEvent){
			$target = $event->getEntityHit();
			$damageEv = new EntityDamageByChildEntityEvent(
				$owner ?? $this,
				$this,
				$target,
				EntityDamageEvent::CAUSE_PROJECTILE,
				$this->damage
			);
			$target->attack($damageEv);
		}

		$explosion = new Explosion($this->location, $this->explosionPower, $owner ?? $this);
		$explosion->explodeA();
		$explosion->explodeB();

		$this->flagForDespawn();
	}
}
