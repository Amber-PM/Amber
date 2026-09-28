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

namespace pocketmine\addon\script;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use function implode;

/**
 * A vanilla Bedrock command the server lacks (/gamerule, /scoreboard, /summon...), served by the command
 * bridge so players and operators can use the same commands add-ons use. Registered only for names no plugin
 * or core command already has. Operators only.
 */
final class BridgeCommand extends Command{
	public function __construct(string $name, string $description, private CommandBridge $bridge){
		parent::__construct($name, $description, "/$name ...");
		$this->setPermission("pocketmine.group.operator");
	}

	public function execute(CommandSender $sender, string $commandLabel, array $args) : bool{
		$output = new CommandOutput();
		$success = $this->bridge->run($this->getName() . " " . implode(" ", $args), $sender instanceof Player ? $sender : null, null, null, null, null, $output);
		foreach($output->getLines() as $line){
			$sender->sendMessage($line);
		}
		if($success === 0 && $output->getLines() === []){
			$sender->sendMessage(TextFormat::RED . "/" . $this->getName() . ": nothing matched");
		}
		return true;
	}
}
