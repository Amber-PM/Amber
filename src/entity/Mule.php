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

namespace pocketmine\entity;

use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use function mt_rand;

class Mule extends ChestedHorse{

	public static function getNetworkTypeId() : string{
		return EntityIds::MULE;
	}

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(1.6, 1.3965);
	}

	public function getName() : string{
		return "Mule";
	}

	protected function addAttributes() : void{
		parent::addAttributes();
		$hp = (float) mt_rand(15, 30);
		$this->setMaxHealth((int) $hp);
		$this->setHealth($hp);
		$this->jumpStrength = 0.5;
		$this->speedMultiplier = 0.175;
	}

	public function canBreed() : bool{
		// Mules are sterile and cannot breed
		return false;
	}
}
