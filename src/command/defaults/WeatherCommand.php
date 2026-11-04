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
use pocketmine\command\overload\IntegerArgumentParser;
use pocketmine\command\overload\StringArgumentParser;
use pocketmine\command\utils\InvalidCommandSyntaxException;
use pocketmine\lang\KnownTranslationFactory;
use pocketmine\permission\DefaultPermissionNames;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use pocketmine\world\weather\Weather;
use function max;
use function min;

class WeatherCommand extends OverloadedCommand{

	public function __construct(){
		parent::__construct(
			"weather",
			KnownTranslationFactory::pocketmine_command_weather_description(),
			KnownTranslationFactory::commands_weather_usage()
		);
		$this->setPermission(DefaultPermissionNames::COMMAND_WEATHER);

		$this->addOverload(
			fn(CommandSender $sender, string $type) => $this->setWeather($sender, $type, null),
			explicitParsers: ["type" => new StringArgumentParser(["clear", "rain", "thunder"])]
		);
		$this->addOverload(
			fn(CommandSender $sender, string $type, int $duration) => $this->setWeather($sender, $type, $duration),
			explicitParsers: [
				"type" => new StringArgumentParser(["clear", "rain", "thunder"]),
				"duration" => new IntegerArgumentParser(1, 1000000)
			]
		);
	}

	private function setWeather(CommandSender $sender, string $type, ?int $durationSeconds) : bool{
		$weatherType = Weather::fromString($type);
		if($weatherType === null){
			throw new InvalidCommandSyntaxException();
		}

		$durationTicks = null;
		if($durationSeconds !== null){
			// Limit duration between 1 second and 1,000,000 seconds (vanilla Bedrock limit) to prevent integer/float overflow
			$durationSeconds = min(max(1, $durationSeconds), 1000000);
			$durationTicks = $durationSeconds * 20;
		}

		$world = $sender instanceof Player ? $sender->getWorld() : $sender->getServer()->getWorldManager()->getDefaultWorld();
		if($world === null){
			$sender->sendMessage(TextFormat::RED . "No default world available");
			return false;
		}

		$world->setWeather($weatherType, $durationTicks);
		$weatherMsg = match($weatherType){
			Weather::CLEAR => KnownTranslationFactory::commands_weather_clear(),
			Weather::RAIN => KnownTranslationFactory::commands_weather_rain(),
			Weather::THUNDER => KnownTranslationFactory::commands_weather_thunder(),
		};
		Command::broadcastCommandMessage($sender, $weatherMsg);

		return true;
	}
}
