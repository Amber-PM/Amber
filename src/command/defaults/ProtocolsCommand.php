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

use pocketmine\command\CommandSender;
use pocketmine\command\OverloadedCommand;
use pocketmine\lang\KnownTranslationFactory;
use pocketmine\lang\Translatable;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\permission\DefaultPermissionNames;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use ReflectionClass;
use ReflectionException;
use function is_string;
use function mb_substr;
use function preg_replace;
use function str_replace;
use function str_starts_with;
use function substr;
use function trim;

class ProtocolsCommand extends OverloadedCommand{

	public function __construct(){
		parent::__construct(
			"protocols",
			KnownTranslationFactory::pocketmine_command_protocols_description(),
			KnownTranslationFactory::commands_protocols_usage(),
			["protocol"]
		);
		$this->setPermission(DefaultPermissionNames::COMMAND_PROTOCOLS);

		$this->addOverload(
			fn(CommandSender $sender) => $this->showAllProtocols($sender),
			DefaultPermissionNames::COMMAND_PROTOCOLS
		);
		$this->addOverload(
			fn(CommandSender $sender, Player $player) => $this->showPlayerProtocol($sender, $player),
			DefaultPermissionNames::COMMAND_PROTOCOLS
		);
	}

	public function getUsage() : Translatable|string{
		return $this->usageMessage ?? parent::getUsage();
	}

	private function showAllProtocols(CommandSender $sender) : bool{
		$sender->sendMessage(TextFormat::GOLD . "Accepted Protocols:");
		foreach(ProtocolInfo::ACCEPTED_PROTOCOL as $protocolId){
			$label = self::getVersionLabel($protocolId);
			$isAllowed = Server::getInstance()->isProtocolAllowed($protocolId);

			$statusText = $isAllowed ? TextFormat::GREEN . "Enabled" : TextFormat::RED . "Blocked";
			$sender->sendMessage("- " . TextFormat::YELLOW . $protocolId . TextFormat::RESET . " (" . $label . ") : " . $statusText);
		}

		return true;
	}

	private function showPlayerProtocol(CommandSender $sender, Player $player) : bool{
		$protocolId = $player->getNetworkSession()->getProtocolId();
		$versionLabel = self::getVersionLabel($protocolId);
		$extraData = $player->getPlayerInfo()->getExtraData();

		$message = TextFormat::AQUA . $player->getName() . TextFormat::RESET . " is on protocol " . TextFormat::YELLOW . $protocolId . TextFormat::RESET . " (" . TextFormat::GREEN . $versionLabel . TextFormat::RESET . ")";

		if(isset($extraData["GameVersion"]) && is_string($extraData["GameVersion"])){
			$cleanVersion = TextFormat::clean($extraData["GameVersion"]);
			$cleanVersion = preg_replace('/[\x00-\x1f\x7f]/', '', $cleanVersion) ?? '';
			$cleanVersion = mb_substr(trim($cleanVersion), 0, 32, "UTF-8");
			if($cleanVersion !== ""){
				$message .= " [client-reported: " . $cleanVersion . "]";
			}
		}

		$sender->sendMessage($message);
		return true;
	}

	public static function getVersionLabel(int $protocolId) : string{
		if($protocolId === 2192){
			return "1.26.50 preview/compatible";
		}
		if($protocolId === 2193){
			return "1.26.50";
		}

		try{
			$ref = new ReflectionClass(ProtocolInfo::class);
			foreach($ref->getConstants() as $name => $val){
				if($name === 'CURRENT_PROTOCOL' || $name === 'PROTOCOL_1_26_51' || $name === 'PROTOCOL_1_26_50') continue;
				if(str_starts_with($name, 'PROTOCOL_1_') && $val === $protocolId){
					return str_replace('_', '.', substr($name, 9));
				}
			}
		}catch(ReflectionException){
		}

		return "Unknown";
	}
}
