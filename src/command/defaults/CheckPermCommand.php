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

class CheckPermCommand extends OverloadedCommand{

	public function __construct(){
		parent::__construct(
			"checkperm",
			KnownTranslationFactory::pocketmine_command_checkperm_description(),
			KnownTranslationFactory::pocketmine_command_checkperm_usage()
		);
		$this->setPermission(DefaultPermissionNames::COMMAND_CHECKPERM);

		$this->addOverload(fn(CommandSender $sender, string $permission, ?Player $player = null) => $this->check($sender, $permission, $player ?? $sender));
	}

	private function check(CommandSender $sender, string $permission, CommandSender $target) : bool{
		$sender->sendMessage(KnownTranslationFactory::pocketmine_command_checkperm_success(
			$permission,
			$target->getName(),
			$target->hasPermission($permission) ? "true" : "false"
		));
		return true;
	}
}
