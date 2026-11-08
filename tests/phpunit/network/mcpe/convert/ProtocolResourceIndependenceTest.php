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

namespace pocketmine\network\mcpe\convert;

use PHPUnit\Framework\TestCase;
use function fclose;
use function php_ini_loaded_file;
use function proc_close;
use function proc_open;
use function stream_get_contents;
use const PHP_BINARY;

final class ProtocolResourceIndependenceTest extends TestCase{
	/** @return iterable<string, array{int}> */
	public static function defaults() : iterable{
		yield 'previous default' => [2169];
		yield 'released 26.50 default' => [2193];
		yield 'released 26.60 default' => [2225];
		yield '26.50 preview as default' => [2192];
		yield 'future default' => [9999];
	}

	/** @dataProvider defaults */
	public function testResourcesAdmissionAndWorldLimitDoNotFollowDefault(int $default) : void{
		$command = [PHP_BINARY, '-c', php_ini_loaded_file(), '-dmemory_limit=2G', __DIR__ . '/fixtures/check-protocol-resources.php', (string) $default];
		$process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['redirect', 1]], $pipes);
		self::assertIsResource($process);
		$output = stream_get_contents($pipes[1]);
		fclose($pipes[1]);
		self::assertSame(0, proc_close($process), $output);
		self::assertSame("Validated 33 protocols\n", $output);
	}
}
