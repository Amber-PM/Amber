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

use pocketmine\command\CommandSender;
use pocketmine\command\OverloadedCommand;
use pocketmine\command\overload\StringArgumentParser;
use pocketmine\permission\DefaultPermissions;
use pocketmine\permission\Permission;
use pocketmine\permission\PermissionManager;
use pocketmine\player\Player;
use pocketmine\stats\ServerMetrics;
use pocketmine\utils\Random;
use pocketmine\utils\TextFormat as TF;
use pocketmine\world\format\Chunk;
use pocketmine\world\generator\structure\Structure;
use pocketmine\world\generator\structure\StructureLoot;
use pocketmine\world\generator\structure\StructurePopulator;
use pocketmine\world\World;
use function array_keys;
use function count;
use function implode;
use function intdiv;
use function mt_rand;
use function number_format;
use function round;
use function sprintf;

final class AmberCommand extends OverloadedCommand{
	public const PERMISSION = "pocketmine.command.amber";

	public function __construct(){
		parent::__construct("amber", "AmberPM tools: performance and structures");
		$operator = PermissionManager::getInstance()->getPermission(DefaultPermissions::ROOT_OPERATOR);
		if(PermissionManager::getInstance()->getPermission(self::PERMISSION) === null && $operator !== null){
			DefaultPermissions::registerPermission(new Permission(self::PERMISSION, "Allows the user to use /amber"), [$operator]);
		}
		$this->setPermission(self::PERMISSION);

		$structures = new StringArgumentParser(array_keys(StructurePopulator::defaultStructures()));
		$this->addOverload(fn(CommandSender $sender) => $this->perf($sender));
		$this->addOverload(
			fn(CommandSender $sender, string $action) => $action === "perf" ? $this->perf($sender) : $this->listStructures($sender),
			null,
			["action" => new StringArgumentParser(["perf", "structure"])]
		);
		$this->addOverload(
			fn(Player $sender, string $action, string $structure) => $this->placeStructure($sender, $structure, $sender->getPosition()->getFloorX(), $sender->getPosition()->getFloorZ(), $sender->getWorld()),
			null,
			["action" => new StringArgumentParser(["structure"]), "structure" => $structures]
		);
		$this->addOverload(
			fn(CommandSender $sender, string $action, string $structure, int $x, int $z, ?string $world = null) => $this->placeStructureInWorld($sender, $structure, $x, $z, $world),
			null,
			["action" => new StringArgumentParser(["structure"]), "structure" => $structures]
		);
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

		if($m->pathSearches["async"] + $m->pathSearches["sync"] > 0){
			$sender->sendMessage(sprintf("%sAdd-on path searches: %s in async workers, %s on the main thread",
				TF::GRAY, number_format($m->pathSearches["async"]), number_format($m->pathSearches["sync"])));
		}

		if($m->scripts !== null && $m->scripts["running"]){
			$sender->sendMessage(sprintf("%sAdd-on scripts: %.2f ms/tick average of a %d ms budget%s",
				TF::GRAY, $m->scripts["averageTickMs"], $m->scripts["budgetMs"], $m->scripts["busy"] ? TF::RED . " (over budget)" : ""));
		}
		return true;
	}


	private function listStructures(CommandSender $sender) : bool{
		$sender->sendMessage(TF::GRAY . "Structures: " . implode(", ", array_keys(StructurePopulator::defaultStructures())));
		return true;
	}

	private function placeStructureInWorld(CommandSender $sender, string $structure, int $x, int $z, ?string $worldName) : bool{
		$worldManager = $sender->getServer()->getWorldManager();
		$world = $worldName !== null ? $worldManager->getWorldByName($worldName) : ($sender instanceof Player ? $sender->getWorld() : $worldManager->getDefaultWorld());
		if($world === null){
			$sender->sendMessage(TF::RED . "No loaded world is called " . ($worldName ?? ""));
			return true;
		}
		return $this->placeStructure($sender, $structure, $x, $z, $world);
	}

	private function placeStructure(CommandSender $sender, string $name, int $x, int $z, World $world) : bool{
		$structure = StructurePopulator::defaultStructures()[$name];
		$reach = Structure::MAX_RADIUS;
		for($chunkX = ($x - $reach) >> Chunk::COORD_BIT_SIZE; $chunkX <= ($x + $reach) >> Chunk::COORD_BIT_SIZE; ++$chunkX){
			for($chunkZ = ($z - $reach) >> Chunk::COORD_BIT_SIZE; $chunkZ <= ($z + $reach) >> Chunk::COORD_BIT_SIZE; ++$chunkZ){
				if($world->loadChunk($chunkX, $chunkZ) === null){
					$sender->sendMessage(TF::RED . "The terrain around $x, $z is not generated yet");
					return true;
				}
			}
		}
		$random = new Random(mt_rand());
		if($structure->place($world, $x, $z, $random)){
			if(StructureLoot::hasLoot($name)){
				StructureLoot::fillChests($world, $x, $z, $name, $random, true);
			}
			$sender->sendMessage(TF::GREEN . "Placed $name at $x, $z");
		}else{
			$sender->sendMessage(TF::YELLOW . "The ground at $x, $z does not suit $name");
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
