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

namespace pocketmine\world\spawner;

enum SpawnCategory : string{
	case MONSTER = "monster";
	case ANIMAL = "animal";
	case WATER = "water";
	case AMBIENT = "ambient";

	/**
	 * Default vanilla mob cap per player for this category.
	 */
	public function getDefaultCap() : int{
		return match($this){
			self::MONSTER => 70,
			self::ANIMAL => 10,
			self::WATER => 5,
			self::AMBIENT => 15,
		};
	}
}
