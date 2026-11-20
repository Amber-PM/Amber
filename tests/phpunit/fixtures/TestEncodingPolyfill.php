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

namespace pmmp\encoding;

use function pack;
use function unpack;

if(!class_exists(BE::class)){
	class BE{
		public static function unpackSignedLong(string $b) : int{
			/** @var int $val */
			$val = unpack("J", $b)[1];
			return $val;
		}

		public static function packSignedLong(int $v) : string{
			return pack("J", $v);
		}
	}
}

if(!class_exists(LE::class)){
	class LE{
		public static function packSignedLong(int $v) : string{
			return pack("P", $v);
		}

		public static function unpackSignedLong(string $b) : int{
			/** @var int $val */
			$val = unpack("P", $b)[1];
			return $val;
		}
	}
}
