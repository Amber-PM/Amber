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

namespace pocketmine\world\weather;

use function strtolower;

final class Weather{

	public const CLEAR = 0;
	public const RAIN = 1;
	public const THUNDER = 2;

	private function __construct(){
		// NOOP
	}

	public static function fromString(string $name) : ?int{
		return match(strtolower($name)){
			"clear", "c", "sun" => self::CLEAR,
			"rain", "r" => self::RAIN,
			"thunder", "t", "storm" => self::THUNDER,
			default => null,
		};
	}

	public static function toString(int $weather) : string{
		return match($weather){
			self::CLEAR => "clear",
			self::RAIN => "rain",
			self::THUNDER => "thunder",
			default => "unknown",
		};
	}
}
