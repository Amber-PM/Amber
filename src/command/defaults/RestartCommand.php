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

namespace pocketmine\command\defaults;

use pocketmine\command\CommandSender;
use pocketmine\command\overload\StringArgumentParser;
use pocketmine\command\OverloadedCommand;
use pocketmine\permission\DefaultPermissions;
use pocketmine\permission\Permission;
use pocketmine\permission\PermissionManager;
use pocketmine\player\Player;
use pocketmine\reload\AssetReloader;
use pocketmine\reload\ConfigReloader;
use pocketmine\reload\ReloadReport;
use pocketmine\reload\WorldReloader;
use pocketmine\utils\TextFormat as TF;
use function array_shift;
use function count;
use function implode;

/**
 * /restart stops the server so its start script starts it again. /restart realtime applies changes while players
 * stay online: configuration, plugins added or removed, worlds with new files waiting, and resource packs/add-ons.
 */
final class RestartCommand extends OverloadedCommand{
	public const PERMISSION = "pocketmine.command.restart";

	public function __construct(){
		parent::__construct("restart", "Restarts the server, or reloads its configuration, plugins, worlds and assets while players stay online");
		$operator = PermissionManager::getInstance()->getPermission(DefaultPermissions::ROOT_OPERATOR);
		if(PermissionManager::getInstance()->getPermission(self::PERMISSION) === null && $operator !== null){
			DefaultPermissions::registerPermission(new Permission(self::PERMISSION, "Allows the user to restart or live-reload the server"), [$operator]);
		}
		$this->setPermission(self::PERMISSION);

		$realtime = new StringArgumentParser(["realtime"]);
		$this->addOverload(fn(CommandSender $sender) => $this->restart($sender));
		$this->addOverload(fn(CommandSender $sender, string $mode) => $this->realtime($sender, ["config", "plugins", "worlds", "assets"]), null, ["mode" => $realtime]);
		$this->addOverload(
			fn(CommandSender $sender, string $mode, string $part) => $this->realtime($sender, [$part]),
			null,
			["mode" => $realtime, "part" => new StringArgumentParser(["config", "plugins", "worlds", "assets"])]
		);
		$this->addOverload(
			fn(CommandSender $sender, string $mode, string $part, string $name) => $part === "plugin" ? $this->restartPlugin($sender, $name) : $this->reloadWorld($sender, $name),
			null,
			["mode" => $realtime, "part" => new StringArgumentParser(["plugin", "world"])]
		);
	}

	private function restart(CommandSender $sender) : bool{
		$server = $sender->getServer();
		$server->getLogger()->notice($sender->getName() . " restarted the server");
		foreach($server->getOnlinePlayers() as $player){
			$player->kick("The server is restarting, join again in a moment");
		}
		$server->shutdown();
		return true;
	}

	/** @param list<string> $parts */
	private function realtime(CommandSender $sender, array $parts) : bool{
		$server = $sender->getServer();
		$server->getLogger()->notice($sender->getName() . " started a live reload: " . implode(", ", $parts));
		foreach($server->getWorldManager()->getWorlds() as $world){
			$world->save(true);
		}
		$report = new ReloadReport();
		$this->runParts($sender, $parts, $report);
		return true;
	}

	/**
	 * Runs the parts one after another; world swaps finish asynchronously, and assets run last because they may
	 * reconnect players.
	 *
	 * @param list<string> $parts
	 */
	private function runParts(CommandSender $sender, array $parts, ReloadReport $report) : void{
		$server = $sender->getServer();
		while(($part = array_shift($parts)) !== null){
			switch($part){
				case "config":
					$report->merge((new ConfigReloader($server))->reload());
					break;
				case "plugins":
					$reloader = $server->getPluginReloader();
					if($reloader === null){
						$report->error("Plugins cannot be reloaded before the server has finished starting");
					}else{
						$report->merge($reloader->reload());
					}
					break;
				case "worlds":
					$pending = (new WorldReloader($server))->getPending();
					if(count($pending) === 0){
						$report->applied("No world has new files waiting (put them in worlds/<name>" . WorldReloader::PENDING_SUFFIX . "/)");
						break;
					}
					$this->reloadWorlds($sender, $pending, $parts, $report);
					return;
				case "assets":
					$report->merge((new AssetReloader($server))->reload());
					break;
			}
		}
		$this->send($sender, $report);
	}

	/**
	 * @param list<string> $worlds
	 * @param list<string> $remainingParts
	 */
	private function reloadWorlds(CommandSender $sender, array $worlds, array $remainingParts, ReloadReport $report) : void{
		$name = array_shift($worlds);
		if($name === null){
			$this->runParts($sender, $remainingParts, $report);
			return;
		}
		(new WorldReloader($sender->getServer()))->reload($name, function(ReloadReport $worldReport) use ($sender, $worlds, $remainingParts, $report) : void{
			$report->merge($worldReport);
			$this->reloadWorlds($sender, $worlds, $remainingParts, $report);
		});
	}

	private function reloadWorld(CommandSender $sender, string $name) : bool{
		(new WorldReloader($sender->getServer()))->reload($name, fn(ReloadReport $report) => $this->send($sender, $report));
		return true;
	}

	private function restartPlugin(CommandSender $sender, string $name) : bool{
		$server = $sender->getServer();
		$plugin = $server->getPluginManager()->getPlugin($name);
		$reloader = $server->getPluginReloader();
		if($plugin === null || $reloader === null){
			$sender->sendMessage(TF::RED . "No plugin is called $name");
			return true;
		}
		$this->send($sender, $reloader->restartPlugin($plugin));
		return true;
	}

	private function send(CommandSender $sender, ReloadReport $report) : void{
		if($sender instanceof Player && !$sender->isConnected()){
			return;
		}
		foreach($report->getApplied() as $line){
			$sender->sendMessage(TF::GREEN . "- " . $line);
		}
		foreach($report->getNeedsRestart() as $line){
			$sender->sendMessage(TF::YELLOW . "- " . $line);
		}
		foreach($report->getErrors() as $line){
			$sender->sendMessage(TF::RED . "- " . $line);
		}
	}
}
