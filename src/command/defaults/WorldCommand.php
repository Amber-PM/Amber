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
use pocketmine\command\OverloadedCommand;
use pocketmine\command\overload\StringArgumentParser;
use pocketmine\lang\KnownTranslationFactory;
use pocketmine\lang\Translatable;
use pocketmine\permission\DefaultPermissionNames;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\world\WorldException;
use Symfony\Component\Filesystem\Path;
use function count;
use function implode;
use function is_dir;
use function preg_match;
use function rtrim;
use function scandir;
use function strtolower;
use function strpbrk;
use function usort;

class WorldCommand extends OverloadedCommand{

	public function __construct(){
		parent::__construct(
			"world",
			KnownTranslationFactory::pocketmine_command_world_description(),
			KnownTranslationFactory::pocketmine_command_world_usage(),
			[]
		);
		$this->setPermission(DefaultPermissionNames::COMMAND_WORLD);

		$this->addOverload(
			fn(CommandSender $sender, string $action) => $this->listWorlds($sender),
			DefaultPermissionNames::COMMAND_WORLD,
			["action" => new StringArgumentParser(["list"])]
		);
		$this->addOverload(
			fn(CommandSender $sender, string $action, string $world) => $this->runWorldAction($sender, $action, $world),
			DefaultPermissionNames::COMMAND_WORLD,
			["action" => new StringArgumentParser(["load", "unload", "tp"])]
		);
	}

	public function getUsage() : Translatable|string{
		return $this->usageMessage ?? parent::getUsage();
	}

	private function runWorldAction(CommandSender $sender, string $action, string $world) : bool{
		return match(strtolower($action)){
			"load" => $this->loadWorld($sender, $world),
			"unload" => $this->unloadWorld($sender, $world),
			"tp" => $this->teleportWorld($sender, $world),
			default => false
		};
	}

	private function listWorlds(CommandSender $sender) : bool{
		$server = Server::getInstance();
		$worldManager = $server->getWorldManager();

		$loaded = [];
		foreach($worldManager->getWorlds() as $world){
			$loaded[] = $world->getFolderName();
		}
		usort($loaded, fn(string $a, string $b) => $a <=> $b);

		$unloaded = [];
		$dataPath = Path::join($server->getDataPath(), "worlds");
		if(is_dir($dataPath)){
			$entries = scandir($dataPath);
			if($entries !== false){
				foreach($entries as $entry){
					if($entry === "." || $entry === ".." || !self::isValidWorldName($entry)){
						continue;
					}
					if($worldManager->getWorldByName($entry) !== null){
						continue;
					}
					if($worldManager->isWorldGenerated($entry)){
						$unloaded[] = $entry;
					}
				}
			}
		}
		usort($unloaded, fn(string $a, string $b) => $a <=> $b);

		$sender->sendMessage(KnownTranslationFactory::pocketmine_command_world_list_loaded(
			(string) count($loaded),
			implode(", ", $loaded)
		));
		$sender->sendMessage(KnownTranslationFactory::pocketmine_command_world_list_unloaded(
			(string) count($unloaded),
			implode(", ", $unloaded)
		));
		return true;
	}

	private function loadWorld(CommandSender $sender, string $worldName) : bool{
		if(!self::isValidWorldName($worldName)){
			$sender->sendMessage(KnownTranslationFactory::pocketmine_command_world_load_error($worldName));
			return true;
		}

		$worldManager = Server::getInstance()->getWorldManager();
		if($worldManager->getWorldByName($worldName) !== null){
			$sender->sendMessage(KnownTranslationFactory::pocketmine_command_world_load_alreadyLoaded($worldName));
			return true;
		}

		if(!$worldManager->isWorldGenerated($worldName)){
			$sender->sendMessage(KnownTranslationFactory::pocketmine_command_world_load_notFound($worldName));
			return true;
		}

		try{
			$success = $worldManager->loadWorld($worldName, false);
			if($success && $worldManager->getWorldByName($worldName) !== null){
				$sender->sendMessage(KnownTranslationFactory::pocketmine_command_world_load_success($worldName));
				return true;
			}
		}catch(WorldException $e){
			Server::getInstance()->getLogger()->logException($e);
		}

		$sender->sendMessage(KnownTranslationFactory::pocketmine_command_world_load_error($worldName));
		return true;
	}

	private function unloadWorld(CommandSender $sender, string $worldName) : bool{
		if(!self::isValidWorldName($worldName)){
			$sender->sendMessage(KnownTranslationFactory::pocketmine_command_world_unload_error($worldName));
			return true;
		}

		$worldManager = Server::getInstance()->getWorldManager();
		$world = $worldManager->getWorldByName($worldName);
		if($world === null){
			$sender->sendMessage(KnownTranslationFactory::pocketmine_command_world_unload_notLoaded($worldName));
			return true;
		}

		if($world === $worldManager->getDefaultWorld()){
			$sender->sendMessage(KnownTranslationFactory::pocketmine_command_world_unload_default());
			return true;
		}

		try{
			if($worldManager->unloadWorld($world)){
				$sender->sendMessage(KnownTranslationFactory::pocketmine_command_world_unload_success($worldName));
			}else{
				$sender->sendMessage(KnownTranslationFactory::pocketmine_command_world_unload_error($worldName));
			}
		}catch(\InvalidArgumentException $e){
			Server::getInstance()->getLogger()->logException($e);
			$sender->sendMessage(KnownTranslationFactory::pocketmine_command_world_unload_error($worldName));
		}

		return true;
	}

	private function teleportWorld(CommandSender $sender, string $worldName) : bool{
		if(!($sender instanceof Player)){
			$sender->sendMessage(KnownTranslationFactory::pocketmine_command_error_playerUserOnly());
			return true;
		}

		if(!self::isValidWorldName($worldName)){
			$sender->sendMessage(KnownTranslationFactory::pocketmine_command_world_tp_notLoaded($worldName));
			return true;
		}

		$world = Server::getInstance()->getWorldManager()->getWorldByName($worldName);
		if($world === null){
			$sender->sendMessage(KnownTranslationFactory::pocketmine_command_world_tp_notLoaded($worldName));
			return true;
		}

		try{
			$safeSpawn = $world->getSafeSpawn();
		}catch(WorldException){
			$sender->sendMessage(KnownTranslationFactory::pocketmine_command_world_tp_error($worldName));
			return true;
		}

		if($sender->teleport($safeSpawn)){
			$sender->sendMessage(KnownTranslationFactory::pocketmine_command_world_tp_success($worldName));
		}else{
			$sender->sendMessage(KnownTranslationFactory::pocketmine_command_world_tp_cancelled($worldName));
		}
		return true;
	}

	public static function isValidWorldName(string $name) : bool{
		return $name !== "" && $name !== "." && $name !== ".."
			&& strpbrk($name, "/\\:") === false
			&& preg_match('/[\x00-\x1f\x7f]/', $name) === 0
			&& rtrim($name, " .") === $name;
	}
}
