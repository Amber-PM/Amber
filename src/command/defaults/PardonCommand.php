<?php

declare(strict_types=1);

namespace pocketmine\command\defaults;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\OverloadedCommand;
use pocketmine\lang\KnownTranslationFactory;
use pocketmine\permission\DefaultPermissionNames;

class PardonCommand extends OverloadedCommand{

	public function __construct(){
		parent::__construct(
			"pardon",
			KnownTranslationFactory::pocketmine_command_unban_player_description(),
			KnownTranslationFactory::commands_unban_usage(),
			["unban"]
		);
		$this->setPermission(DefaultPermissionNames::COMMAND_UNBAN_PLAYER);

		$this->addOverload(
			fn(CommandSender $sender, string $name) => $this->pardon($sender, $name)
		);
	}

	private function pardon(CommandSender $sender, string $name) : bool{
		$sender->getServer()->getNameBans()->remove($name);
		Command::broadcastCommandMessage($sender, KnownTranslationFactory::commands_unban_success($name));
		return true;
	}
}
