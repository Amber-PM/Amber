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
use pocketmine\lang\Translatable;
use pocketmine\permission\DefaultPermissionNames;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use pocketmine\world\gamerule\GameRuleType;
use function count;
use function implode;
use function is_bool;
use function is_numeric;
use function strtolower;

class GameRuleCommand extends VanillaCommand{

	public function __construct(){
		parent::__construct(
			"gamerule",
			"Sets or queries a game rule value",
			"/gamerule [rule: string] [value: bool|int]"
		);
		$this->setPermission(DefaultPermissionNames::COMMAND_GAMERULE);
	}

	public function execute(CommandSender $sender, string $commandLabel, array $args) : bool{
		if(!$this->testPermission($sender)){
			return true;
		}

		$world = $sender instanceof Player ? $sender->getWorld() : $sender->getServer()->getWorldManager()->getDefaultWorld();
		if($world === null){
			$sender->sendMessage(TextFormat::RED . "No world available to manage game rules");
			return false;
		}

		$rules = $world->getGameRules();

		if(count($args) === 0){
			$list = [];
			foreach($rules->getAll() as $name => $value){
				$display = is_bool($value) ? ($value ? "true" : "false") : (string) $value;
				$list[] = "$name = $display";
			}
			$sender->sendMessage(TextFormat::GREEN . "Game rules:\n" . TextFormat::GRAY . implode(", ", $list));
			return true;
		}

		$ruleName = $args[0];
		$canonical = $rules->canonicalizeName($ruleName);
		if($canonical === null){
			$sender->sendMessage(TextFormat::RED . "No game rule called '$ruleName' is available");
			return false;
		}

		if(count($args) === 1){
			$value = $rules->get($canonical);
			$display = is_bool($value) ? ($value ? "true" : "false") : (string) $value;
			$sender->sendMessage(TextFormat::WHITE . "$canonical = $display");
			return true;
		}

		$rawValue = $args[1];
		$type = $rules->getType($canonical);

		if($type === GameRuleType::BOOL){
			$lower = strtolower($rawValue);
			if($lower === "true" || $lower === "1"){
				$parsed = true;
			}elseif($lower === "false" || $lower === "0"){
				$parsed = false;
			}else{
				$sender->sendMessage(TextFormat::RED . "Expected boolean value ('true' or 'false') for rule '$canonical'");
				return false;
			}
		}else{
			if(!is_numeric($rawValue)){
				$sender->sendMessage(TextFormat::RED . "Expected integer value for rule '$canonical'");
				return false;
			}
			$parsed = (int) $rawValue;
		}

		$rules->set($canonical, $parsed);
		$display = is_bool($parsed) ? ($parsed ? "true" : "false") : (string) $parsed;
		Command::broadcastCommandMessage($sender, new Translatable("commands.gamerule.success", [$canonical, $display]));

		return true;
	}
}
