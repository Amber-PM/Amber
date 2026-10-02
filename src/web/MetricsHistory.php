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

use pocketmine\stats\ServerMetrics;
use function array_shift;
use function count;
use function round;

/**
 * Samples of the main server numbers over the last hour, for the panel's charts.
 */
final class MetricsHistory{
	/** Seconds between samples. */
	public const INTERVAL = 5;
	public const SAMPLES = 720;

	/** @var list<array{int, float, float, int, int}> time, TPS, load %, players, main thread memory */
	private array $samples = [];

	public function add(int $time, ServerMetrics $metrics) : void{
		$this->samples[] = [$time, round($metrics->tps, 2), round($metrics->tickUsage, 1), $metrics->playerCount, $metrics->mainThreadMemory];
		if(count($this->samples) > self::SAMPLES){
			array_shift($this->samples);
		}
	}

	/**
	 * @return array{interval: int, time: list<int>, tps: list<float>, load: list<float>, players: list<int>, memory: list<int>}
	 */
	public function toArray() : array{
		$series = ["interval" => self::INTERVAL, "time" => [], "tps" => [], "load" => [], "players" => [], "memory" => []];
		foreach($this->samples as [$time, $tps, $load, $players, $memory]){
			$series["time"][] = $time;
			$series["tps"][] = $tps;
			$series["load"][] = $load;
			$series["players"][] = $players;
			$series["memory"][] = $memory;
		}
		return $series;
	}
}
