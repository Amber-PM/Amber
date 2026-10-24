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

namespace pocketmine\item;

use pocketmine\entity\Location;
use pocketmine\entity\projectile\FishingHook;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\sound\WaterSplashSound;

class FishingRod extends Durable{

	public function getMaxStackSize() : int{
		return 1;
	}

	public function getMaxDurability() : int{
		return 384;
	}

	public function onClickAir(Player $player, Vector3 $directionVector, array &$returnedItems) : ItemUseResult{
		$hook = $player->getFishingHook();
		if($hook !== null && !$hook->isClosed()){
			$damage = $hook->reelIn();
			$player->setFishingHook(null);
			if($damage > 0){
				$this->applyDamage($damage);
			}
		}else{
			$location = Location::fromObject(
				$player->getEyePos(),
				$player->getWorld(),
				$player->getLocation()->yaw,
				$player->getLocation()->pitch
			);
			$newHook = new FishingHook($location, $player);
			$motion = $directionVector->multiply(1.5)->add(0, 0.1, 0);
			$newHook->setMotion($motion);
			$newHook->spawnToAll();
			$player->setFishingHook($newHook);
			$player->broadcastSound(new WaterSplashSound(0.4));
		}

		return ItemUseResult::SUCCESS;
	}
}
