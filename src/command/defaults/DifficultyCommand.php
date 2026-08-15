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

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\OverloadedCommand;
use pocketmine\command\overload\StringArgumentParser;
use pocketmine\command\utils\InvalidCommandSyntaxException;
use pocketmine\lang\KnownTranslationFactory;
use pocketmine\permission\DefaultPermissionNames;
use pocketmine\ServerProperties;
use pocketmine\world\World;

class DifficultyCommand extends OverloadedCommand{

	private const ALIASES = ["0", "peaceful", "p", "1", "easy", "e", "2", "normal", "n", "3", "hard", "h"];

	public function __construct(){
		parent::__construct(
			"difficulty",
			KnownTranslationFactory::pocketmine_command_difficulty_description(),
			KnownTranslationFactory::commands_difficulty_usage()
		);
		$this->setPermission(DefaultPermissionNames::COMMAND_DIFFICULTY);

		$this->addOverload(
			fn(CommandSender $sender, string $difficulty) => $this->run($sender, $difficulty),
			explicitParsers: ["difficulty" => new StringArgumentParser(self::ALIASES)]
		);
	}

	private function run(CommandSender $sender, string $difficulty) : bool{
		$value = $sender->getServer()->isHardcore() ? World::DIFFICULTY_HARD : World::getDifficultyFromString($difficulty);
		if($value === -1){
			throw new InvalidCommandSyntaxException();
		}

		$sender->getServer()->getConfigGroup()->setConfigInt(ServerProperties::DIFFICULTY, $value);

		//TODO: add per-world support
		foreach($sender->getServer()->getWorldManager()->getWorlds() as $world){
			$world->setDifficulty($value);
		}

		Command::broadcastCommandMessage($sender, KnownTranslationFactory::commands_difficulty_success((string) $value));

		return true;
	}
}
