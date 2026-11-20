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

namespace pocketmine\fixtures;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use function morton2d_decode;
use function morton2d_encode;
use function morton3d_decode;
use function morton3d_encode;

require_once __DIR__ . "/TestEncodingPolyfill.php";

final class MortonEncodingTest extends TestCase{

	/** @return iterable<string, array{int, int, int}> */
	public static function coordinates() : iterable{
		yield "nonnegative" => [0, 1, 42];
		yield "mixed signs" => [-1, 12, -7];
		yield "negative Y" => [42, -14, 0];
		yield "21-bit limits" => [-1048576, 1048575, -1048576];
	}

	#[DataProvider("coordinates")]
	public function testSignedRoundTrip(int $x, int $y, int $z) : void{
		self::assertSame([$x, $y], morton2d_decode(morton2d_encode($x, $y)));
		self::assertSame([$x, $y, $z], morton3d_decode(morton3d_encode($x, $y, $z)));
	}

	public function test2dSignedLimits() : void{
		self::assertSame([-2147483648, 2147483647], morton2d_decode(morton2d_encode(-2147483648, 2147483647)));
	}
}
