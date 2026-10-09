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

namespace pocketmine\web;

use PHPUnit\Framework\TestCase;
use pocketmine\stats\ServerMetrics;
use function count;

if(!class_exists(\pmmp\thread\ThreadSafe::class)){
	class ThreadSafeMock{
		public function synchronized(\Closure $block, mixed ...$args) : mixed{
			return $block(...$args);
		}
	}
	class_alias(ThreadSafeMock::class, \pmmp\thread\ThreadSafe::class);
}
if(!class_exists(\pmmp\thread\ThreadSafeArray::class)){
	class ThreadSafeArrayMock extends \ArrayObject{}
	class_alias(ThreadSafeArrayMock::class, \pmmp\thread\ThreadSafeArray::class);
}

require_once __DIR__ . '/../../../src/stats/ServerMetrics.php';
require_once __DIR__ . '/../../../src/web/MetricsHistory.php';
require_once __DIR__ . '/../../../src/web/WebLogBuffer.php';

final class PanelBuffersTest extends TestCase{

	private static function metrics(float $tps, int $players) : ServerMetrics{
		return new ServerMetrics(10.0, $tps, $tps, 12.5, 12.5, 1000, 2000, 3000, 4, $players, 20, [], [], [], 2, [], [], 1, 1, null, ["async" => 0, "sync" => 0]);
	}

	public function testHistoryKeepsTheLastHour() : void{
		$history = new MetricsHistory();
		for($i = 0; $i < MetricsHistory::SAMPLES + 10; ++$i){
			$history->add($i * MetricsHistory::INTERVAL, self::metrics(20.0 - $i % 3, $i % 7));
		}
		$series = $history->toArray();
		self::assertCount(MetricsHistory::SAMPLES, $series["time"]);
		self::assertSame(10 * MetricsHistory::INTERVAL, $series["time"][0], "oldest samples dropped");
		self::assertCount(MetricsHistory::SAMPLES, $series["tps"]);
		self::assertSame(1000, $series["memory"][0]);
		self::assertSame(12.5, $series["load"][0]);
	}

	public function testLogBufferKeepsItsCapacity() : void{
		$buffer = new WebLogBuffer(150);
		for($i = 0; $i < 400; ++$i){
			$buffer->log("info", "line $i");
		}
		[$lines, $next] = $buffer->getLinesAfter(0);
		self::assertSame(400, $next);
		self::assertCount(150, $lines);
		self::assertSame([250, "[info] line 250"], $lines[0]);

		[$newer] = $buffer->getLinesAfter(398);
		self::assertSame(2, count($newer));
	}

	public function testLogBufferCapacityIsBounded() : void{
		$small = new WebLogBuffer(1);
		for($i = 0; $i < 300; ++$i){
			$small->log("info", "x");
		}
		self::assertCount(WebLogBuffer::MIN_LINES, $small->getLinesAfter(0)[0]);
	}
}
