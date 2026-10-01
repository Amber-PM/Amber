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
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\Location;
use pocketmine\entity\object\ArmorStand as ArmorStandEntity;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\player\Player;
use pocketmine\world\sound\BlockPlaceSound;
use function round;

class ArmorStand extends Item{

	public function getMaxStackSize() : int{
		return 16;
	}

	public function onInteractBlock(Player $player, Block $blockReplace, Block $blockClicked, int $face, Vector3 $clickVector, array &$returnedItems) : ItemUseResult{
		if($face !== Facing::UP){
			return ItemUseResult::NONE;
		}

		if(!$blockClicked->isSolid()){
			return ItemUseResult::NONE;
		}

		$blockAbove = $blockReplace->getSide(Facing::UP);
		if($blockReplace->isSolid() || $blockAbove->isSolid()){
			return ItemUseResult::NONE;
		}

		$playerYaw = $player->getLocation()->getYaw();
		$snappedYaw = round($playerYaw / 22.5) * 22.5;

		$pos = $blockReplace->getPosition();
		$location = new Location($pos->x + 0.5, $pos->y, $pos->z + 0.5, $pos->getWorld(), $snappedYaw, 0.0);

		$entity = new ArmorStandEntity($location, CompoundTag::create());
		$entity->spawnToAll();

		$pos->getWorld()->addSound($location, new BlockPlaceSound(VanillaBlocks::OAK_PLANKS()));

		$this->pop();
		return ItemUseResult::SUCCESS;
	}
}
