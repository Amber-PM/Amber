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
use pocketmine\command\overload\GreedyStringArgumentParser;
use pocketmine\command\OverloadedCommand;
use pocketmine\lang\KnownTranslationFactory;
use pocketmine\lang\Translatable;
use pocketmine\permission\DefaultPermissionNames;
use pocketmine\utils\TextFormat;
use function array_chunk;
use function count;
use function explode;
use function implode;
use function ksort;
use function min;
use function sort;
use function strtolower;
use const PHP_INT_MAX;
use const SORT_FLAG_CASE;
use const SORT_NATURAL;

class HelpCommand extends OverloadedCommand{

	public function __construct(){
		parent::__construct(
			"help",
			KnownTranslationFactory::pocketmine_command_help_description(),
			KnownTranslationFactory::commands_help_usage(),
			["?"]
		);
		$this->setPermission(DefaultPermissionNames::COMMAND_HELP);

		$this->addOverload(fn(CommandSender $sender) => $this->listCommands($sender, 1));
		$this->addOverload(fn(CommandSender $sender, int $page) => $this->listCommands($sender, $page));
		$this->addOverload(fn(CommandSender $sender, string $command, int $page) => $this->describeCommand($sender, $command));
		$this->addOverload(fn(CommandSender $sender, string $command) => $this->describeCommand($sender, $command), null, ["command" => new GreedyStringArgumentParser()]);
	}

	private function listCommands(CommandSender $sender, int $pageNumber) : bool{
		$pageHeight = $sender->getScreenLineHeight();
		$commands = [];
		foreach($sender->getServer()->getCommandMap()->getCommands() as $command){
			if($command->testPermissionSilent($sender)){
				$commands[$command->getLabel()] = $command;
			}
		}
		ksort($commands, SORT_NATURAL | SORT_FLAG_CASE);
		$commands = array_chunk($commands, $pageHeight);
		$pageNumber = min(count($commands), $pageNumber);
		if($pageNumber < 1){
			$pageNumber = 1;
		}
		$sender->sendMessage(KnownTranslationFactory::commands_help_header((string) $pageNumber, (string) count($commands)));
		$lang = $sender->getLanguage();
		if(isset($commands[$pageNumber - 1])){
			foreach($commands[$pageNumber - 1] as $command){
				$description = $command->getDescription();
				$descriptionString = $description instanceof Translatable ? $lang->translate($description) : $description;
				$sender->sendMessage(TextFormat::DARK_GREEN . "/" . $command->getLabel() . ": " . TextFormat::RESET . $descriptionString);
			}
		}

		return true;
	}

	private function describeCommand(CommandSender $sender, string $commandName) : bool{
		if(($cmd = $sender->getServer()->getCommandMap()->getCommand(strtolower($commandName))) instanceof Command){
			if($cmd->testPermissionSilent($sender)){
				$lang = $sender->getLanguage();
				$description = $cmd->getDescription();
				$descriptionString = $description instanceof Translatable ? $lang->translate($description) : $description;
				$sender->sendMessage(KnownTranslationFactory::pocketmine_command_help_specificCommand_header($cmd->getLabel())
					->format(TextFormat::YELLOW . "--------- " . TextFormat::RESET, TextFormat::YELLOW . " ---------"));
				$sender->sendMessage(KnownTranslationFactory::pocketmine_command_help_specificCommand_description(TextFormat::RESET . $descriptionString)
					->prefix(TextFormat::GOLD));

				$usage = $cmd->getUsage();
				$usageString = $usage instanceof Translatable ? $lang->translate($usage) : $usage;
				$sender->sendMessage(KnownTranslationFactory::pocketmine_command_help_specificCommand_usage(TextFormat::RESET . implode("\n" . TextFormat::RESET, explode("\n", $usageString, limit: PHP_INT_MAX)))
					->prefix(TextFormat::GOLD));

				$aliases = $cmd->getAliases();
				sort($aliases, SORT_NATURAL);
				$sender->sendMessage(KnownTranslationFactory::pocketmine_command_help_specificCommand_aliases(TextFormat::RESET . implode(", ", $aliases))
					->prefix(TextFormat::GOLD));

				return true;
			}
		}
		$sender->sendMessage(KnownTranslationFactory::pocketmine_command_notFound($commandName, "/help")->prefix(TextFormat::RED));

		return true;
	}
}
