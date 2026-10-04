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

use pocketmine\item\Item;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\world\particle\SmokeParticle;
use pocketmine\world\sound\ClickSound;
use function mt_rand;

class DefaultDispenseBehavior implements DispenseBehavior{

	public function dispense(BlockSource $source, Item $item) : Item{
		$dispensed = $item->pop();

		$facing = $source->getFacing();
		[$dx, $dy, $dz] = Facing::OFFSET[$facing];

		if($facing === Facing::UP || $facing === Facing::DOWN){
			$motion = new Vector3(
				(mt_rand(-10, 10) / 100) * 0.1,
				$dy * 0.3 + (mt_rand(-10, 10) / 100) * 0.05,
				(mt_rand(-10, 10) / 100) * 0.1
			);
		}else{
			$motion = new Vector3(
				$dx * 0.3 + (mt_rand(-10, 10) / 100) * 0.05,
				0.2 + (mt_rand(-10, 10) / 100) * 0.05,
				$dz * 0.3 + (mt_rand(-10, 10) / 100) * 0.05
			);
		}

		$dispensePos = $source->getDispensePosition();
		$source->getWorld()->dropItem($dispensePos, $dispensed, $motion, 10);
		$source->getWorld()->addParticle($dispensePos, new SmokeParticle());
		$source->getWorld()->addSound($source->getPos(), new ClickSound());

		return $item;
	}
}
