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

namespace pocketmine\entity\raid;

use pocketmine\world\raid\Raid;

/**
 * Interface implemented by entities that participate in village raids.
 */
interface Raider{

	public function getRaid() : ?Raid;

	public function setRaid(?Raid $raid) : void;

	public function getWave() : int;

	public function setWave(int $wave) : void;

	public function isRaidCaptain() : bool;

	public function setRaidCaptain(bool $isCaptain) : void;

	public function isCelebrating() : bool;

	public function setCelebrating(bool $celebrating) : void;

	public function hasRaidGoal() : bool;
}
