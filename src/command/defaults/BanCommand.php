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

class BanCommand extends OverloadedCommand{

	public function __construct(){
		parent::__construct(
			"ban",
			KnownTranslationFactory::pocketmine_command_ban_player_description(),
			KnownTranslationFactory::commands_ban_usage()
		);
		$this->setPermission(DefaultPermissionNames::COMMAND_BAN_PLAYER);

		$this->addOverload(
			fn(CommandSender $sender, string $name, string $reason) => $this->run($sender, $name, $reason),
			explicitParsers: ["reason" => new GreedyStringArgumentParser()]
		);
	}

	private function run(CommandSender $sender, string $name, string $reason) : bool{
		$sender->getServer()->getNameBans()->addBan($name, $reason, null, $sender->getName());

		$player = $sender->getServer()->getPlayerExact($name);
		if($player instanceof Player){
			$player->kick($reason !== "" ? KnownTranslationFactory::pocketmine_disconnect_ban($reason) : KnownTranslationFactory::pocketmine_disconnect_ban_noReason());
		}

		Command::broadcastCommandMessage($sender, KnownTranslationFactory::commands_ban_success($player !== null ? $player->getName() : $name));

		return true;
	}
}
