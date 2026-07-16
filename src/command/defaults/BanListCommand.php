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

use pocketmine\command\CommandSender;
use pocketmine\command\OverloadedCommand;
use pocketmine\command\overload\StringArgumentParser;
use pocketmine\lang\KnownTranslationFactory;
use pocketmine\permission\BanEntry;
use pocketmine\permission\DefaultPermissionNames;
use function array_map;
use function count;
use function implode;
use function sort;
use const SORT_STRING;

class BanListCommand extends OverloadedCommand{

	public function __construct(){
		parent::__construct(
			"banlist",
			KnownTranslationFactory::pocketmine_command_banlist_description(),
			KnownTranslationFactory::commands_banlist_usage()
		);
		$this->setPermission(DefaultPermissionNames::COMMAND_BAN_LIST);

		$this->addOverload(
			fn(CommandSender $sender, string $type = "players") => $this->run($sender, $type),
			explicitParsers: ["type" => new StringArgumentParser(["ips", "players"])]
		);
	}

	private function run(CommandSender $sender, string $type) : bool{
		$list = $type === "ips" ? $sender->getServer()->getIPBans() : $sender->getServer()->getNameBans();

		$names = array_map(fn(BanEntry $entry) => $entry->getName(), $list->getEntries());
		sort($names, SORT_STRING);

		$sender->sendMessage($type === "ips" ? KnownTranslationFactory::commands_banlist_ips((string) count($names)) : KnownTranslationFactory::commands_banlist_players((string) count($names)));
		$sender->sendMessage(implode(", ", $names));

		return true;
	}
}
