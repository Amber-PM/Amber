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

namespace pocketmine;

use PHPUnit\Framework\TestCase;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use function in_array;

final class ServerProtocolPreloadTest extends TestCase{

	private static function select(array $accepted, array $allowed, \Closure $isAllowed) : array{
		return (new \ReflectionMethod(Server::class, 'getProtocolsToPreload'))->invoke(null, $accepted, $allowed, $isAllowed);
	}

	public function testEmptyAllowedListPreloadsNothingAndNeverConsultsTheFilter() : void{
		$calls = 0;
		$result = self::select(ProtocolInfo::ACCEPTED_PROTOCOL, [], function(int $protocolId) use (&$calls) : bool{
			$calls++;
			return true;
		});

		self::assertSame([], $result);
		self::assertSame(0, $calls);
	}

	public function testExplicitListPreloadsOnlyAllowedProtocolsInAcceptedOrder() : void{
		$result = self::select([589, 594, 618, 622], [622, 589], fn(int $protocolId) : bool => in_array($protocolId, [622, 589], true));

		self::assertSame([589, 622], $result);
	}

	public function testDisabledProtocolsAreExcludedEvenWhenListed() : void{
		$result = self::select([589, 594, 618], [589, 594, 618], fn(int $protocolId) : bool => $protocolId !== 594);

		self::assertSame([589, 618], $result);
	}

	public function testProtocolsOutsideTheAcceptedListAreIgnored() : void{
		$result = self::select(ProtocolInfo::ACCEPTED_PROTOCOL, [2169, 4242], fn(int $protocolId) : bool => in_array($protocolId, [2169, 4242], true));

		self::assertSame([2169], $result);
	}

	public function testListingEveryAcceptedProtocolPreloadsEveryOne() : void{
		$result = self::select(ProtocolInfo::ACCEPTED_PROTOCOL, ProtocolInfo::ACCEPTED_PROTOCOL, fn(int $protocolId) : bool => true);

		self::assertSame(ProtocolInfo::ACCEPTED_PROTOCOL, $result);
	}
}