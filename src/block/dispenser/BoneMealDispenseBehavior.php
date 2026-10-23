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
use pocketmine\world\particle\HappyVillagerParticle;
use pocketmine\world\sound\ClickFailSound;
use pocketmine\world\sound\ItemUseOnBlockSound;

class BoneMealDispenseBehavior implements DispenseBehavior{

	public function dispense(BlockSource $source, Item $item) : Item{
		if(!$source->isTargetAvailable()){
			$source->getWorld()->addSound($source->getPos(), new ClickFailSound());
			return $item;
		}
		$world = $source->getWorld();
		$targetPos = $source->getPos()->getSide($source->getFacing());
		$targetBlock = $world->getBlock($targetPos);

		$returnedItems = [];
		if($targetBlock->onInteract($item, Facing::opposite($source->getFacing()), new Vector3(0, 0, 0), null, $returnedItems)){
			$world->addParticle($targetPos->add(0.5, 0.5, 0.5), new HappyVillagerParticle());
			$world->addSound($targetPos, new ItemUseOnBlockSound($targetBlock));
			return $item;
		}

		$world->addSound($source->getPos(), new ClickFailSound());
		return $item;
	}
}
