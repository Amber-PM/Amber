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

use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\world\particle\HeartParticle;
use function mt_rand;

class Donkey extends ChestedHorse{

	public static function getNetworkTypeId() : string{
		return EntityIds::DONKEY;
	}

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(1.5, 1.3965);
	}

	public function getName() : string{
		return "Donkey";
	}

	protected function addAttributes() : void{
		parent::addAttributes();
		$hp = (float) mt_rand(15, 30);
		$this->setMaxHealth((int) $hp);
		$this->setHealth($hp);
		$this->jumpStrength = 0.5;
		$this->speedMultiplier = 0.175;
	}

	public function findEligibleMate() : ?AbstractHorse{
		if(!isset($this->location) || !$this->canBreed()){
			return null;
		}
		$world = $this->getWorld();
		if(!$world->isLoaded()){
			return null;
		}

		$candidates = isset($this->boundingBox)
			? $world->getNearbyEntities($this->boundingBox->expandedCopy(8.0, 8.0, 8.0), $this)
			: $world->getEntities();

		foreach($candidates as $nearby){
			if(
				$nearby instanceof AbstractHorse &&
				$nearby !== $this &&
				$nearby->isAlive() &&
				!$nearby->isClosed() &&
				$nearby->isTamed() &&
				$nearby->isInLove() &&
				$nearby->canBreed() &&
				isset($nearby->location) &&
				$this->location->distance($nearby->location) <= 8.0
			){
				return $nearby;
			}
		}

		return null;
	}

	public function breed(AbstractHorse $partner) : ?AbstractHorse{
		if(!isset($this->location) || !isset($partner->location)){
			return null;
		}

		$midX = ($this->location->x + $partner->location->x) / 2.0;
		$midY = ($this->location->y + $partner->location->y) / 2.0;
		$midZ = ($this->location->z + $partner->location->z) / 2.0;
		$midLocation = new Location($midX, $midY, $midZ, $this->getWorld(), 0.0, 0.0);

		// Donkey + Horse produces Mule; Donkey + Donkey produces Donkey
		if($partner instanceof Horse){
			$baby = new Mule($midLocation);
		}else{
			$baby = new Donkey($midLocation);
		}

		$baby->setAge(-24000);
		$baby->setTamed(true);
		$baby->setOwnerUUID($this->getOwnerUUID());
		$baby->setOwnerName($this->getOwnerName());
		$baby->spawnToAll();

		$this->setInLoveTicks(0);
		$partner->setInLoveTicks(0);
		$this->setBreedingCooldownTicks(self::BREEDING_COOLDOWN_TICKS);
		$partner->setBreedingCooldownTicks(self::BREEDING_COOLDOWN_TICKS);

		if(isset($this->location)){
			$world = $this->getWorld();
			if($world->isLoaded()){
				$world->addParticle($midLocation->add(0, 1.0, 0), new HeartParticle());
			}
		}

		return $baby;
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		$hasUpdate = parent::entityBaseTick($tickDiff);

		if($this->inLoveTicks > 0 && $this->canBreed()){
			$mate = $this->findEligibleMate();
			if($mate !== null && isset($this->location) && isset($mate->location)){
				$dist = $this->location->distance($mate->location);
				if($dist <= 1.5){
					$this->breed($mate);
				}
				$hasUpdate = true;
			}
		}

		return $hasUpdate;
	}
}
