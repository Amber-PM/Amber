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
use pocketmine\command\overload\PlayerOrSelfArgumentParser;
use pocketmine\command\overload\StringArgumentParser;
use pocketmine\item\enchantment\EnchantingHelper;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\enchantment\StringToEnchantmentParser;
use pocketmine\lang\KnownTranslationFactory;
use pocketmine\permission\DefaultPermissionNames;
use pocketmine\player\Player;

class EnchantCommand extends OverloadedCommand{
	use BoundedNumberHelperTrait;

	public function __construct(){
		parent::__construct(
			"enchant",
			KnownTranslationFactory::pocketmine_command_enchant_description(),
			KnownTranslationFactory::commands_enchant_usage()
		);
		$this->setPermissions([
			DefaultPermissionNames::COMMAND_ENCHANT_SELF,
			DefaultPermissionNames::COMMAND_ENCHANT_OTHER
		]);

		$this->addOverload(
			fn(CommandSender $sender, Player $target, string $enchantment, ?int $level = null) => $this->applyEnchant($sender, $target, $enchantment, $level),
			explicitParsers: [
				"target" => new PlayerOrSelfArgumentParser(),
				"enchantment" => new StringArgumentParser(StringToEnchantmentParser::getInstance()->getKnownAliases())
			]
		);
	}

	private function applyEnchant(CommandSender $sender, Player $target, string $enchantmentName, ?int $level) : bool{
		//permission depends on whether the resolved target is the sender itself or someone else, same as vanilla
		if(!$this->testPermission($sender, $target === $sender ? DefaultPermissionNames::COMMAND_ENCHANT_SELF : DefaultPermissionNames::COMMAND_ENCHANT_OTHER)){
			return true;
		}

		$item = $target->getInventory()->getItemInHand();

		if($item->isNull()){
			$sender->sendMessage(KnownTranslationFactory::commands_enchant_noItem());
			return true;
		}

		$enchantment = StringToEnchantmentParser::getInstance()->parse($enchantmentName);
		if($enchantment === null){
			$sender->sendMessage(KnownTranslationFactory::commands_enchant_notFound($enchantmentName));
			return true;
		}

		$resolvedLevel = $this->getBoundedInt($sender, (string) ($level ?? 1), 1, $enchantment->getMaxLevel());
		if($resolvedLevel === null){
			return false;
		}

		//this is necessary to deal with enchanted books, which are a different item type than regular books
		$enchantedItem = EnchantingHelper::enchantItem($item, [new EnchantmentInstance($enchantment, $resolvedLevel)]);
		$target->getInventory()->setItemInHand($enchantedItem);

		self::broadcastCommandMessage($sender, KnownTranslationFactory::commands_enchant_success($target->getName()));
		return true;
	}
}
