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

namespace pocketmine\entity\object;

use pocketmine\block\VanillaBlocks;
use pocketmine\entity\Entity;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Living;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\world\sound\ExplodeSound;

class LightningBolt extends Entity{

	public static function getNetworkTypeId() : string{ return EntityIds::LIGHTNING_BOLT; }

	protected int $age = 0;

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(1.8, 0.3);
	}

	protected function getInitialDragMultiplier() : float{ return 0.0; }

	protected function getInitialGravity() : float{ return 0.0; }

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		if($this->closed){
			return false;
		}

		$hasUpdate = parent::entityBaseTick($tickDiff);
		$this->age += $tickDiff;

		if($this->age === 1){
			$this->broadcastSound(new ExplodeSound());

			// Damage entities within 3 blocks
			$bb = $this->getBoundingBox()->expandedCopy(3.0, 3.0, 3.0);
			foreach($this->getWorld()->getNearbyEntities($bb, $this) as $entity){
				if($entity instanceof Living && $entity->isAlive()){
					$ev = new EntityDamageEvent($entity, EntityDamageEvent::CAUSE_LIGHTNING, 5.0);
					$entity->attack($ev);
					$entity->setOnFire(8);
				}
			}

			// Strike fire at base block if replaceable
			$baseBlock = $this->getWorld()->getBlock($this->getPosition());
			if($baseBlock->canBeReplaced()){
				$this->getWorld()->setBlock($this->getPosition(), VanillaBlocks::FIRE());
			}
		}

		if($this->age >= 30){
			$this->flagForDespawn();
		}

		return $hasUpdate || $this->age < 30;
	}
}
