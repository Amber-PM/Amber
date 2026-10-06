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

namespace pocketmine\world\sound;

use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\LevelSoundEventPacket;
use pocketmine\network\mcpe\protocol\types\LevelSoundEvent;

class CrossbowLoadStartSound implements Sound{

	public function __construct(
		private bool $quickCharge = false
	){}

	public function isQuickCharge() : bool{
		return $this->quickCharge;
	}

	public function encode(Vector3 $pos) : array{
		$sound = $this->quickCharge ? LevelSoundEvent::CROSSBOW_QUICK_CHARGE_START : LevelSoundEvent::CROSSBOW_LOADING_START;
		return [LevelSoundEventPacket::nonActorSound($sound, $pos, false)];
	}
}
