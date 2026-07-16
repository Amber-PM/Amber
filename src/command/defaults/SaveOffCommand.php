<?php

declare(strict_types=1);

namespace pocketmine\command\defaults;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\OverloadedCommand;
use pocketmine\lang\KnownTranslationFactory;
use pocketmine\permission\DefaultPermissionNames;

class SaveOffCommand extends OverloadedCommand{

	public function __construct(){
		parent::__construct(
			"save-off",
			KnownTranslationFactory::pocketmine_command_saveoff_description()
		);
		$this->setPermission(DefaultPermissionNames::COMMAND_SAVE_DISABLE);

		$this->addOverload(
			fn(CommandSender $sender) => $this->disable($sender)
		);
	}

	private function disable(CommandSender $sender) : bool{
		$sender->getServer()->getWorldManager()->setAutoSave(false);
		Command::broadcastCommandMessage($sender, KnownTranslationFactory::commands_save_disabled());
		return true;
	}
}
