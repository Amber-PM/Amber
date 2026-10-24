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
use pocketmine\command\utils\InvalidCommandSyntaxException;
use pocketmine\lang\Translatable;
use pocketmine\permission\DefaultPermissionNames;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use pocketmine\world\weather\Weather;
use function count;
use function is_numeric;
use function max;

class WeatherCommand extends VanillaCommand{

	public function __construct(){
		parent::__construct(
			"weather",
			"Sets the weather",
			"/weather <clear|rain|thunder> [duration: int]"
		);
		$this->setPermission(DefaultPermissionNames::COMMAND_WEATHER);
	}

	public function execute(CommandSender $sender, string $commandLabel, array $args) : bool{
		if(!$this->testPermission($sender)){
			return true;
		}

		if(count($args) === 0){
			throw new InvalidCommandSyntaxException();
		}

		$weatherType = Weather::fromString($args[0]);
		if($weatherType === null){
			throw new InvalidCommandSyntaxException();
		}

		$duration = null;
		if(isset($args[1])){
			if(!is_numeric($args[1])){
				throw new InvalidCommandSyntaxException();
			}
			$duration = max(1, (int) $args[1]) * 20; // Seconds to ticks
		}

		$world = $sender instanceof Player ? $sender->getWorld() : $sender->getServer()->getWorldManager()->getDefaultWorld();
		if($world === null){
			$sender->sendMessage(TextFormat::RED . "No default world available");
			return false;
		}

		$world->setWeather($weatherType, $duration);
		$weatherName = Weather::toString($weatherType);
		Command::broadcastCommandMessage($sender, new Translatable("commands.weather.set." . $weatherName, [], "Set the weather to " . $weatherName));

		return true;
	}
}
