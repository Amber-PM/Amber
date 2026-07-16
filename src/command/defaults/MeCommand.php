<?php

declare(strict_types=1);

namespace pocketmine\command\defaults;

use pocketmine\command\CommandSender;
use pocketmine\command\OverloadedCommand;
use pocketmine\command\overload\GreedyStringArgumentParser;
use pocketmine\lang\KnownTranslationFactory;
use pocketmine\permission\DefaultPermissionNames;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;

class MeCommand extends OverloadedCommand{

	public function __construct(){
		parent::__construct(
			"me",
			KnownTranslationFactory::pocketmine_command_me_description(),
			KnownTranslationFactory::commands_me_usage()
		);
		$this->setPermission(DefaultPermissionNames::COMMAND_ME);

		$this->addOverload(
			fn(CommandSender $sender, string $message) => $this->broadcastEmote($sender, $message),
			null,
			["message" => new GreedyStringArgumentParser()]
		);
	}

	private function broadcastEmote(CommandSender $sender, string $message) : bool{
		$name = $sender instanceof Player ? $sender->getDisplayName() : $sender->getName();
		$sender->getServer()->broadcastMessage(KnownTranslationFactory::chat_type_emote($name, TextFormat::RESET . $message));
		return true;
	}
}
