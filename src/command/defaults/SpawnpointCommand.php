<?php

declare(strict_types=1);

namespace pocketmine\command\defaults;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\OverloadedCommand;
use pocketmine\command\overload\PlayerOrSelfArgumentParser;
use pocketmine\command\overload\Vector3ArgumentParser;
use pocketmine\lang\KnownTranslationFactory;
use pocketmine\math\Vector3;
use pocketmine\permission\DefaultPermissionNames;
use pocketmine\player\Player;
use pocketmine\world\Position;
use function round;

class SpawnpointCommand extends OverloadedCommand{

	public function __construct(){
		parent::__construct(
			"spawnpoint",
			KnownTranslationFactory::pocketmine_command_spawnpoint_description(),
			KnownTranslationFactory::commands_spawnpoint_usage()
		);
		$this->setPermissions([
			DefaultPermissionNames::COMMAND_SPAWNPOINT_SELF,
			DefaultPermissionNames::COMMAND_SPAWNPOINT_OTHER
		]);

		$this->addOverload(
			fn(Player $sender) => $this->setSpawn($sender, $sender, $sender->getPosition()->asVector3())
		);
		$this->addOverload(
			fn(Player $sender, Player $target) => $this->setSpawn($sender, $target, $sender->getPosition()->asVector3()),
			explicitParsers: ["target" => new PlayerOrSelfArgumentParser()]
		);
		$this->addOverload(
			fn(CommandSender $sender, Player $target, Vector3 $position) => $this->setSpawn($sender, $target, $position),
			explicitParsers: ["target" => new PlayerOrSelfArgumentParser(), "position" => new Vector3ArgumentParser()]
		);
	}

	private function setSpawn(CommandSender $sender, Player $target, Vector3 $position) : bool{
		$permission = $target === $sender ? DefaultPermissionNames::COMMAND_SPAWNPOINT_SELF : DefaultPermissionNames::COMMAND_SPAWNPOINT_OTHER;
		if(!$this->testPermission($sender, $permission)){
			return true;
		}

		$spawn = $position->floor();
		$target->setSpawn(Position::fromObject($spawn, $target->getWorld()));

		Command::broadcastCommandMessage($sender, KnownTranslationFactory::pocketmine_command_spawnpoint_success(
			$target->getName(),
			(string) round($spawn->x, 2),
			(string) round($spawn->y, 2),
			(string) round($spawn->z, 2)
		));
		return true;
	}
}
