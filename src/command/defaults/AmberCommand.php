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
use pocketmine\player\Player;
use pocketmine\stats\ServerMetrics;
use pocketmine\utils\Random;
use pocketmine\utils\TextFormat as TF;
use pocketmine\world\format\Chunk;
use pocketmine\world\generator\structure\Structure;
use pocketmine\world\generator\structure\StructureLoot;
use pocketmine\world\generator\structure\StructurePopulator;
use function array_keys;
use function basename;
use function count;
use function floor;
use function implode;
use function intdiv;
use function is_numeric;
use function mt_rand;
use function number_format;
use function round;
use function sprintf;

final class AmberCommand extends Command{
	public const PERMISSION = "pocketmine.command.amber";

	public function __construct(){
		parent::__construct("amber", "AmberPM tools", "/amber <perf|backup [world]|structure <name> [x z [world]]>");
		$operator = PermissionManager::getInstance()->getPermission(DefaultPermissions::ROOT_OPERATOR);
		if(PermissionManager::getInstance()->getPermission(self::PERMISSION) === null && $operator !== null){
			DefaultPermissions::registerPermission(new Permission(self::PERMISSION, "Allows the user to use /amber"), [$operator]);
		}
		$this->setPermission(self::PERMISSION);
	}

	public function execute(CommandSender $sender, string $commandLabel, array $args) : bool{
		return match($args[0] ?? "perf"){
			"perf" => $this->perf($sender),
			"backup" => $this->backup($sender, $args[1] ?? null),
			"structure" => $this->structure($sender, $args),
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

	private function backup(CommandSender $sender, ?string $worldName) : bool{
		$server = $sender->getServer();
		$worlds = $worldName !== null ? [$server->getWorldManager()->getWorldByName($worldName)] : $server->getWorldManager()->getWorlds();
		foreach($worlds as $world){
			if($world === null){
				$sender->sendMessage(TF::RED . "No loaded world is called $worldName");
				return true;
			}
			$name = $world->getFolderName();
			$started = $server->getBackupManager()->backup($world, function(?string $file, ?string $error) use ($sender, $name) : void{
				if($sender instanceof Player && !$sender->isConnected()){
					return;
				}
				$sender->sendMessage($file !== null ? TF::GREEN . "Backed up $name to " . basename($file) : TF::RED . "Backup of $name failed: $error");
			});
			$sender->sendMessage($started ? TF::GRAY . "Backing up $name..." : TF::YELLOW . "A backup of $name is already running");
		}
		return true;
	}

	/**
	 * @param string[] $args
	 */
	private function structure(CommandSender $sender, array $args) : bool{
		$structures = StructurePopulator::defaultStructures();
		$structure = $structures[$args[1] ?? ""] ?? null;
		if($structure === null){
			$sender->sendMessage(TF::GRAY . "Structures: " . implode(", ", array_keys($structures)));
			return true;
		}
		if(isset($args[2], $args[3])){
			if(!is_numeric($args[2]) || !is_numeric($args[3])){
				throw new InvalidCommandSyntaxException();
			}
			$x = (int) floor((float) $args[2]);
			$z = (int) floor((float) $args[3]);
			$world = isset($args[4]) ? $sender->getServer()->getWorldManager()->getWorldByName($args[4]) : ($sender instanceof Player ? $sender->getWorld() : $sender->getServer()->getWorldManager()->getDefaultWorld());
		}elseif($sender instanceof Player){
			$position = $sender->getPosition();
			$x = $position->getFloorX();
			$z = $position->getFloorZ();
			$world = $sender->getWorld();
		}else{
			throw new InvalidCommandSyntaxException();
		}
		if($world === null){
			$sender->sendMessage(TF::RED . "No loaded world is called " . ($args[4] ?? ""));
			return true;
		}

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
			if(StructureLoot::hasLoot($structure->getName())){
				StructureLoot::fillChests($world, $x, $z, $structure->getName(), $random, true);
			}
			$sender->sendMessage(TF::GREEN . "Placed " . $structure->getName() . " at $x, $z");
		}else{
			$sender->sendMessage(TF::YELLOW . "The ground at $x, $z does not suit " . $structure->getName());
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
