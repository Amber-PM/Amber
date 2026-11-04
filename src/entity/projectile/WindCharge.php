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
use pocketmine\entity\Living;
use pocketmine\entity\Location;
use pocketmine\event\entity\EntityDamageByChildEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\ProjectileHitEntityEvent;
use pocketmine\event\entity\ProjectileHitEvent;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\world\Explosion;

class WindCharge extends Throwable{

	protected float $damage = 1.0;

	public function __construct(Location $location, ?Entity $shootingEntity = null, ?CompoundTag $nbt = null){
		parent::__construct($location, $shootingEntity, $nbt);
	}

	public static function getNetworkTypeId() : string{
		return EntityIds::WIND_CHARGE_PROJECTILE;
	}

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(0.3, 0.3);
	}

	protected function getInitialDragMultiplier() : float{
		return 0.0;
	}

	protected function getInitialGravity() : float{
		return 0.0;
	}

	protected function onHit(ProjectileHitEvent $event) : void{
		if($event instanceof ProjectileHitEntityEvent){
			$entity = $event->getEntityHit();
			$owningEntity = $this->getOwningEntity();

			$ev = new EntityDamageByChildEntityEvent($owningEntity ?? $this, $this, $entity, EntityDamageEvent::CAUSE_PROJECTILE, $this->damage);
			$entity->attack($ev);

			if($entity instanceof Living && $entity->isAlive()){
				// Wind burst knockback upwards and away
				$entity->knockBack(0.0, 0.0, 1.2, 0.8);
			}
		}

		$explosion = new Explosion($this->location, 1.2, $this);
		$explosion->explodeA();
		$explosion->explodeB();

		$this->flagForDespawn();
	}
}
