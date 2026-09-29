<?php

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketMine Team
 * @link http://www.pocketmine.net/
 *
 *
 */


declare(strict_types=1);

namespace pocketmine\stats;

use pocketmine\command\defaults\ProtocolsCommand;
use pocketmine\network\mcpe\cache\ChunkCache;
use pocketmine\network\mcpe\convert\BlockTranslator;
use pocketmine\network\mcpe\convert\ItemTypeDictionaryFromDataHelper;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\Server;
use pocketmine\utils\Process;
use function array_keys;
use function array_sum;
use function count;
use function ksort;
use function memory_get_peak_usage;
use function memory_get_usage;
use function microtime;
use function sort;

/**
 * A snapshot of server health, shared by /amber perf, the metrics endpoint and the admin panel.
 */
final class ServerMetrics{

	/**
	 * @param array<int, int>                                                                                   $playersByProtocol
	 * @param array<string, array{chunks: int, entities: int, players: int, tickMs: float}>                     $worlds
	 * @param list<array{world: string, bytes: int, chunks: int, hits: int, misses: int}>                       $chunkCaches
	 * @param array<int, int>                                                                                   $asyncQueues
	 * @param list<int>                                                                                         $loadedProtocols
	 * @param array{running: bool, busy: bool, averageTickMs: float, budgetMs: int}|null                         $scripts
	 */
	public function __construct(
		public readonly float $uptime,
		public readonly float $tps,
		public readonly float $tpsAverage,
		public readonly float $tickUsage,
		public readonly float $tickUsageAverage,
		public readonly int $mainThreadMemory,
		public readonly int $mainThreadPeakMemory,
		public readonly int $processMemory,
		public readonly int $threadCount,
		public readonly int $playerCount,
		public readonly int $maxPlayers,
		public readonly array $playersByProtocol,
		public readonly array $worlds,
		public readonly array $chunkCaches,
		public readonly int $asyncPoolSize,
		public readonly array $asyncQueues,
		public readonly array $loadedProtocols,
		public readonly int $loadedPalettes,
		public readonly int $loadedItemLists,
		public readonly ?array $scripts
	){}

	public static function collect(Server $server) : self{
		$playersByProtocol = ProtocolsCommand::countPlayersByProtocol($server->getOnlinePlayers());
		ksort($playersByProtocol);

		$worlds = [];
		foreach($server->getWorldManager()->getWorlds() as $world){
			$worlds[$world->getFolderName()] = [
				"chunks" => count($world->getLoadedChunks()),
				"entities" => count($world->getEntities()),
				"players" => count($world->getPlayers()),
				"tickMs" => $world->getTickRateTime(),
			];
		}

		$chunkCaches = [];
		foreach(ChunkCache::getAllInstances() as $cache){
			$world = $cache->getWorld();
			$chunkCaches[] = [
				"world" => $world->isLoaded() ? $world->getFolderName() : "(unloaded)",
				"bytes" => $cache->calculateCacheSize(),
				"chunks" => $cache->getCachedChunkCount(),
				"hits" => $cache->getHits(),
				"misses" => $cache->getMisses(),
			];
		}

		$scriptHost = $server->getAddonManager()->getScriptHost();
		$scripts = $scriptHost === null ? null : [
			"running" => $scriptHost->isRunning(),
			"busy" => $scriptHost->isBusy(),
			"averageTickMs" => $scriptHost->getAverageTickMs(),
			"budgetMs" => $scriptHost->getTickBudgetMs(),
		];

		$asyncPool = $server->getAsyncPool();
		$loadedProtocols = array_keys(TypeConverter::getAll());
		sort($loadedProtocols);

		return new self(
			microtime(true) - $server->getStartTime(),
			$server->getTicksPerSecond(),
			$server->getTicksPerSecondAverage(),
			$server->getTickUsage(),
			$server->getTickUsageAverage(),
			memory_get_usage(),
			memory_get_peak_usage(),
			Process::getAdvancedMemoryUsage()[1],
			Process::getThreadCount(),
			count($server->getOnlinePlayers()),
			$server->getMaxPlayers(),
			$playersByProtocol,
			$worlds,
			$chunkCaches,
			$asyncPool->getSize(),
			$asyncPool->getTaskQueueSizes(),
			$loadedProtocols,
			BlockTranslator::getLoadedCount(),
			ItemTypeDictionaryFromDataHelper::getLoadedCount(),
			$scripts
		);
	}

	public function getQueuedAsyncTasks() : int{
		return array_sum($this->asyncQueues);
	}
}
