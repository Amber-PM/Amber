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
use pocketmine\lang\KnownTranslationFactory;
use pocketmine\permission\DefaultPermissionNames;
use pocketmine\player\GameMode;
use pocketmine\player\Player;

class GamemodeCommand extends OverloadedCommand{

	public function __construct(){
		parent::__construct(
			"gamemode",
			KnownTranslationFactory::pocketmine_command_gamemode_description(),
			KnownTranslationFactory::commands_gamemode_usage()
		);
		$this->setPermissions([
			DefaultPermissionNames::COMMAND_GAMEMODE_SELF,
			DefaultPermissionNames::COMMAND_GAMEMODE_OTHER
		]);

		$modeAliases = [];
		foreach(GameMode::cases() as $case){
			foreach($case->getAliases() as $alias){
				$modeAliases[] = $alias;
			}
		}
		$modeParser = new StringArgumentParser($modeAliases);

		$this->addOverload(
			fn(Player $sender, string $mode) => $this->applyGamemode($sender, $sender, $mode),
			DefaultPermissionNames::COMMAND_GAMEMODE_SELF,
			["mode" => $modeParser]
		);
		$this->addOverload(
			fn(CommandSender $sender, string $mode, Player $target) => $this->applyGamemode($sender, $target, $mode),
			DefaultPermissionNames::COMMAND_GAMEMODE_OTHER,
			["mode" => $modeParser]
		);
	}

	private function applyGamemode(CommandSender $sender, Player $target, string $modeString) : bool{
		$gameMode = GameMode::fromString($modeString);
		if($gameMode === null){
			$sender->sendMessage(KnownTranslationFactory::pocketmine_command_gamemode_unknown($modeString));
			return true;
		}

		if($target->getGamemode() === $gameMode){
			$sender->sendMessage(KnownTranslationFactory::pocketmine_command_gamemode_failure($target->getName()));
			return true;
		}

		$target->setGamemode($gameMode);
		if($gameMode !== $target->getGamemode()){
			$sender->sendMessage(KnownTranslationFactory::pocketmine_command_gamemode_failure($target->getName()));
		}else{
			if($target === $sender){
				Command::broadcastCommandMessage($sender, KnownTranslationFactory::commands_gamemode_success_self($gameMode->getTranslatableName()));
			}else{
				$target->sendMessage(KnownTranslationFactory::gameMode_changed($gameMode->getTranslatableName()));
				Command::broadcastCommandMessage($sender, KnownTranslationFactory::commands_gamemode_success_other($gameMode->getTranslatableName(), $target->getName()));
			}
		}

		return true;
	}
}
