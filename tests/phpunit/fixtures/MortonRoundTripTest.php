<?php

declare(strict_types=1);

namespace pocketmine\fixtures;

use PHPUnit\Framework\TestCase;
use function morton2d_decode;
use function morton2d_encode;
use function morton3d_decode;
use function morton3d_encode;

require_once __DIR__ . "/TestEncodingPolyfill.php";

final class MortonRoundTripTest extends TestCase{

	public function testSigned2dCoordinates() : void{
		self::assertSame([-2147483648, -1], morton2d_decode(morton2d_encode(-2147483648, -1)));
		self::assertSame([2147483647, 2147483646], morton2d_decode(morton2d_encode(2147483647, 2147483646)));
	}

	public function testSigned3dCoordinates() : void{
		self::assertSame([-1048576, -1, -2], morton3d_decode(morton3d_encode(-1048576, -1, -2)));
		self::assertSame([1048575, 1048574, 1048573], morton3d_decode(morton3d_encode(1048575, 1048574, 1048573)));
	}
}
