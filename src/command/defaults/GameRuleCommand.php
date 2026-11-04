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

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\OverloadedCommand;
use pocketmine\lang\KnownTranslationFactory;
use pocketmine\permission\DefaultPermissionNames;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use pocketmine\world\gamerule\GameRuleType;
use function count;
use function implode;
use function is_bool;
use function preg_match;
use function strtolower;

class GameRuleCommand extends OverloadedCommand{

	public function __construct(){
		parent::__construct(
			"gamerule",
			KnownTranslationFactory::pocketmine_command_gamerule_description(),
			KnownTranslationFactory::commands_gamerule_usage()
		);
		$this->setPermission(DefaultPermissionNames::COMMAND_GAMERULE);

		$this->addOverload(
			fn(CommandSender $sender) => $this->listRules($sender)
		);
		$this->addOverload(
			fn(CommandSender $sender, string $rule) => $this->queryRule($sender, $rule)
		);
		$this->addOverload(
			fn(CommandSender $sender, string $rule, string $value) => $this->setRule($sender, $rule, $value)
		);
	}

	private function listRules(CommandSender $sender) : bool{
		$world = $sender instanceof Player ? $sender->getWorld() : $sender->getServer()->getWorldManager()->getDefaultWorld();
		if($world === null){
			$sender->sendMessage(TextFormat::RED . "No world available to manage game rules");
			return false;
		}

		$rules = $world->getGameRules();
		$list = [];
		foreach($rules->getAll() as $name => $val){
			$display = is_bool($val) ? ($val ? "true" : "false") : (string) $val;
			$list[] = "$name = $display";
		}
		$sender->sendMessage(TextFormat::GREEN . "Game rules:\n" . TextFormat::GRAY . implode(", ", $list));
		return true;
	}

	private function queryRule(CommandSender $sender, string $rule) : bool{
		$world = $sender instanceof Player ? $sender->getWorld() : $sender->getServer()->getWorldManager()->getDefaultWorld();
		if($world === null){
			$sender->sendMessage(TextFormat::RED . "No world available to manage game rules");
			return false;
		}

		$rules = $world->getGameRules();
		$canonical = $rules->canonicalizeName($rule);
		if($canonical === null){
			$sender->sendMessage(TextFormat::RED . "No game rule called '$rule' is available");
			return false;
		}

		$value = $rules->get($canonical);
		$display = is_bool($value) ? ($value ? "true" : "false") : (string) $value;
		$sender->sendMessage(TextFormat::WHITE . "$canonical = $display");
		return true;
	}

	private function setRule(CommandSender $sender, string $rule, string $value) : bool{
		$world = $sender instanceof Player ? $sender->getWorld() : $sender->getServer()->getWorldManager()->getDefaultWorld();
		if($world === null){
			$sender->sendMessage(TextFormat::RED . "No world available to manage game rules");
			return false;
		}

		$rules = $world->getGameRules();
		$canonical = $rules->canonicalizeName($rule);
		if($canonical === null){
			$sender->sendMessage(TextFormat::RED . "No game rule called '$rule' is available");
			return false;
		}

		$type = $rules->getType($canonical);

		if($type === GameRuleType::BOOL){
			$lower = strtolower($value);
			if($lower === "true" || $lower === "1"){
				$parsed = true;
			}elseif($lower === "false" || $lower === "0"){
				$parsed = false;
			}else{
				$sender->sendMessage(TextFormat::RED . "Expected boolean value ('true' or 'false') for rule '$canonical'");
				return false;
			}
		}else{
			if(preg_match('/^[-+]?\d+$/', $value) !== 1){
				$sender->sendMessage(TextFormat::RED . "Expected integer value for rule '$canonical'");
				return false;
			}
			$parsed = (int) $value;
		}

		if(!$rules->set($canonical, $parsed)){
			$sender->sendMessage(TextFormat::RED . "Value '$value' is out of range for rule '$canonical'");
			return false;
		}

		$display = is_bool($parsed) ? ($parsed ? "true" : "false") : (string) $parsed;
		Command::broadcastCommandMessage($sender, KnownTranslationFactory::commands_gamerule_success($canonical, $display));

		return true;
	}
}
