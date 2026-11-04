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
use pocketmine\command\overload\GreedyStringArgumentParser;
use pocketmine\command\overload\Vector3ArgumentParser;
use pocketmine\entity\EntityFactory;
use pocketmine\data\SavedDataLoadingException;
use pocketmine\entity\Location;
use pocketmine\lang\KnownTranslationFactory;
use pocketmine\math\Vector3;
use pocketmine\permission\DefaultPermissionNames;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use function in_array;
use function str_starts_with;
use function strtolower;
use function substr;

class SummonCommand extends OverloadedCommand{

	public function __construct(){
		parent::__construct(
			"summon",
			KnownTranslationFactory::pocketmine_command_summon_description(),
			KnownTranslationFactory::commands_summon_usage()
		);
		$this->setPermission(DefaultPermissionNames::COMMAND_SUMMON);

		$this->addOverload(
			fn(CommandSender $sender, string $entityType) => $this->summon($sender, $entityType, null, null)
		);
		$this->addOverload(
			fn(CommandSender $sender, string $entityType, Vector3 $spawnPos) => $this->summon($sender, $entityType, $spawnPos, null),
			explicitParsers: ["spawnPos" => new Vector3ArgumentParser()]
		);
		$this->addOverload(
			fn(CommandSender $sender, string $entityType, Vector3 $spawnPos, string $nameTag) => $this->summon($sender, $entityType, $spawnPos, $nameTag),
			explicitParsers: ["spawnPos" => new Vector3ArgumentParser(), "nameTag" => new GreedyStringArgumentParser()]
		);
	}

	private function summon(CommandSender $sender, string $entityType, ?Vector3 $spawnPos, ?string $nameTag) : bool{
		$world = null;
		$yaw = 0.0;
		$pitch = 0.0;

		if($sender instanceof Player){
			$world = $sender->getWorld();
			$yaw = $sender->getLocation()->yaw;
			$pitch = $sender->getLocation()->pitch;
		}else{
			$world = $sender->getServer()->getWorldManager()->getDefaultWorld();
		}

		if($world === null){
			$sender->sendMessage(TextFormat::RED . "No world available to summon entity");
			return false;
		}

		if($spawnPos === null){
			if($sender instanceof Player){
				$spawnPos = $sender->getPosition();
			}else{
				$spawnPos = $world->getSpawnLocation();
			}
		}

		$location = new Location($spawnPos->x, $spawnPos->y, $spawnPos->z, $world, $yaw, $pitch);

		// Guard against unsupported entity types that require specialized save data not provided by /summon
		$cleanType = strtolower(str_starts_with($entityType, "minecraft:") ? substr($entityType, 10) : $entityType);
		if(in_array($cleanType, ["item", "painting", "falling_block", "falling_sand", "trident"], true)){
			$sender->sendMessage(TextFormat::RED . "Cannot summon '$entityType': unsupported entity type");
			return false;
		}

		try{
			$entity = EntityFactory::getInstance()->create($entityType, $location);
		}catch(SavedDataLoadingException){
			$sender->sendMessage(TextFormat::RED . "Cannot summon '$entityType': missing required data");
			return false;
		}catch(\Throwable){
			$sender->sendMessage(TextFormat::RED . "Could not summon entity: '$entityType'");
			return false;
		}

		if($entity === null){
			$sender->sendMessage(TextFormat::RED . "Could not summon entity: Unknown entity type '$entityType'");
			return false;
		}

		if($nameTag !== null && $nameTag !== ""){
			$entity->setNameTag($nameTag);
			$entity->setNameTagVisible(true);
			$entity->setNameTagAlwaysVisible(true);
		}

		$entity->spawnToAll();
		Command::broadcastCommandMessage($sender, KnownTranslationFactory::commands_summon_success());

		return true;
	}
}
