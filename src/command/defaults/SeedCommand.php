<?php

declare(strict_types=1);

namespace pocketmine\command\defaults;

use pocketmine\command\CommandSender;
use pocketmine\command\OverloadedCommand;
use pocketmine\lang\KnownTranslationFactory;
use pocketmine\permission\DefaultPermissionNames;
use pocketmine\player\Player;

class SeedCommand extends OverloadedCommand{

	public function __construct(){
		parent::__construct(
			"seed",
			KnownTranslationFactory::pocketmine_command_seed_description()
		);
		$this->setPermission(DefaultPermissionNames::COMMAND_SEED);

		$this->addOverload(
			fn(CommandSender $sender) => $this->showSeed($sender)
		);
	}

	private function showSeed(CommandSender $sender) : bool{
		$world = $sender instanceof Player
			? $sender->getPosition()->getWorld()
			: $sender->getServer()->getWorldManager()->getDefaultWorld();

		$sender->sendMessage(KnownTranslationFactory::commands_seed_success((string) $world->getSeed()));
		return true;
	}
}
