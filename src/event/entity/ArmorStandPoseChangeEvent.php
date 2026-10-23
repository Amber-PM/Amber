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

namespace pocketmine\event\entity;

use pocketmine\entity\object\ArmorStand;
use pocketmine\event\Cancellable;
use pocketmine\event\CancellableTrait;
use pocketmine\player\Player;

/**
 * Called when an armor stand's pose is changed by a player or plugin.
 *
 * @phpstan-extends EntityEvent<ArmorStand>
 */
class ArmorStandPoseChangeEvent extends EntityEvent implements Cancellable{
	use CancellableTrait;

	public function __construct(
		ArmorStand $armorStand,
		private ?Player $player,
		private int $oldPose,
		private int $newPose
	){
		$this->entity = $armorStand;
	}

	public function getArmorStand() : ArmorStand{
		/** @var ArmorStand $armorStand */
		$armorStand = $this->entity;
		return $armorStand;
	}

	/**
	 * @return ArmorStand
	 */
	public function getEntity(){
		return $this->entity;
	}

	/**
	 * Returns the player changing the pose, or null if triggered programmatically.
	 */
	public function getPlayer() : ?Player{
		return $this->player;
	}

	public function getOldPose() : int{
		return $this->oldPose;
	}

	public function getNewPose() : int{
		return $this->newPose;
	}

	public function setNewPose(int $newPose) : void{
		$this->newPose = $newPose;
	}
}
