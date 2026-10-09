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
use pocketmine\entity\object\PrimedTNT;
use pocketmine\item\Item;
use pocketmine\world\sound\ClickSound;

class TNTDispenseBehavior implements DispenseBehavior{

	public function dispense(BlockSource $source, Item $item) : Item{
		if(!$source->isTargetAvailable()){
			$source->getWorld()->addSound($source->getPos(), new \pocketmine\world\sound\ClickFailSound());
			return $item;
		}
		$item->pop();

		$world = $source->getWorld();
		$targetPos = $source->getPos()->getSide($source->getFacing());
		$spawnPos = $targetPos->add(0.5, 0.0, 0.5);

		$tnt = new PrimedTNT(Location::fromObject($spawnPos, $world, 0.0, 0.0));
		$tnt->spawnToAll();

		$world->addSound($source->getPos(), new ClickSound());

		return $item;
	}
}
