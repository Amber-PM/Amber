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

use pocketmine\block\utils\FacesOppositePlacingPlayerTrait;
use pocketmine\block\utils\HorizontalFacing;
use pocketmine\block\utils\SupportType;
use pocketmine\data\runtime\RuntimeDataDescriber;
use pocketmine\math\AxisAlignedBB;

final class DriedGhast extends Transparent implements HorizontalFacing{
	use FacesOppositePlacingPlayerTrait { describeBlockOnlyState as private describeFacing; }

	private int $rehydrationLevel = 0;

	protected function describeBlockOnlyState(RuntimeDataDescriber $w) : void{
		$this->describeFacing($w);
		$w->boundedIntAuto(0, 3, $this->rehydrationLevel);
	}

	public function getRehydrationLevel() : int{
		return $this->rehydrationLevel;
	}

	public function setRehydrationLevel(int $level) : self{
		if($level < 0 || $level > 3){
			throw new \InvalidArgumentException("Invalid dried ghast rehydration level: $level");
		}
		$this->rehydrationLevel = $level;
		return $this;
	}

	public function getSupportType(int $facing) : SupportType{
		return SupportType::NONE;
	}

	protected function recalculateCollisionBoxes() : array{
		return [new AxisAlignedBB(0.125, 0, 0.125, 0.875, 0.75, 0.875)];
	}
}
