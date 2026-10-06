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

namespace pocketmine\block;

use pocketmine\block\utils\SupportType;
use pocketmine\data\runtime\RuntimeDataDescriber;
use pocketmine\entity\Entity;
use pocketmine\item\Item;
use pocketmine\math\Axis;
use pocketmine\math\Facing;

class NetherPortal extends Transparent{

	protected int $axis = Axis::X;

	protected function describeBlockOnlyState(RuntimeDataDescriber $w) : void{
		$w->horizontalAxis($this->axis);
	}

	public function getAxis() : int{
		return $this->axis;
	}

	/**
	 * @throws \InvalidArgumentException
	 * @return $this
	 */
	public function setAxis(int $axis) : self{
		if($axis !== Axis::X && $axis !== Axis::Z){
			throw new \InvalidArgumentException("Invalid axis");
		}
		$this->axis = $axis;
		return $this;
	}

	public function getLightLevel() : int{
		return 11;
	}

	public function isSolid() : bool{
		return false;
	}

	protected function recalculateCollisionBoxes() : array{
		return [];
	}

	public function getSupportType(int $facing) : SupportType{
		return SupportType::NONE;
	}

	public function getDrops(Item $item) : array{
		return [];
	}

	public function onNearbyBlockChange() : void{
		if(!$this->isValid()){
			$this->position->getWorld()->setBlock($this->position, VanillaBlocks::AIR());
		}
	}

	public function isValid() : bool{
		$checkNeighbor = function(int $facing) : bool{
			$side = $this->getSide($facing);
			if($side instanceof NetherPortal && $side->getAxis() === $this->axis){
				return true;
			}
			return $side->getTypeId() === BlockTypeIds::OBSIDIAN;
		};

		if(!$checkNeighbor(Facing::UP) || !$checkNeighbor(Facing::DOWN)){
			return false;
		}

		if($this->axis === Axis::X){
			return $checkNeighbor(Facing::WEST) && $checkNeighbor(Facing::EAST);
		}

		return $checkNeighbor(Facing::NORTH) && $checkNeighbor(Facing::SOUTH);
	}

	public function hasEntityCollision() : bool{
		return true;
	}

	public function onEntityInside(Entity $entity) : bool{
		if($entity instanceof \pocketmine\player\Player){
			\pocketmine\world\portal\PortalTeleporter::handlePlayerInNetherPortal($entity);
		}
		return true;
	}
}
