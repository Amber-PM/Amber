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

use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityPreExplodeEvent;
use pocketmine\event\entity\ProjectileHitEvent;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\player\Player;
use pocketmine\world\Explosion;

class Fireball extends SmallFireball{

	public static function getNetworkTypeId() : string{ return EntityIds::FIREBALL; }

	public function attack(EntityDamageEvent $source) : void{
		if(!$source instanceof EntityDamageByEntityEvent || $source->getCause() !== EntityDamageEvent::CAUSE_ENTITY_ATTACK){
			parent::attack($source);
			return;
		}

		$attacker = $source->getDamager();
		if(!$attacker instanceof Player || $attacker->isSpectator()){
			return;
		}
		$source->call();
		if($source->isCancelled() || $this->isClosed() || $this->isFlaggedForDespawn() ||
			$attacker->isClosed() || !$attacker->isAlive() || $attacker->getWorld() !== $this->getWorld()){
			return;
		}

		$speed = $this->motion->length();
		if($this->setMotion($attacker->getDirectionVector()->multiply($speed > 0 ? $speed : 0.8))){
			$this->setOwningEntity($attacker);
			$this->ignoreOwnerUntilAge = $this->ticksLived + 5;
		}else{
			$source->cancel();
		}
	}

	protected function onHit(ProjectileHitEvent $event) : void{
		$this->flagForDespawn();
		$explode = new EntityPreExplodeEvent($this, 1.0);
		$explode->setIncendiary(true);
		$explode->call();
		if(!$explode->isCancelled()){
			$explosion = new Explosion($this->getPosition(), $explode->getRadius(), $this, $explode->getFireChance());
			if($explode->isBlockBreaking()){
				$explosion->explodeA();
			}
			$explosion->explodeB();
		}
	}
}
