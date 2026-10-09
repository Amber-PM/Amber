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

namespace pocketmine\block\dispenser;

use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use function cos;
use function log;
use function mt_getrandmax;
use function mt_rand;
use function sqrt;
use const M_PI;

final class DispenseMotion{
	private static function gaussian() : float{
		return sqrt(-2.0 * log(mt_rand(1, mt_getrandmax()) / mt_getrandmax())) * cos(2.0 * M_PI * mt_rand() / mt_getrandmax());
	}

	public static function item(int $facing) : Vector3{
		[$dx, , $dz] = Facing::OFFSET[$facing];
		$speed = 0.2 + mt_rand() / mt_getrandmax() * 0.1;
		return new Vector3($dx * $speed + self::gaussian() * 0.045, 0.2 + self::gaussian() * 0.045, $dz * $speed + self::gaussian() * 0.045);
	}

	public static function projectile(int $facing, bool $bottle) : Vector3{
		[$dx, $dy, $dz] = Facing::OFFSET[$facing];
		$direction = (new Vector3($dx, $dy + 0.1, $dz))->normalize();
		$spread = $bottle ? 0.0225 : 0.045;
		return $direction->add(self::gaussian() * $spread, self::gaussian() * $spread, self::gaussian() * $spread)->multiply($bottle ? 1.375 : 1.1);
	}
}
