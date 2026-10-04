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
use pocketmine\entity\projectile\EnderPearl;
use pocketmine\entity\projectile\ExperienceBottle;
use pocketmine\entity\projectile\Projectile;
use pocketmine\entity\projectile\Snowball;
use pocketmine\entity\projectile\SplashPotion;
use pocketmine\item\Item;
use pocketmine\item\ItemTypeIds;
use pocketmine\item\SplashPotion as ItemSplashPotion;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\world\particle\SmokeParticle;
use pocketmine\world\sound\BowShootSound;
use function mt_rand;

class ProjectileDispenseBehavior implements DispenseBehavior{

	public function dispense(BlockSource $source, Item $item) : Item{
		$dispensed = $item->pop();

		$world = $source->getWorld();
		$dispensePos = $source->getDispensePosition();
		$facing = $source->getFacing();
		[$dx, $dy, $dz] = Facing::OFFSET[$facing];

		$speed = 1.1;
		$motion = new Vector3(
			$dx * $speed + (mt_rand(-10, 10) / 100) * 0.05,
			$dy * $speed + ($facing === Facing::UP || $facing === Facing::DOWN ? 0.0 : 0.1) + (mt_rand(-10, 10) / 100) * 0.05,
			$dz * $speed + (mt_rand(-10, 10) / 100) * 0.05
		);

		$loc = Location::fromObject($dispensePos, $world, 0.0, 0.0);
		$projectile = $this->createProjectile($loc, $dispensed);
		if($projectile !== null){
			$projectile->setMotion($motion);
			$projectile->spawnToAll();
			$world->addSound($source->getPos(), new BowShootSound());
			$world->addParticle($dispensePos, new SmokeParticle());
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
			ItemTypeIds::ENDER_PEARL => new EnderPearl($loc, null),
			default => null,
		};
	}
}
