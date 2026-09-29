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

namespace pocketmine\command\defaults;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\utils\InvalidCommandSyntaxException;
use pocketmine\permission\DefaultPermissions;
use pocketmine\permission\Permission;
use pocketmine\permission\PermissionManager;
use pocketmine\stats\ServerMetrics;
use pocketmine\utils\TextFormat as TF;
use function count;
use function implode;
use function intdiv;
use function number_format;
use function round;
use function sprintf;

final class AmberCommand extends Command{
	public const PERMISSION = "pocketmine.command.amber";

	public function __construct(){
		parent::__construct("amber", "AmberPM tools", "/amber perf");
		$operator = PermissionManager::getInstance()->getPermission(DefaultPermissions::ROOT_OPERATOR);
		if(PermissionManager::getInstance()->getPermission(self::PERMISSION) === null && $operator !== null){
			DefaultPermissions::registerPermission(new Permission(self::PERMISSION, "Allows the user to use /amber"), [$operator]);
		}
		$this->setPermission(self::PERMISSION);
	}

	public function execute(CommandSender $sender, string $commandLabel, array $args) : bool{
		return match($args[0] ?? "perf"){
			"perf" => $this->perf($sender),
			default => throw new InvalidCommandSyntaxException(),
		};
	}

	private function perf(CommandSender $sender) : bool{
		$m = ServerMetrics::collect($sender->getServer());

		$sender->sendMessage(TF::GOLD . "--- AmberPM performance ---");
		$sender->sendMessage(sprintf("%sTPS: %s%.2f %s(avg %.2f), load %.1f%% (avg %.1f%%), uptime %s",
			TF::GRAY, TF::WHITE, $m->tps, TF::GRAY, $m->tpsAverage, $m->tickUsage, $m->tickUsageAverage, self::duration($m->uptime)));
		$sender->sendMessage(sprintf("%sMemory: main thread %s%s %s(peak %s), process %s, %d threads",
			TF::GRAY, TF::WHITE, self::bytes($m->mainThreadMemory), TF::GRAY, self::bytes($m->mainThreadPeakMemory), self::bytes($m->processMemory), $m->threadCount));

		$byProtocol = [];
		foreach($m->playersByProtocol as $protocolId => $count){
			$byProtocol[] = ProtocolsCommand::getVersionLabel($protocolId) . " ($protocolId): $count";
		}
		$sender->sendMessage(TF::GRAY . "Players: " . TF::WHITE . $m->playerCount . "/" . $m->maxPlayers . ($byProtocol === [] ? "" : TF::GRAY . " - " . implode(", ", $byProtocol)));

		$sender->sendMessage(sprintf("%sProtocol tables: %d protocol(s) loaded, sharing %d block palette(s) and %d item list(s)",
			TF::GRAY, count($m->loadedProtocols), $m->loadedPalettes, $m->loadedItemLists));

		foreach($m->worlds as $name => $world){
			$sender->sendMessage(sprintf("%sWorld %s%s%s: %d chunks, %d entities, %d players, %.2f ms/tick",
				TF::GRAY, TF::WHITE, $name, TF::GRAY, $world["chunks"], $world["entities"], $world["players"], $world["tickMs"]));
		}

		$cacheBytes = 0;
		$cacheChunks = 0;
		$hits = 0;
		$misses = 0;
		foreach($m->chunkCaches as $cache){
			$cacheBytes += $cache["bytes"];
			$cacheChunks += $cache["chunks"];
			$hits += $cache["hits"];
			$misses += $cache["misses"];
		}
		$requests = $hits + $misses;
		$sender->sendMessage(sprintf("%sChunk cache: %s for %d chunk(s), %s%% hits (%s requests)",
			TF::GRAY, self::bytes($cacheBytes), $cacheChunks, $requests > 0 ? number_format($hits / $requests * 100, 1) : "0.0", number_format($requests)));

		$sender->sendMessage(sprintf("%sAsync workers: %d running of %d, %d task(s) queued",
			TF::GRAY, count($m->asyncQueues), $m->asyncPoolSize, $m->getQueuedAsyncTasks()));

		if($m->scripts !== null && $m->scripts["running"]){
			$sender->sendMessage(sprintf("%sAdd-on scripts: %.2f ms/tick average of a %d ms budget%s",
				TF::GRAY, $m->scripts["averageTickMs"], $m->scripts["budgetMs"], $m->scripts["busy"] ? TF::RED . " (over budget)" : ""));
		}
		return true;
	}

	public static function bytes(int $bytes) : string{
		return match(true){
			$bytes >= 1024 ** 3 => round($bytes / 1024 ** 3, 2) . " GB",
			$bytes >= 1024 ** 2 => round($bytes / 1024 ** 2, 1) . " MB",
			$bytes >= 1024 => round($bytes / 1024, 1) . " KB",
			default => $bytes . " B",
		};
	}

	public static function duration(float $seconds) : string{
		$s = (int) $seconds;
		return sprintf("%dd %02dh %02dm %02ds", intdiv($s, 86400), intdiv($s, 3600) % 24, intdiv($s, 60) % 60, $s % 60);
	}
}
