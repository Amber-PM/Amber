<?php

declare(strict_types=1);

namespace pocketmine\command\defaults;

use pocketmine\command\CommandSender;
use pocketmine\command\OverloadedCommand;
use pocketmine\lang\KnownTranslationFactory;
use pocketmine\permission\DefaultPermissionNames;
use pocketmine\player\Player;
use function array_filter;
use function array_map;
use function count;
use function implode;
use function sort;
use const SORT_STRING;

class ListCommand extends OverloadedCommand{

	public function __construct(){
		parent::__construct(
			"list",
			KnownTranslationFactory::pocketmine_command_list_description()
		);
		$this->setPermission(DefaultPermissionNames::COMMAND_LIST);

		$this->addOverload(
			fn(CommandSender $sender) => $this->listPlayers($sender)
		);
	}

	private function listPlayers(CommandSender $sender) : bool{
		$visiblePlayers = array_filter(
			$sender->getServer()->getOnlinePlayers(),
			fn(Player $player) : bool => !($sender instanceof Player) || $sender->canSee($player)
		);
		$playerNames = array_map(fn(Player $player) : string => $player->getName(), $visiblePlayers);
		sort($playerNames, SORT_STRING);

		$sender->sendMessage(KnownTranslationFactory::commands_players_list((string) count($playerNames), (string) $sender->getServer()->getMaxPlayers()));
		$sender->sendMessage(implode(", ", $playerNames));
		return true;
	}
}
