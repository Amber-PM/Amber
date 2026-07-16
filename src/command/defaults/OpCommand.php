<?php

declare(strict_types=1);

namespace pocketmine\command\defaults;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\OverloadedCommand;
use pocketmine\command\utils\InvalidCommandSyntaxException;
use pocketmine\lang\KnownTranslationFactory;
use pocketmine\permission\DefaultPermissionNames;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;

class OpCommand extends OverloadedCommand{

	public function __construct(){
		parent::__construct(
			"op",
			KnownTranslationFactory::pocketmine_command_op_description(),
			KnownTranslationFactory::commands_op_usage()
		);
		$this->setPermission(DefaultPermissionNames::COMMAND_OP_GIVE);

		$this->addOverload(
			fn(CommandSender $sender, string $name) => $this->grantOp($sender, $name)
		);
	}

	private function grantOp(CommandSender $sender, string $name) : bool{
		if(!Player::isValidUserName($name)){
			throw new InvalidCommandSyntaxException();
		}

		$sender->getServer()->addOp($name);
		$target = $sender->getServer()->getPlayerExact($name);
		if($target !== null){
			$target->sendMessage(KnownTranslationFactory::commands_op_message()->prefix(TextFormat::GRAY));
		}

		Command::broadcastCommandMessage($sender, KnownTranslationFactory::commands_op_success($name));
		return true;
	}
}
