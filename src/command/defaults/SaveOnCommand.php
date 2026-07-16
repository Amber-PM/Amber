<?php

declare(strict_types=1);

namespace pocketmine\command\defaults;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\OverloadedCommand;
use pocketmine\lang\KnownTranslationFactory;
use pocketmine\permission\DefaultPermissionNames;

class SaveOnCommand extends OverloadedCommand{

	public function __construct(){
		parent::__construct(
			"save-on",
			KnownTranslationFactory::pocketmine_command_saveon_description()
		);
		$this->setPermission(DefaultPermissionNames::COMMAND_SAVE_ENABLE);

		$this->addOverload(
			fn(CommandSender $sender) => $this->enable($sender)
		);
	}

	private function enable(CommandSender $sender) : bool{
		$sender->getServer()->getWorldManager()->setAutoSave(true);
		Command::broadcastCommandMessage($sender, KnownTranslationFactory::commands_save_enabled());
		return true;
	}
}
