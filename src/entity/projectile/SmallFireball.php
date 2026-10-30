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
use pocketmine\event\entity\EntityCombustByEntityEvent;
use pocketmine\event\entity\EntityDamageByChildEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\ProjectileHitEntityEvent;
use pocketmine\event\entity\ProjectileHitEvent;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\world\particle\FlameParticle;
use pocketmine\world\particle\SmokeParticle;

class SmallFireball extends Projectile{

	public static function getNetworkTypeId() : string{
		return EntityIds::SMALL_FIREBALL;
	}

	protected float $damage = 5.0;

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(0.31, 0.31);
	}

	protected function getInitialGravity() : float{
		return 0.0;
	}

	protected function getInitialDragMultiplier() : float{
		return 0.0;
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
		if($event instanceof ProjectileHitEntityEvent){
			$target = $event->getEntityHit();
			$combustEv = new EntityCombustByEntityEvent($this, $target, 5);
			$combustEv->call();
			if(!$combustEv->isCancelled()){
				$target->setOnFire($combustEv->getDuration());
			}

			$owner = $this->getOwningEntity();
			$damageEv = new EntityDamageByChildEntityEvent(
				$owner ?? $this,
				$this,
				$target,
				EntityDamageEvent::CAUSE_PROJECTILE,
				$this->damage
			);
			$target->attack($damageEv);
		}

		$this->flagForDespawn();
	}
}
