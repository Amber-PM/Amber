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
use pocketmine\command\overload\ItemArgumentParser;
use pocketmine\inventory\Inventory;
use pocketmine\item\Item;
use pocketmine\lang\KnownTranslationFactory;
use pocketmine\permission\DefaultPermissionNames;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use function min;

class ClearCommand extends OverloadedCommand{

	public function __construct(){
		parent::__construct(
			"clear",
			KnownTranslationFactory::pocketmine_command_clear_description(),
			KnownTranslationFactory::pocketmine_command_clear_usage()
		);
		$this->setPermissions([DefaultPermissionNames::COMMAND_CLEAR_SELF, DefaultPermissionNames::COMMAND_CLEAR_OTHER]);

		$this->addOverload(
			fn(Player $sender, ?Item $item = null, ?int $maxCount = null) => $this->run($sender, $sender, $item, $maxCount),
			DefaultPermissionNames::COMMAND_CLEAR_SELF,
			["item" => new ItemArgumentParser()]
		);
		$this->addOverload(
			fn(CommandSender $sender, Player $target, ?Item $item = null, ?int $maxCount = null) => $this->run($sender, $target, $item, $maxCount),
			DefaultPermissionNames::COMMAND_CLEAR_OTHER,
			["item" => new ItemArgumentParser()]
		);
	}

	private function run(CommandSender $sender, Player $target, ?Item $item, ?int $maxCount) : bool{
		$inventories = [
			$target->getInventory(),
			$target->getCursorInventory(),
			$target->getArmorInventory(),
			$target->getOffHandInventory()
		];

		if($item !== null && $maxCount === 0){
			$count = $this->countItems($inventories, $item);
			if($count > 0){
				$sender->sendMessage(KnownTranslationFactory::commands_clear_testing($target->getName(), (string) $count));
			}else{
				$sender->sendMessage(KnownTranslationFactory::commands_clear_failure_no_items($target->getName())->prefix(TextFormat::RED));
			}

			return true;
		}

		$clearedCount = 0;
		if($item === null){
			$clearedCount += $this->countItems($inventories, null);
			foreach($inventories as $inventory){
				$inventory->clearAll();
			}
		}elseif($maxCount === null){
			$clearedCount += $this->countItems($inventories, $item);
			foreach($inventories as $inventory){
				$inventory->remove($item);
			}
		}else{
			$remaining = $maxCount;
			foreach($inventories as $inventory){
				foreach($inventory->all($item) as $index => $stack){
					$reduction = min($stack->getCount(), $remaining);
					$stack->pop($reduction);
					$clearedCount += $reduction;
					$inventory->setItem($index, $stack);

					$remaining -= $reduction;
					if($remaining <= 0){
						break 2;
					}
				}
			}
		}

		if($clearedCount > 0){
			Command::broadcastCommandMessage($sender, KnownTranslationFactory::commands_clear_success($target->getName(), (string) $clearedCount));
		}else{
			$sender->sendMessage(KnownTranslationFactory::commands_clear_failure_no_items($target->getName())->prefix(TextFormat::RED));
		}

		return true;
	}

	/**
	 * @param Inventory[] $inventories
	 */
	private function countItems(array $inventories, ?Item $target) : int{
		$count = 0;
		foreach($inventories as $inventory){
			$contents = $target !== null ? $inventory->all($target) : $inventory->getContents();
			foreach($contents as $item){
				$count += $item->getCount();
			}
		}

		return $count;
	}
}
