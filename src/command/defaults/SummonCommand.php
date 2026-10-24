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
use pocketmine\command\utils\InvalidCommandSyntaxException;
use pocketmine\entity\EntityFactory;
use pocketmine\entity\Location;
use pocketmine\lang\Translatable;
use pocketmine\permission\DefaultPermissionNames;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use function count;
use function str_starts_with;

class SummonCommand extends VanillaCommand{

	public function __construct(){
		parent::__construct(
			"summon",
			"Summons an entity",
			"/summon <entityType: string> [spawnPos: x y z] [spawnEvent: string] [nameTag: string]"
		);
		$this->setPermission(DefaultPermissionNames::COMMAND_SUMMON);
	}

	public function execute(CommandSender $sender, string $commandLabel, array $args) : bool{
		if(!$this->testPermission($sender)){
			return true;
		}

		if(count($args) < 1){
			throw new InvalidCommandSyntaxException();
		}

		$entityType = $args[0];

		$world = null;
		$x = 0.0;
		$y = 0.0;
		$z = 0.0;
		$yaw = 0.0;
		$pitch = 0.0;

		if($sender instanceof Player){
			$world = $sender->getWorld();
			$x = $sender->getPosition()->x;
			$y = $sender->getPosition()->y;
			$z = $sender->getPosition()->z;
			$yaw = $sender->getLocation()->yaw;
			$pitch = $sender->getLocation()->pitch;
		}else{
			$world = $sender->getServer()->getWorldManager()->getDefaultWorld();
			if($world !== null){
				$spawn = $world->getSpawnLocation();
				$x = $spawn->x;
				$y = $spawn->y;
				$z = $spawn->z;
			}
		}

		if($world === null){
			$sender->sendMessage(TextFormat::RED . "No world available to summon entity");
			return false;
		}

		if(isset($args[1]) && isset($args[2]) && isset($args[3])){
			$x = (float) $this->getRelativeDouble($x, $sender, $args[1]);
			$y = (float) $this->getRelativeDouble($y, $sender, $args[2]);
			$z = (float) $this->getRelativeDouble($z, $sender, $args[3]);
		}

		$location = new Location($x, $y, $z, $world, $yaw, $pitch);

		$nameTag = null;
		if(isset($args[5])){
			$nameTag = $args[5];
		}elseif(isset($args[4]) && !str_starts_with($args[4], "minecraft:")){
			$nameTag = $args[4];
		}

		$entity = EntityFactory::getInstance()->create($entityType, $location);
		if($entity === null){
			$sender->sendMessage(TextFormat::RED . "Could not summon entity: Unknown entity type '$entityType'");
			return false;
		}

		if($nameTag !== null){
			$entity->setNameTag($nameTag);
			$entity->setNameTagVisible(true);
			$entity->setNameTagAlwaysVisible(true);
		}

		$entity->spawnToAll();
		Command::broadcastCommandMessage($sender, new Translatable("commands.summon.success", [], "Object successfully summoned"));

		return true;
	}
}
