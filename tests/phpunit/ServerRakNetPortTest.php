<?php

declare(strict_types=1);

namespace pocketmine;

use PHPUnit\Framework\TestCase;

final class ServerRakNetPortTest extends TestCase{
	public function testDefaultFollowsServerPorts() : void{
		self::assertSame([19132, 19133], Server::resolveRakNetPorts(0, 19132, 19133));
	}

	public function testExplicitPortIsUsedForBothFamilies() : void{
		self::assertSame([19140, 19140], Server::resolveRakNetPorts(19140, 25057, 19133));
	}

	public function testMinusOneDisablesRakNet() : void{
		self::assertNull(Server::resolveRakNetPorts(-1, 25057, 19133));
	}

	/** @return iterable<string, array{int}> */
	public static function invalidPorts() : iterable{
		yield 'below -1' => [-2];
		yield 'above maximum' => [65536];
	}

	/** @dataProvider invalidPorts */
	public function testInvalidPortIsRejected(int $configured) : void{
		$this->expectException(\InvalidArgumentException::class);
		Server::resolveRakNetPorts($configured, 19132, 19133);
	}
}
