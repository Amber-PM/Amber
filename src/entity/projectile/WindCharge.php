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

use pocketmine\block\Block;
use pocketmine\entity\Entity;
use pocketmine\entity\Living;
use pocketmine\event\entity\EntityDamageByChildEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\ProjectileHitEvent;
use pocketmine\math\RayTraceResult;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\world\sound\ExplodeSound;

class WindCharge extends Throwable{

	public static function getNetworkTypeId() : string{ return EntityIds::WIND_CHARGE_PROJECTILE; }

	protected float $damage = 2.5;

	public function onHit(ProjectileHitEvent $event) : void{
		$world = $this->getWorld();
		$pos = $this->getLocation();
		$world->addSound($pos, new ExplodeSound());

		$bb = $this->getBoundingBox()->expandedCopy(2.5, 2.5, 2.5);
		foreach($world->getNearbyEntities($bb, $this) as $entity){
			if($entity instanceof Living && $entity->isAlive()){
				$diff = $entity->getPosition()->subtractVector($pos);
				$dist = $diff->length();
				if($dist > 0.001){
					$force = max(0.3, (2.5 - $dist) / 2.5 * 1.5);
					$motion = new Vector3($diff->x / $dist * $force, 0.5, $diff->z / $dist * $force);
					$entity->setMotion($motion);
				}
			}
		}

		$this->flagForDespawn();
	}

	protected function onHitEntity(Entity $entityHit, RayTraceResult $hitResult) : void{
		$owner = $this->getOwningEntity();
		$ev = new EntityDamageByChildEntityEvent($owner ?? $this, $this, $entityHit, EntityDamageEvent::CAUSE_PROJECTILE, $this->damage);
		$entityHit->attack($ev);
		$this->flagForDespawn();
	}
}
