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

use pocketmine\block\Block;
use pocketmine\block\EndPortalFrame;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\portal\EndPortalDetector;
use pocketmine\world\sound\EndPortalFrameFillSound;

class EnderEye extends Item{

	public function getMaxStackSize() : int{
		return 64;
	}

	public function onInteractBlock(Player $player, Block $blockReplace, Block $blockClicked, int $face, Vector3 $clickVector, array &$returnedItems) : ItemUseResult{
		if($blockClicked instanceof EndPortalFrame && !$blockClicked->hasEye()){
			$blockClicked->setEye(true);
			$world = $player->getWorld();
			$world->setBlock($blockClicked->getPosition(), $blockClicked);
			$world->addSound($blockClicked->getPosition()->add(0.5, 0.5, 0.5), new EndPortalFrameFillSound());

			$this->pop();

			EndPortalDetector::tryActivate($world, $blockClicked->getPosition());

			return ItemUseResult::SUCCESS;
		}

		return ItemUseResult::NONE;
	}
}
