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
use pocketmine\block\Water;
use pocketmine\entity\Location;
use pocketmine\entity\object\Boat as BoatEntity;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\player\Player;

class Boat extends Item{
	protected BoatType $boatType;

	public function __construct(ItemIdentifier $identifier, string $name, BoatType $boatType){
		parent::__construct($identifier, $name);
		$this->boatType = $boatType;
	}

	public function getType() : BoatType{
		return $this->boatType;
	}

	public function getFuelTime() : int{
		return 1200; //400 in PC
	}

	public function getMaxStackSize() : int{
		return 1;
	}

	protected function createEntity(Location $location) : BoatEntity{
		return new BoatEntity($location, $this->boatType);
	}

	public function onInteractBlock(Player $player, Block $blockReplace, Block $blockClicked, int $face, Vector3 $clickVector, array &$returnedItems) : ItemUseResult{
		$isWaterPlacement = $blockClicked instanceof Water || $blockReplace instanceof Water;
		if(!$isWaterPlacement && $face !== Facing::UP && !$blockClicked->isSolid()){
			return ItemUseResult::NONE;
		}

		$pos = $blockReplace->getPosition();
		$world = $pos->getWorld();

		$spawnY = $isWaterPlacement ? $pos->y + 0.35 : $pos->y;
		$location = new Location($pos->x + 0.5, $spawnY, $pos->z + 0.5, $world, $player->getLocation()->yaw, 0.0);

		$boat = $this->createEntity($location);
		$boat->spawnToAll();

		$this->pop();
		return ItemUseResult::SUCCESS;
	}
}
