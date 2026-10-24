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

use pocketmine\event\entity\EntityCombustByBlockEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\player\Player;
use function mt_rand;

class Phantom extends Living{

	public static function getNetworkTypeId() : string{
		return EntityIds::PHANTOM;
	}

	public function getName() : string{
		return "Phantom";
	}

	public function getDefaultMaxHealth() : int{
		return 20;
	}

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(0.5, 0.9);
	}

	protected function getInitialGravity() : float{
		return 0.0; // Phantoms fly!
	}

	protected function initEntity(CompoundTag $nbt) : void{
		$this->setMaxHealth(20);
		parent::initEntity($nbt);
		if($nbt->getTag("Health") === null){
			$this->setHealth(20.0);
		}
	}

	public function attack(EntityDamageEvent $source) : void{
		// Fall damage immunity for flying entities
		if($source->getCause() === EntityDamageEvent::CAUSE_FALL){
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

		$world = $this->getWorld();

		// Sunlight burning for undead
		$time = $world->getTime() % 24000;
		if($time >= 0 && $time < 12000 && !$this->isUnderwater()){
			$highestBlockAtPos = $world->getHighestBlockAt((int) $this->location->x, (int) $this->location->z);
			if($highestBlockAtPos === null || $highestBlockAtPos <= $this->location->y){
				$this->setOnFire(8);
				$hasUpdate = true;
			}
		}

		// Avoid cats within 16 blocks
		$felineNearby = false;
		foreach($world->getNearbyEntities($this->boundingBox->expandedCopy(16, 16, 16), $this) as $nearby){
			if($nearby instanceof Cat){
				$felineNearby = true;
				break;
			}
		}

		// Swoop attack target players
		if(!$felineNearby){
			foreach($world->getNearbyEntities($this->boundingBox->expandedCopy(1.5, 1.5, 1.5), $this) as $nearby){
				if($nearby instanceof Player && $nearby->isAlive() && !$nearby->isCreative() && !$nearby->isSpectator()){
					$ev = new EntityDamageByEntityEvent($this, $nearby, EntityDamageEvent::CAUSE_ENTITY_ATTACK, 6.0);
					$nearby->attack($ev);
				}
			}
		}

		return $hasUpdate;
	}

	public function getDrops() : array{
		return [
			VanillaItems::PHANTOM_MEMBRANE()->setCount(mt_rand(0, 1))
		];
	}

	public function getXpDropAmount() : int{
		return 5;
	}
}
