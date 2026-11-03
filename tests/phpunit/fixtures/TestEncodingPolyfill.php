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

namespace pmmp\encoding {

	use function pack;
	use function unpack;

	if(!class_exists(BE::class, false)){
		class BE{
			public static function unpackSignedLong(string $b) : int{
				/** @var int $val */
				$val = unpack("J", $b)[1];
				return $val;
			}

			public static function packSignedLong(int $v) : string{
				return pack("J", $v);
			}

			public static function packSignedInt(int $v) : string{
				return pack("N", $v);
			}

			public static function unpackSignedInt(string $b) : int{
				/** @var int $val */
				$val = unpack("N", $b)[1];
				return $val >= 0x80000000 ? $val - 0x100000000 : $val;
			}
		}
	}

	if(!class_exists(LE::class, false)){
		class LE{
			public static function packSignedLong(int $v) : string{
				return pack("P", $v);
			}

			public static function unpackSignedLong(string $b) : int{
				/** @var int $val */
				$val = unpack("P", $b)[1];
				return $val;
			}

			public static function packSignedInt(int $v) : string{
				return pack("V", $v);
			}

			public static function unpackSignedInt(string $b) : int{
				/** @var int $val */
				$val = unpack("V", $b)[1];
				return $val >= 0x80000000 ? $val - 0x100000000 : $val;
			}
		}
	}
}

namespace {
	if(!function_exists('morton2d_encode')){
		function morton2d_encode(int $x, int $y) : int{
			$res = 0;
			for($i = 0; $i < 32; ++$i){
				$res |= (($x >> $i) & 1) << (2 * $i);
				$res |= (($y >> $i) & 1) << (2 * $i + 1);
			}
			return $res;
		}
	}

	if(!function_exists('morton2d_decode')){
		function morton2d_decode(int $m) : array{
			$x = 0;
			$y = 0;
			for($i = 0; $i < 32; ++$i){
				$x |= (($m >> (2 * $i)) & 1) << $i;
				$y |= (($m >> (2 * $i + 1)) & 1) << $i;
			}
			return [$x, $y];
		}
	}

	if(!function_exists('morton3d_encode')){
		function morton3d_encode(int $x, int $y, int $z) : int{
			$res = 0;
			for($i = 0; $i < 21; ++$i){
				$res |= (($x >> $i) & 1) << (3 * $i);
				$res |= (($y >> $i) & 1) << (3 * $i + 1);
				$res |= (($z >> $i) & 1) << (3 * $i + 2);
			}
			return $res;
		}
	}

	if(!function_exists('morton3d_decode')){
		function morton3d_decode(int $m) : array{
			$x = 0;
			$y = 0;
			$z = 0;
			for($i = 0; $i < 21; ++$i){
				$x |= (($m >> (3 * $i)) & 1) << $i;
				$y |= (($m >> (3 * $i + 1)) & 1) << $i;
				$z |= (($m >> (3 * $i + 2)) & 1) << $i;
			}
			return [$x, $y, $z];
		}
	}
}
