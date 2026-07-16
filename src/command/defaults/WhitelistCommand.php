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
use pocketmine\command\OverloadedCommand;
use pocketmine\command\overload\StringArgumentParser;
use pocketmine\command\utils\InvalidCommandSyntaxException;
use pocketmine\lang\KnownTranslationFactory;
use pocketmine\lang\Translatable;
use pocketmine\permission\DefaultPermissionNames;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\ServerProperties;
use function count;
use function implode;
use function sort;
use function strtolower;
use const SORT_STRING;

class WhitelistCommand extends OverloadedCommand{

	public function __construct(){
		parent::__construct(
			"whitelist",
			KnownTranslationFactory::pocketmine_command_whitelist_description(),
			KnownTranslationFactory::commands_whitelist_usage()
		);
		$this->setPermissions([
			DefaultPermissionNames::COMMAND_WHITELIST_RELOAD,
			DefaultPermissionNames::COMMAND_WHITELIST_ENABLE,
			DefaultPermissionNames::COMMAND_WHITELIST_DISABLE,
			DefaultPermissionNames::COMMAND_WHITELIST_LIST,
			DefaultPermissionNames::COMMAND_WHITELIST_ADD,
			DefaultPermissionNames::COMMAND_WHITELIST_REMOVE
		]);

		//reload / on / off / list take no further arguments
		$this->addOverload(
			fn(CommandSender $sender, string $action) => $this->runAction($sender, $action),
			explicitParsers: ["action" => new StringArgumentParser(["reload", "on", "off", "list"])]
		);
		//bare "add"/"remove" with no username shows the usage line, same as vanilla
		$this->addOverload(
			fn(CommandSender $sender, string $action) => $this->sendUserActionUsage($sender, $action),
			explicitParsers: ["action" => new StringArgumentParser(["add", "remove"])]
		);
		$this->addOverload(
			fn(CommandSender $sender, string $action, string $username) => $this->runUserAction($sender, $action, $username),
			explicitParsers: ["action" => new StringArgumentParser(["add", "remove"])]
		);
	}

	private function runAction(CommandSender $sender, string $action) : bool{
		switch(strtolower($action)){
			case "reload":
				if($this->testPermission($sender, DefaultPermissionNames::COMMAND_WHITELIST_RELOAD)){
					$server = $sender->getServer();
					$server->getWhitelisted()->reload();
					if($server->hasWhitelist()){
						$this->kickNonWhitelistedPlayers($server);
					}
					Command::broadcastCommandMessage($sender, KnownTranslationFactory::commands_whitelist_reloaded());
				}
				return true;
			case "on":
				if($this->testPermission($sender, DefaultPermissionNames::COMMAND_WHITELIST_ENABLE)){
					$server = $sender->getServer();
					$server->getConfigGroup()->setConfigBool(ServerProperties::WHITELIST, true);
					$this->kickNonWhitelistedPlayers($server);
					Command::broadcastCommandMessage($sender, KnownTranslationFactory::commands_whitelist_enabled());
				}
				return true;
			case "off":
				if($this->testPermission($sender, DefaultPermissionNames::COMMAND_WHITELIST_DISABLE)){
					$sender->getServer()->getConfigGroup()->setConfigBool(ServerProperties::WHITELIST, false);
					Command::broadcastCommandMessage($sender, KnownTranslationFactory::commands_whitelist_disabled());
				}
				return true;
			case "list":
				if($this->testPermission($sender, DefaultPermissionNames::COMMAND_WHITELIST_LIST)){
					$entries = $sender->getServer()->getWhitelisted()->getAll(true);
					sort($entries, SORT_STRING);
					$result = implode(", ", $entries);
					$count = (string) count($entries);

					$sender->sendMessage(KnownTranslationFactory::commands_whitelist_list($count, $count));
					$sender->sendMessage($result);
				}
				return true;
			default:
				throw new InvalidCommandSyntaxException();
		}
	}

	private function sendUserActionUsage(CommandSender $sender, string $action) : bool{
		/** @var Translatable $usage */
		$usage = match(strtolower($action)){
			"add" => KnownTranslationFactory::commands_whitelist_add_usage(),
			"remove" => KnownTranslationFactory::commands_whitelist_remove_usage(),
		};
		$sender->sendMessage(KnownTranslationFactory::commands_generic_usage($usage));
		return true;
	}

	private function runUserAction(CommandSender $sender, string $action, string $username) : bool{
		if(!Player::isValidUserName($username)){
			throw new InvalidCommandSyntaxException();
		}

		switch(strtolower($action)){
			case "add":
				if($this->testPermission($sender, DefaultPermissionNames::COMMAND_WHITELIST_ADD)){
					$sender->getServer()->addWhitelist($username);
					Command::broadcastCommandMessage($sender, KnownTranslationFactory::commands_whitelist_add_success($username));
				}
				return true;
			case "remove":
				if($this->testPermission($sender, DefaultPermissionNames::COMMAND_WHITELIST_REMOVE)){
					$server = $sender->getServer();
					$server->removeWhitelist($username);
					if(!$server->isWhitelisted($username)){
						$server->getPlayerExact($username)?->kick(KnownTranslationFactory::pocketmine_disconnect_kick(KnownTranslationFactory::pocketmine_disconnect_whitelisted()));
					}
					Command::broadcastCommandMessage($sender, KnownTranslationFactory::commands_whitelist_remove_success($username));
				}
				return true;
			default:
				throw new InvalidCommandSyntaxException();
		}
	}

	private function kickNonWhitelistedPlayers(Server $server) : void{
		$message = KnownTranslationFactory::pocketmine_disconnect_kick(KnownTranslationFactory::pocketmine_disconnect_whitelisted());
		foreach($server->getOnlinePlayers() as $player){
			if(!$server->isWhitelisted($player->getName())){
				$player->kick($message);
			}
		}
	}
}
