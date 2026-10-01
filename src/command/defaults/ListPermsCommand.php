<?php

/*
 *
 *     _             _               
 *    / \   _ __ ___ | |__   ___ _ __ 
 *   / _ \ | '_ ` _ \| '_ \ / _ \ '__|
 *  / ___ \| | | | | | |_) |  __/ |   
 * /_/   \_\_| |_| |_|_.__/ \___|_|   
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author AmberPM Team
 * @link https://github.com/Amber-PM/Amber
 *
 *
 */

declare(strict_types=1);

namespace pocketmine\command\defaults;

use pocketmine\command\CommandSender;
use pocketmine\command\OverloadedCommand;
use pocketmine\lang\KnownTranslationFactory;
use pocketmine\permission\DefaultPermissionNames;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use function implode;
use function sort;

class ListPermsCommand extends OverloadedCommand{

	public function __construct(){
		parent::__construct(
			"listperms",
			KnownTranslationFactory::pocketmine_command_listperms_description(),
			KnownTranslationFactory::pocketmine_command_listperms_usage(),
			["listpermissions"]
		);
		$this->setPermission(DefaultPermissionNames::COMMAND_LISTPERMS);

		$this->addOverload(fn(CommandSender $sender, ?Player $player = null) => $this->list($sender, $player ?? $sender));
	}

	private function list(CommandSender $sender, CommandSender $target) : bool{
		$perms = [];
		foreach($target->getEffectivePermissions() as $info){
			$perms[] = $info->getPermission() . ": " . ($info->getValue() ? "true" : "false");
		}
		sort($perms);

		$sender->sendMessage(TextFormat::GREEN . "Effective permissions for " . $target->getName() . ":\n" . TextFormat::RESET . implode("\n", $perms));
		return true;
	}
}
