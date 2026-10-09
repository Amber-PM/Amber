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

use pocketmine\block\tile\PistonArm;
use pocketmine\block\utils\AnyFacing;
use pocketmine\data\runtime\RuntimeDataDescriber;
use pocketmine\item\Item;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;

class PistonHead extends Transparent implements AnyFacing{
	protected int $facing = Facing::DOWN;
	protected bool $sticky = false;
	private ?Vector3 $movementOffset = null;

	protected function describeBlockOnlyState(RuntimeDataDescriber $w) : void{
		$w->facing($this->facing);
		$w->bool($this->sticky);
	}

	public function getFacing() : int{
		return $this->facing;
	}

	/** @return $this */
	public function setFacing(int $facing) : self{
		Facing::validate($facing);
		$this->facing = $facing;
		return $this;
	}

	public function isSticky() : bool{
		return $this->sticky;
	}

	/** @return $this */
	public function setSticky(bool $sticky) : self{
		$this->sticky = $sticky;
		return $this;
	}

	public function getDrops(Item $item) : array{
		return [];
	}

	public function getDropsForCompatibleTool(Item $item) : array{
		return [];
	}

	public function isSolid() : bool{
		return false;
	}

	protected function recalculateCollisionBoxes() : array{
		return match($this->facing){
			Facing::DOWN => [new AxisAlignedBB(0, 0, 0, 1, 0.25, 1), new AxisAlignedBB(0.375, 0.25, 0.375, 0.625, 1, 0.625)],
			Facing::UP => [new AxisAlignedBB(0, 0.75, 0, 1, 1, 1), new AxisAlignedBB(0.375, 0, 0.375, 0.625, 0.75, 0.625)],
			Facing::NORTH => [new AxisAlignedBB(0, 0, 0, 1, 1, 0.25), new AxisAlignedBB(0.375, 0.375, 0.25, 0.625, 0.625, 1)],
			Facing::SOUTH => [new AxisAlignedBB(0, 0, 0.75, 1, 1, 1), new AxisAlignedBB(0.375, 0.375, 0, 0.625, 0.625, 0.75)],
			Facing::WEST => [new AxisAlignedBB(0, 0, 0, 0.25, 1, 1), new AxisAlignedBB(0.25, 0.375, 0.375, 1, 0.625, 0.625)],
			Facing::EAST => [new AxisAlignedBB(0.75, 0, 0, 1, 1, 1), new AxisAlignedBB(0, 0.375, 0.375, 0.75, 0.625, 0.625)]
		};
	}

	public function readStateFromWorld() : Block{
		$this->movementOffset = null;
		$world = $this->position->getWorld();
		$base = $this->position->getSide(Facing::opposite($this->facing));
		if($world->isChunkLoaded($base->getFloorX() >> 4, $base->getFloorZ() >> 4)){
			$tile = $world->getTile($base);
			if($tile instanceof PistonArm && $tile->isMoving()){
				[$x, $y, $z] = Facing::OFFSET[$this->facing];
				$this->movementOffset = new Vector3($x * ($tile->getProgress() - 1.0), $y * ($tile->getProgress() - 1.0), $z * ($tile->getProgress() - 1.0));
			}
		}
		return $this;
	}

	public function getModelPositionOffset() : ?Vector3{
		return $this->movementOffset;
	}

	public function getSupportType(int $facing) : utils\SupportType{
		return $facing === $this->facing ? utils\SupportType::FULL : utils\SupportType::NONE;
	}

	public function getAffectedBlocks() : array{
		if($this->position->isValid()){
			$basePos = $this->position->getSide(Facing::opposite($this->facing));
			$world = $this->position->getWorld();
			if(!$world->isInWorld($basePos->getFloorX(), $basePos->getFloorY(), $basePos->getFloorZ()) || !$world->isChunkLoaded($basePos->getFloorX() >> 4, $basePos->getFloorZ() >> 4)){
				return parent::getAffectedBlocks();
			}
			$base = $world->getBlock($basePos);
			if($base instanceof Piston && $base->getFacing() === $this->facing && $base->isSticky() === $this->sticky){
				return [$this, $base];
			}
		}

		return parent::getAffectedBlocks();
	}

	public function onNearbyBlockChange() : void{
		if($this->position->isValid()){
			$basePos = $this->position->getSide(Facing::opposite($this->facing));
			$world = $this->position->getWorld();
			if(!$world->isInWorld($basePos->getFloorX(), $basePos->getFloorY(), $basePos->getFloorZ()) || !$world->isChunkLoaded($basePos->getFloorX() >> 4, $basePos->getFloorZ() >> 4)){
				return;
			}
			$base = $world->getBlock($basePos);
			if(!$base instanceof Piston || $base->getFacing() !== $this->facing || $base->isSticky() !== $this->sticky){
				$this->position->getWorld()->useBreakOn($this->position);
			}
		}
	}
}
