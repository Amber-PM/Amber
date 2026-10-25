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

use pocketmine\block\utils\RedstoneSource;
use pocketmine\data\runtime\RuntimeDataDescriber;
use pocketmine\entity\vehicle\Minecart;
use pocketmine\math\AxisAlignedBB;
use pocketmine\world\redstone\RedstoneEngine;

class DetectorRail extends StraightOnlyRail implements RedstoneSource{
	protected bool $activated = false;

	protected function describeBlockOnlyState(RuntimeDataDescriber $w) : void{
		parent::describeBlockOnlyState($w);
		$w->bool($this->activated);
	}

	public function isActivated() : bool{ return $this->activated; }

	/** @return $this */
	public function setActivated(bool $activated) : self{
		$this->activated = $activated;
		return $this;
	}

	public function onScheduledUpdate() : void{
		if($this->activated){
			$world = $this->position->getWorld();
			$box = AxisAlignedBB::one()->offset($this->position->x, $this->position->y, $this->position->z);
			$hasMinecart = false;
			foreach($world->getNearbyEntities($box) as $entity){
				if($entity instanceof Minecart && !$entity->isFlaggedForDespawn() && !$entity->isClosed()){
					$hasMinecart = true;
					break;
				}
			}

			if(!$hasMinecart){
				$this->activated = false;
				$world->setBlock($this->position, $this);
			}else{
				$world->scheduleDelayedBlockUpdate($this->position, 20);
			}
		}
	}

	public function onRedstoneScheduledUpdate(RedstoneEngine $engine) : void{
		$this->onScheduledUpdate();
	}

	public function getRedstoneOutput(int $face, bool $strongOnly, RedstoneEngine $engine) : int{
		return $this->activated ? 15 : 0;
	}
}
