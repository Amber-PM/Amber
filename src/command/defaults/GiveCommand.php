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
use pocketmine\command\overload\IntegerArgumentParser;
use pocketmine\command\overload\ItemArgumentParser;
use pocketmine\command\overload\PlayerOrSelfArgumentParser;
use pocketmine\item\Item;
use pocketmine\lang\KnownTranslationFactory;
use pocketmine\nbt\JsonNbtParser;
use pocketmine\nbt\NbtDataException;
use pocketmine\nbt\NbtException;
use pocketmine\permission\DefaultPermissionNames;
use pocketmine\player\Player;

class GiveCommand extends OverloadedCommand{

	public function __construct(){
		parent::__construct(
			"give",
			KnownTranslationFactory::pocketmine_command_give_description(),
			KnownTranslationFactory::pocketmine_command_give_usage()
		);
		$this->setPermissions([
			DefaultPermissionNames::COMMAND_GIVE_SELF,
			DefaultPermissionNames::COMMAND_GIVE_OTHER
		]);

		$this->addOverload(
			fn(CommandSender $sender, Player $target, Item $item, ?int $count = null, string $nbt = "") => $this->give($sender, $target, $item, $count, $nbt),
			explicitParsers: [
				"target" => new PlayerOrSelfArgumentParser(),
				"item" => new ItemArgumentParser(),
				"count" => new IntegerArgumentParser(1, 32767),
				"nbt" => new GreedyStringArgumentParser()
			]
		);
	}

	private function give(CommandSender $sender, Player $target, Item $item, ?int $count, string $nbt) : bool{
		if(!$this->testPermission($sender, $target === $sender ? DefaultPermissionNames::COMMAND_GIVE_SELF : DefaultPermissionNames::COMMAND_GIVE_OTHER)){
			return true;
		}

		$item->setCount($count ?? $item->getMaxStackSize());

		if($nbt !== ""){
			try{
				$tags = JsonNbtParser::parseJson($nbt);
			}catch(NbtDataException $e){
				$sender->sendMessage(KnownTranslationFactory::commands_give_tagError($e->getMessage()));
				return true;
			}

			try{
				$item->setNamedTag($tags);
			}catch(NbtException $e){
				$sender->sendMessage(KnownTranslationFactory::commands_give_tagError($e->getMessage()));
				return true;
			}
		}

		$target->getInventory()->addItem($item);

		Command::broadcastCommandMessage($sender, KnownTranslationFactory::commands_give_success(
			$item->getName(),
			(string) $item->getCount(),
			$target->getName()
		));
		return true;
	}
}
