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

use pocketmine\command\defaults\ProtocolsCommand;
use pocketmine\stats\ServerMetrics;
use function count;
use function implode;
use function is_finite;
use function is_int;
use function sprintf;
use function str_replace;

/**
 * Renders server metrics in the Prometheus text exposition format.
 */
final class PrometheusExporter{
	/** @var list<string> */
	private array $lines = [];

	private function __construct(){}

	public static function render(ServerMetrics $m) : string{
		$e = new self();
		$e->metric("amber_uptime_seconds", "gauge", "Seconds since the server started", $m->uptime);
		$e->metric("amber_tps", "gauge", "Ticks per second over the last second", $m->tps);
		$e->metric("amber_tps_average", "gauge", "Ticks per second, averaged", $m->tpsAverage);
		$e->metric("amber_tick_usage_percent", "gauge", "Share of each tick's time budget used, averaged", $m->tickUsageAverage);
		$e->metric("amber_main_thread_memory_bytes", "gauge", "Memory used by the main thread", $m->mainThreadMemory);
		$e->metric("amber_process_memory_bytes", "gauge", "Resident memory of the server process", $m->processMemory);
		$e->metric("amber_threads", "gauge", "Threads in the server process", $m->threadCount);
		$e->metric("amber_players_online", "gauge", "Players online", $m->playerCount);
		$e->metric("amber_players_max", "gauge", "Player slots", $m->maxPlayers);

		$e->header("amber_players_by_protocol", "gauge", "Players online by client protocol");
		foreach($m->playersByProtocol as $protocolId => $count){
			$e->sample("amber_players_by_protocol", ["protocol" => (string) $protocolId, "version" => ProtocolsCommand::getVersionLabel($protocolId)], $count);
		}

		$e->header("amber_world_loaded_chunks", "gauge", "Loaded chunks per world");
		foreach($m->worlds as $name => $world){
			$e->sample("amber_world_loaded_chunks", ["world" => (string) $name], $world["chunks"]);
		}
		$e->header("amber_world_entities", "gauge", "Entities per world");
		foreach($m->worlds as $name => $world){
			$e->sample("amber_world_entities", ["world" => (string) $name], $world["entities"]);
		}
		$e->header("amber_world_players", "gauge", "Players per world");
		foreach($m->worlds as $name => $world){
			$e->sample("amber_world_players", ["world" => (string) $name], $world["players"]);
		}
		$e->header("amber_world_tick_milliseconds", "gauge", "Time the last tick of each world took");
		foreach($m->worlds as $name => $world){
			$e->sample("amber_world_tick_milliseconds", ["world" => (string) $name], $world["tickMs"]);
		}

		$bytes = $chunks = $hits = $misses = 0;
		foreach($m->chunkCaches as $cache){
			$bytes += $cache["bytes"];
			$chunks += $cache["chunks"];
			$hits += $cache["hits"];
			$misses += $cache["misses"];
		}
		$e->metric("amber_chunk_cache_bytes", "gauge", "Size of the cached chunk packets", $bytes);
		$e->metric("amber_chunk_cache_chunks", "gauge", "Chunks with cached packets", $chunks);
		$e->metric("amber_chunk_cache_hits_total", "counter", "Chunk requests answered from the cache", $hits);
		$e->metric("amber_chunk_cache_misses_total", "counter", "Chunk requests that needed a new packet", $misses);

		$e->metric("amber_async_workers", "gauge", "Running async workers", count($m->asyncQueues));
		$e->metric("amber_async_workers_max", "gauge", "Async worker pool size", $m->asyncPoolSize);
		$e->metric("amber_async_queued_tasks", "gauge", "Tasks waiting in async worker queues", $m->getQueuedAsyncTasks());
		$e->metric("amber_protocol_tables_loaded", "gauge", "Client protocols with loaded translation tables", count($m->loadedProtocols));
		$e->metric("amber_block_palettes_loaded", "gauge", "Distinct block palettes loaded", $m->loadedPalettes);

		$e->header("amber_addon_path_searches_total", "counter", "Add-on mob path searches by where they ran");
		$e->sample("amber_addon_path_searches_total", ["thread" => "async"], $m->pathSearches["async"]);
		$e->sample("amber_addon_path_searches_total", ["thread" => "main"], $m->pathSearches["sync"]);
		if($m->scripts !== null){
			$e->metric("amber_addon_scripts_running", "gauge", "Whether add-on scripts are running", $m->scripts["running"] ? 1 : 0);
			$e->metric("amber_addon_scripts_tick_milliseconds", "gauge", "Script time per tick, averaged", $m->scripts["averageTickMs"]);
		}
		return implode("\n", $e->lines) . "\n";
	}

	private function header(string $name, string $type, string $help) : void{
		$this->lines[] = "# HELP $name $help";
		$this->lines[] = "# TYPE $name $type";
	}

	private function metric(string $name, string $type, string $help, int|float $value) : void{
		$this->header($name, $type, $help);
		$this->sample($name, [], $value);
	}

	/**
	 * @param array<string, string> $labels
	 */
	private function sample(string $name, array $labels, int|float $value) : void{
		$parts = [];
		foreach($labels as $label => $labelValue){
			$parts[] = $label . '="' . str_replace(["\\", "\"", "\n"], ["\\\\", "\\\"", "\\n"], $labelValue) . '"';
		}
		$formatted = is_int($value) ? (string) $value : (is_finite($value) ? sprintf("%.6F", $value) : "NaN");
		$this->lines[] = $name . ($parts === [] ? "" : "{" . implode(",", $parts) . "}") . " " . $formatted;
	}
}
