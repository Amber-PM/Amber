<?php

declare(strict_types=1);

namespace pocketmine\command\defaults;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\OverloadedCommand;
use pocketmine\command\overload\StringArgumentParser;
use pocketmine\lang\KnownTranslationFactory;
use pocketmine\permission\DefaultPermissionNames;
use pocketmine\player\Player;
use pocketmine\world\World;
use function array_keys;

class TimeCommand extends OverloadedCommand{

	private const PRESETS = [
		"day" => World::TIME_DAY,
		"noon" => World::TIME_NOON,
		"sunset" => World::TIME_SUNSET,
		"night" => World::TIME_NIGHT,
		"midnight" => World::TIME_MIDNIGHT,
		"sunrise" => World::TIME_SUNRISE
	];

	public function __construct(){
		parent::__construct(
			"time",
			KnownTranslationFactory::pocketmine_command_time_description(),
			KnownTranslationFactory::pocketmine_command_time_usage()
		);
		$this->setPermissions([
			DefaultPermissionNames::COMMAND_TIME_ADD,
			DefaultPermissionNames::COMMAND_TIME_SET,
			DefaultPermissionNames::COMMAND_TIME_START,
			DefaultPermissionNames::COMMAND_TIME_STOP,
			DefaultPermissionNames::COMMAND_TIME_QUERY
		]);

		$this->addOverload(
			fn(CommandSender $sender, string $action) => $this->startTime($sender),
			DefaultPermissionNames::COMMAND_TIME_START,
			["action" => new StringArgumentParser(["start"])]
		);
		$this->addOverload(
			fn(CommandSender $sender, string $action) => $this->stopTime($sender),
			DefaultPermissionNames::COMMAND_TIME_STOP,
			["action" => new StringArgumentParser(["stop"])]
		);
		$this->addOverload(
			fn(CommandSender $sender, string $action) => $this->queryTime($sender),
			DefaultPermissionNames::COMMAND_TIME_QUERY,
			["action" => new StringArgumentParser(["query"])]
		);
		$this->addOverload(
			fn(CommandSender $sender, string $action, string $preset) => $this->setTime($sender, self::PRESETS[$preset]),
			DefaultPermissionNames::COMMAND_TIME_SET,
			["action" => new StringArgumentParser(["set"]), "preset" => new StringArgumentParser(array_keys(self::PRESETS))]
		);
		$this->addOverload(
			fn(CommandSender $sender, string $action, int $value) => $this->setTime($sender, $value),
			DefaultPermissionNames::COMMAND_TIME_SET,
			["action" => new StringArgumentParser(["set"])]
		);
		$this->addOverload(
			fn(CommandSender $sender, string $action, int $value) => $this->addTime($sender, $value),
			DefaultPermissionNames::COMMAND_TIME_ADD,
			["action" => new StringArgumentParser(["add"])]
		);
	}

	private function startTime(CommandSender $sender) : bool{
		foreach($sender->getServer()->getWorldManager()->getWorlds() as $world){
			$world->startTime();
		}
		Command::broadcastCommandMessage($sender, "Restarted the time");
		return true;
	}

	private function stopTime(CommandSender $sender) : bool{
		foreach($sender->getServer()->getWorldManager()->getWorlds() as $world){
			$world->stopTime();
		}
		Command::broadcastCommandMessage($sender, "Stopped the time");
		return true;
	}

	private function queryTime(CommandSender $sender) : bool{
		$world = $sender instanceof Player ? $sender->getWorld() : $sender->getServer()->getWorldManager()->getDefaultWorld();
		$sender->sendMessage($sender->getLanguage()->translate(KnownTranslationFactory::commands_time_query((string) $world->getTime())));
		return true;
	}

	private function setTime(CommandSender $sender, int $value) : bool{
		foreach($sender->getServer()->getWorldManager()->getWorlds() as $world){
			$world->setTime($value);
		}
		Command::broadcastCommandMessage($sender, KnownTranslationFactory::commands_time_set((string) $value));
		return true;
	}

	private function addTime(CommandSender $sender, int $value) : bool{
		foreach($sender->getServer()->getWorldManager()->getWorlds() as $world){
			$world->setTime($world->getTime() + $value);
		}
		Command::broadcastCommandMessage($sender, KnownTranslationFactory::commands_time_added((string) $value));
		return true;
	}
}
