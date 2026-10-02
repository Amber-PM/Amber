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

namespace pocketmine\reload;

use pocketmine\entity\Location;
use pocketmine\math\Vector3;
use pocketmine\Server;
use pocketmine\utils\Filesystem;
use pocketmine\world\generator\Flat;
use pocketmine\world\Position;
use pocketmine\world\World;
use pocketmine\world\WorldCreationOptions;
use pocketmine\world\WorldException;
use Symfony\Component\Filesystem\Path;
use function basename;
use function count;
use function date;
use function glob;
use function is_dir;
use function rename;
use function str_ends_with;
use function strlen;
use function substr;
use const GLOB_ONLYDIR;

/**
 * Swaps a world for new files while players stay online: put the new world in worlds/<name>.new/, and the world is
 * replaced by it. Players in the world wait in a small holding world for the moment of the swap, then go back to
 * where they were. The old files are kept as worlds/<name>.old-<time>/.
 */
final class WorldReloader{
	public const PENDING_SUFFIX = ".new";
	private const HOLDING_WORLD = "amber-reload-holding";

	public function __construct(private Server $server){}

	private function worldsPath() : string{
		return Path::join($this->server->getDataPath(), "worlds");
	}

	/** @return list<string> worlds with new files waiting in worlds/<name>.new/ */
	public function getPending() : array{
		$pending = [];
		foreach(glob(Path::join($this->worldsPath(), "*" . self::PENDING_SUFFIX), GLOB_ONLYDIR) ?: [] as $dir){
			$pending[] = substr(basename($dir), 0, -strlen(self::PENDING_SUFFIX));
		}
		return $pending;
	}

	/**
	 * @phpstan-param \Closure(ReloadReport) : void $onDone
	 */
	public function reload(string $name, \Closure $onDone) : void{
		$report = new ReloadReport();
		$worlds = $this->server->getWorldManager();
		$target = Path::join($this->worldsPath(), $name);
		$source = $target . self::PENDING_SUFFIX;
		if($name === "" || $name === self::HOLDING_WORLD || str_ends_with($name, self::PENDING_SUFFIX) || basename($name) !== $name){
			$report->error("\"$name\" is not a world folder name");
			$onDone($report);
			return;
		}
		if(!is_dir($source)){
			$report->error("Put the new files of $name in worlds/$name" . self::PENDING_SUFFIX . "/ first");
			$onDone($report);
			return;
		}

		$world = $worlds->getWorldByName($name);
		if($world === null){
			$this->swapFolders($name, $report);
			$onDone($report);
			return;
		}

		$holding = $this->getHoldingWorld();
		if($holding === null){
			$report->error("The holding world for players could not be created, see the console");
			$onDone($report);
			return;
		}
		$holding->requestSafeSpawn()->onCompletion(
			function(Position $spawn) use ($world, $holding, $name, $report, $onDone) : void{
				$this->swapLoadedWorld($world, $holding, $spawn, $name, $report);
				$onDone($report);
			},
			function() use ($holding, $report, $onDone) : void{
				$this->dropHoldingWorld($holding);
				$report->error("The holding world for players could not be prepared");
				$onDone($report);
			}
		);
	}

	private function swapLoadedWorld(World $world, World $holding, Position $holdingSpawn, string $name, ReloadReport $report) : void{
		$worlds = $this->server->getWorldManager();
		$wasDefault = $worlds->getDefaultWorld() === $world;

		/** @var list<array{\pocketmine\player\Player, Location}> $returns */
		$returns = [];
		foreach($world->getPlayers() as $player){
			$returns[] = [$player, $player->getLocation()];
			$player->teleport($holdingSpawn);
		}

		$world->setAutoSave(false); //the old files are kept as they are; the new ones must not be overwritten
		if(!$worlds->unloadWorld($world, true)){
			$report->error("World $name could not be unloaded");
			$this->sendBack($returns, $world);
			$this->dropHoldingWorld($holding);
			return;
		}

		$swapped = $this->swapFolders($name, $report);
		if(!$worlds->loadWorld($name)){
			$report->error("World $name could not be loaded" . ($swapped ? " from the new files; the old files were put back" : ""));
			if($swapped){
				$this->restoreFolders($name, $swapped);
				$worlds->loadWorld($name);
			}
		}
		$newWorld = $worlds->getWorldByName($name);
		if($newWorld !== null && $wasDefault){
			$worlds->setDefaultWorld($newWorld);
		}
		$destination = $newWorld ?? $worlds->getDefaultWorld();
		if($destination !== null && $destination !== $holding){
			$this->sendBack($returns, $destination);
			if(count($returns) > 0){
				$report->applied(count($returns) . " player(s) were moved back into $name");
			}
		}
		$this->dropHoldingWorld($holding);
	}

	/**
	 * Moves the world's folder aside and the pending folder into place.
	 *
	 * @return string|false the folder the old files were moved to, or false if nothing was swapped
	 */
	private function swapFolders(string $name, ReloadReport $report) : string|false{
		$target = Path::join($this->worldsPath(), $name);
		$source = $target . self::PENDING_SUFFIX;
		$old = $target . ".old-" . date("Y-m-d_H-i-s");
		if(is_dir($target) && !@rename($target, $old)){
			$report->error("Could not move worlds/$name aside");
			return false;
		}
		if(!@rename($source, $target)){
			if(is_dir($old)){
				@rename($old, $target);
			}
			$report->error("Could not move worlds/$name" . self::PENDING_SUFFIX . " into place");
			return false;
		}
		$report->applied("Replaced world $name with its new files (the old ones are in worlds/" . basename($old) . ")");
		return $old;
	}

	private function restoreFolders(string $name, string $old) : void{
		$target = Path::join($this->worldsPath(), $name);
		if(@rename($target, $target . self::PENDING_SUFFIX)){
			@rename($old, $target);
		}
	}

	/** @param list<array{\pocketmine\player\Player, Location}> $returns */
	private function sendBack(array $returns, World $world) : void{
		foreach($returns as [$player, $location]){
			if(!$player->isConnected()){
				continue;
			}
			try{
				$position = $world->getSafeSpawn(new Vector3($location->x, $location->y, $location->z));
			}catch(WorldException){
				$position = $world->getSpawnLocation();
			}
			$player->teleport(Location::fromObject($position, $world, $location->yaw, $location->pitch));
		}
	}

	private function getHoldingWorld() : ?World{
		$worlds = $this->server->getWorldManager();
		if(!$worlds->isWorldGenerated(self::HOLDING_WORLD)){
			$worlds->generateWorld(self::HOLDING_WORLD, WorldCreationOptions::create()->setGeneratorClass(Flat::class)->setSpawnPosition(new Vector3(0, 5, 0)), false);
		}
		if(!$worlds->isWorldLoaded(self::HOLDING_WORLD)){
			$worlds->loadWorld(self::HOLDING_WORLD);
		}
		return $worlds->getWorldByName(self::HOLDING_WORLD);
	}

	private function dropHoldingWorld(World $holding) : void{
		$worlds = $this->server->getWorldManager();
		if($holding->isLoaded() && count($holding->getPlayers()) === 0 && $worlds->unloadWorld($holding, true)){
			Filesystem::recursiveUnlink(Path::join($this->worldsPath(), self::HOLDING_WORLD));
		}
	}
}
