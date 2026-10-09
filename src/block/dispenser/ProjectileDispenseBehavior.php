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

namespace pocketmine\block\dispenser;

use pocketmine\entity\Location;
use pocketmine\entity\projectile\Arrow;
use pocketmine\entity\projectile\Egg;
use pocketmine\entity\projectile\ExperienceBottle;
use pocketmine\entity\projectile\Projectile;
use pocketmine\entity\projectile\Snowball;
use pocketmine\entity\projectile\SplashPotion;
use pocketmine\item\Item;
use pocketmine\item\ItemTypeIds;
use pocketmine\item\SplashPotion as ItemSplashPotion;
use pocketmine\world\particle\DispenserParticle;
use pocketmine\world\sound\BowShootSound;

class ProjectileDispenseBehavior implements DispenseBehavior{

	public function dispense(BlockSource $source, Item $item) : Item{
		if(!$source->isTargetAvailable()){
			$source->getWorld()->addSound($source->getPos(), new \pocketmine\world\sound\ClickFailSound());
			return $item;
		}
		$dispensed = $item->pop();

		$world = $source->getWorld();
		$dispensePos = $source->getDispensePosition();
		$motion = DispenseMotion::projectile($source->getFacing(), $item->getTypeId() === ItemTypeIds::SPLASH_POTION || $item->getTypeId() === ItemTypeIds::EXPERIENCE_BOTTLE);

		$loc = Location::fromObject($dispensePos, $world, 0.0, 0.0);
		$projectile = $this->createProjectile($loc, $dispensed);
		if($projectile !== null){
			$projectile->setMotion($motion);
			$projectile->spawnToAll();
			$world->addSound($source->getPos(), new BowShootSound());
			$world->addParticle($dispensePos, new DispenserParticle());
		}else{
			$source->getWorld()->dropItem($dispensePos, $dispensed, $motion, 10);
		}

		return $item;
	}

	protected function createProjectile(Location $loc, Item $item) : ?Projectile{
		return match($item->getTypeId()){
			ItemTypeIds::ARROW => new Arrow($loc, null, false),
			ItemTypeIds::SNOWBALL => new Snowball($loc, null),
			ItemTypeIds::EGG => new Egg($loc, null),
			ItemTypeIds::SPLASH_POTION => $item instanceof ItemSplashPotion ? new SplashPotion($loc, null, $item->getType()) : null,
			ItemTypeIds::EXPERIENCE_BOTTLE => new ExperienceBottle($loc, null),
			default => null,
		};
	}
}
