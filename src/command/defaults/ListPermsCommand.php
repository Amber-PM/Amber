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
 * @author PocketMine-MV Team
 * @link https://github.com/vapebw/PocketmineMV
 *
 *
 */

declare(strict_types=1);

namespace pocketmine\command\defaults;

use pocketmine\command\CommandSender;
use pocketmine\lang\KnownTranslationFactory;
use pocketmine\permission\DefaultPermissionNames;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use function array_shift;
use function count;
use function implode;
use function sort;

class ListPermsCommand extends VanillaCommand{

	public function __construct(){
		parent::__construct(
			"listperms",
			KnownTranslationFactory::pocketmine_command_listperms_description(),
			KnownTranslationFactory::pocketmine_command_listperms_usage(),
			["listpermissions"]
		);
		$this->setPermission(DefaultPermissionNames::COMMAND_LISTPERMS);
	}

	public function execute(CommandSender $sender, string $commandLabel, array $args){
		$target = $sender;
		if(count($args) > 0){
			$targetName = array_shift($args);
			$target = $sender->getServer()->getPlayerExact($targetName);
			if($target === null){
				$sender->sendMessage(KnownTranslationFactory::commands_generic_player_notFound()->prefix(TextFormat::RED));
				return true;
			}
		}

		$perms = [];
		foreach($target->getEffectivePermissions() as $info){
			$perms[] = $info->getPermission() . ": " . ($info->getValue() ? "true" : "false");
		}
		sort($perms);

		$sender->sendMessage(TextFormat::GREEN . "Effective permissions for " . $target->getName() . ":\n" . TextFormat::RESET . implode("\n", $perms));

		return true;
	}
}
