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
use pocketmine\command\overload\GreedyStringArgumentParser;
use pocketmine\lang\KnownTranslationFactory;
use pocketmine\permission\DefaultPermissionNames;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;

class TellCommand extends OverloadedCommand{

	public function __construct(){
		parent::__construct(
			"tell",
			KnownTranslationFactory::pocketmine_command_tell_description(),
			KnownTranslationFactory::commands_message_usage(),
			["w", "msg"]
		);
		$this->setPermission(DefaultPermissionNames::COMMAND_TELL);

		$this->addOverload(
			fn(CommandSender $sender, Player $target, string $message) => $this->tell($sender, $target, $message),
			DefaultPermissionNames::COMMAND_TELL,
			["message" => new GreedyStringArgumentParser()]
		);
	}

	private function tell(CommandSender $sender, Player $target, string $message) : bool{
		if($target === $sender){
			$sender->sendMessage(KnownTranslationFactory::commands_message_sameTarget()->prefix(TextFormat::RED));
			return true;
		}

		$sender->sendMessage(KnownTranslationFactory::commands_message_display_outgoing($target->getDisplayName(), $message)->prefix(TextFormat::GRAY . TextFormat::ITALIC));

		$name = $sender instanceof Player ? $sender->getDisplayName() : $sender->getName();
		$target->sendMessage(KnownTranslationFactory::commands_message_display_incoming($name, $message)->prefix(TextFormat::GRAY . TextFormat::ITALIC));

		Command::broadcastCommandMessage($sender, KnownTranslationFactory::commands_message_display_outgoing($target->getDisplayName(), $message), false);

		return true;
	}
}
