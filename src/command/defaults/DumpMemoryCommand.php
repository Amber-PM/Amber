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
use pocketmine\lang\KnownTranslationFactory;
use pocketmine\permission\DefaultPermissionNames;
use Symfony\Component\Filesystem\Path;
use function date;

class DumpMemoryCommand extends OverloadedCommand{

	public function __construct(){
		parent::__construct(
			"dumpmemory",
			KnownTranslationFactory::pocketmine_command_dumpmemory_description(),
			"/dumpmemory [path]"
		);
		$this->setPermission(DefaultPermissionNames::COMMAND_DUMPMEMORY);

		$this->addOverload(fn(CommandSender $sender, ?string $path = null) => $this->run($sender, $path));
	}

	private function run(CommandSender $sender, ?string $path) : bool{
		$sender->getServer()->getMemoryManager()->dumpServerMemory(
			$path ?? Path::join($sender->getServer()->getDataPath(), "memory_dumps", date("D_M_j-H.i.s-T_Y")),
			48,
			80
		);

		return true;
	}
}
