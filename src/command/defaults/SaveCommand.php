<?php

declare(strict_types=1);

namespace pocketmine\command\defaults;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\OverloadedCommand;
use pocketmine\lang\KnownTranslationFactory;
use pocketmine\permission\DefaultPermissionNames;
use function microtime;
use function round;

class SaveCommand extends OverloadedCommand{

	public function __construct(){
		parent::__construct(
			"save-all",
			KnownTranslationFactory::pocketmine_command_save_description()
		);
		$this->setPermission(DefaultPermissionNames::COMMAND_SAVE_PERFORM);

		$this->addOverload(
			fn(CommandSender $sender) => $this->saveAll($sender)
		);
	}

	private function saveAll(CommandSender $sender) : bool{
		Command::broadcastCommandMessage($sender, KnownTranslationFactory::pocketmine_save_start());
		$start = microtime(true);

		foreach($sender->getServer()->getOnlinePlayers() as $player){
			$player->save();
		}
		foreach($sender->getServer()->getWorldManager()->getWorlds() as $world){
			$world->save(true);
		}

		Command::broadcastCommandMessage($sender, KnownTranslationFactory::pocketmine_save_success((string) round(microtime(true) - $start, 3)));
		return true;
	}
}
