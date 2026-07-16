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
use function trim;

class KickCommand extends OverloadedCommand{

	public function __construct(){
		parent::__construct(
			"kick",
			KnownTranslationFactory::pocketmine_command_kick_description(),
			KnownTranslationFactory::commands_kick_usage()
		);
		$this->setPermission(DefaultPermissionNames::COMMAND_KICK);

		$this->addOverload(
			fn(CommandSender $sender, Player $target, string $reason = "") => $this->kick($sender, $target, $reason),
			DefaultPermissionNames::COMMAND_KICK,
			["reason" => new GreedyStringArgumentParser()]
		);
	}

	private function kick(CommandSender $sender, Player $target, string $reason) : bool{
		$reason = trim($reason);

		$target->kick($reason !== "" ? KnownTranslationFactory::pocketmine_disconnect_kick($reason) : KnownTranslationFactory::pocketmine_disconnect_kick_noReason());
		if($reason !== ""){
			Command::broadcastCommandMessage($sender, KnownTranslationFactory::commands_kick_success_reason($target->getName(), $reason));
		}else{
			Command::broadcastCommandMessage($sender, KnownTranslationFactory::commands_kick_success($target->getName()));
		}

		return true;
	}
}
