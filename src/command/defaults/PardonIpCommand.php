<?php

declare(strict_types=1);

namespace pocketmine\command\defaults;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\OverloadedCommand;
use pocketmine\lang\KnownTranslationFactory;
use pocketmine\permission\DefaultPermissionNames;
use function inet_pton;

class PardonIpCommand extends OverloadedCommand{

	public function __construct(){
		parent::__construct(
			"pardon-ip",
			KnownTranslationFactory::pocketmine_command_unban_ip_description(),
			KnownTranslationFactory::commands_unbanip_usage(),
			["unban-ip"]
		);
		$this->setPermission(DefaultPermissionNames::COMMAND_UNBAN_IP);

		$this->addOverload(
			fn(CommandSender $sender, string $ip) => $this->pardonIp($sender, $ip)
		);
	}

	private function pardonIp(CommandSender $sender, string $ip) : bool{
		if(inet_pton($ip) === false){
			$sender->sendMessage(KnownTranslationFactory::commands_unbanip_invalid());
			return true;
		}

		$sender->getServer()->getIPBans()->remove($ip);
		$sender->getServer()->getNetwork()->unblockAddress($ip);
		Command::broadcastCommandMessage($sender, KnownTranslationFactory::commands_unbanip_success($ip));
		return true;
	}
}
