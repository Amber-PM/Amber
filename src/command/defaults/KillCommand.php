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
use pocketmine\command\overload\PlayerOrSelfArgumentParser;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\lang\KnownTranslationFactory;
use pocketmine\permission\DefaultPermissionNames;
use pocketmine\player\Player;

class KillCommand extends OverloadedCommand{

	public function __construct(){
		parent::__construct(
			"kill",
			KnownTranslationFactory::pocketmine_command_kill_description(),
			KnownTranslationFactory::pocketmine_command_kill_usage(),
			["suicide"]
		);
		$this->setPermissions([
			DefaultPermissionNames::COMMAND_KILL_SELF,
			DefaultPermissionNames::COMMAND_KILL_OTHER
		]);

		$this->addOverload(fn(Player $sender) => $this->kill($sender, $sender));
		$this->addOverload(
			fn(CommandSender $sender, Player $target) => $this->kill($sender, $target),
			explicitParsers: ["target" => new PlayerOrSelfArgumentParser()]
		);
	}

	private function kill(CommandSender $sender, Player $target) : bool{
		if(!$this->testPermission($sender, $target === $sender ? DefaultPermissionNames::COMMAND_KILL_SELF : DefaultPermissionNames::COMMAND_KILL_OTHER)){
			return true;
		}

		$target->attack(new EntityDamageEvent($target, EntityDamageEvent::CAUSE_SUICIDE, $target->getHealth()));
		if($target === $sender){
			$sender->sendMessage(KnownTranslationFactory::commands_kill_successful($sender->getName()));
		}else{
			Command::broadcastCommandMessage($sender, KnownTranslationFactory::commands_kill_successful($target->getName()));
		}

		return true;
	}
}
