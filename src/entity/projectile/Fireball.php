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

use pocketmine\event\entity\EntityPreExplodeEvent;
use pocketmine\event\entity\ProjectileHitEvent;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\world\Explosion;

class Fireball extends SmallFireball{

	public static function getNetworkTypeId() : string{ return EntityIds::FIREBALL; }

	protected function onHit(ProjectileHitEvent $event) : void{
		$this->flagForDespawn();
		$explode = new EntityPreExplodeEvent($this, 1.0);
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
