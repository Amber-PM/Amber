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
use pocketmine\world\particle\DispenserParticle;
use pocketmine\world\sound\ClickSound;

class DefaultDispenseBehavior implements DispenseBehavior{

	public function dispense(BlockSource $source, Item $item) : Item{
		if(!$source->isTargetAvailable()){
			$source->getWorld()->addSound($source->getPos(), new \pocketmine\world\sound\ClickFailSound());
			return $item;
		}
		$dispensed = $item->pop();

		$facing = $source->getFacing();
		$motion = DispenseMotion::item($facing);

		$dispensePos = $source->getDispensePosition()->subtract(0, $facing === Facing::UP || $facing === Facing::DOWN ? 0.125 : 0.15625, 0);
		$source->getWorld()->dropItem($dispensePos, $dispensed, $motion, 10);
		$source->getWorld()->addParticle($dispensePos, new DispenserParticle());
		$source->getWorld()->addSound($source->getPos(), new ClickSound());

		return $item;
	}
}
