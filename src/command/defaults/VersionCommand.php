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
use pocketmine\lang\KnownTranslationFactory;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\permission\DefaultPermissionNames;
use pocketmine\plugin\Plugin;
use pocketmine\utils\TextFormat;
use pocketmine\utils\Utils;
use pocketmine\VersionInfo;
use function count;
use function implode;
use function sprintf;
use function stripos;
use function strtolower;
use const PHP_VERSION;

class VersionCommand extends OverloadedCommand{

	public function __construct(){
		parent::__construct(
			"version",
			KnownTranslationFactory::pocketmine_command_version_description(),
			KnownTranslationFactory::pocketmine_command_version_usage(),
			["ver", "about"]
		);
		$this->setPermission(DefaultPermissionNames::COMMAND_VERSION);

		$this->addOverload(fn(CommandSender $sender) => $this->showServerVersion($sender));
		//NOTE: unlike the original implementation, this only matches a single word. Plugins with spaces in
		//their name will need to be looked up by a distinctive single-word substring instead.
		$this->addOverload(fn(CommandSender $sender, string $plugin) => $this->showPluginVersion($sender, $plugin));
	}

	private function showServerVersion(CommandSender $sender) : bool{
		$sender->sendMessage(KnownTranslationFactory::pocketmine_command_version_serverSoftwareName(
			TextFormat::GREEN . VersionInfo::NAME . TextFormat::RESET
		));
		$versionColor = VersionInfo::IS_DEVELOPMENT_BUILD ? TextFormat::YELLOW : TextFormat::GREEN;
		$sender->sendMessage(KnownTranslationFactory::pocketmine_command_version_serverSoftwareVersion(
			$versionColor . VersionInfo::VERSION()->getFullVersion() . TextFormat::RESET,
			TextFormat::GREEN . VersionInfo::GIT_HASH() . TextFormat::RESET
		));
		$sender->sendMessage(KnownTranslationFactory::pocketmine_command_version_minecraftVersion(
			TextFormat::GREEN . ProtocolInfo::MINECRAFT_VERSION_NETWORK . TextFormat::RESET,
			TextFormat::GREEN . ProtocolInfo::CURRENT_PROTOCOL . TextFormat::RESET
		));
		$sender->sendMessage(KnownTranslationFactory::pocketmine_command_version_phpVersion(TextFormat::GREEN . PHP_VERSION . TextFormat::RESET));

		$jitMode = Utils::getOpcacheJitMode();
		if($jitMode !== null){
			if($jitMode !== 0){
				$jitStatus = KnownTranslationFactory::pocketmine_command_version_phpJitEnabled(sprintf("CRTO: %d", $jitMode));
			}else{
				$jitStatus = KnownTranslationFactory::pocketmine_command_version_phpJitDisabled();
			}
		}else{
			$jitStatus = KnownTranslationFactory::pocketmine_command_version_phpJitNotSupported();
		}
		$sender->sendMessage(KnownTranslationFactory::pocketmine_command_version_phpJitStatus($jitStatus->format(TextFormat::GREEN, TextFormat::RESET)));
		$sender->sendMessage(KnownTranslationFactory::pocketmine_command_version_operatingSystem(TextFormat::GREEN . Utils::getOS() . TextFormat::RESET));

		return true;
	}

	private function showPluginVersion(CommandSender $sender, string $pluginName) : bool{
		$exactPlugin = $sender->getServer()->getPluginManager()->getPlugin($pluginName);

		if($exactPlugin instanceof Plugin){
			$this->describeToSender($exactPlugin, $sender);
			return true;
		}

		$found = false;
		$search = strtolower($pluginName);
		foreach($sender->getServer()->getPluginManager()->getPlugins() as $plugin){
			if(stripos($plugin->getName(), $search) !== false){
				$this->describeToSender($plugin, $sender);
				$found = true;
			}
		}

		if(!$found){
			$sender->sendMessage(KnownTranslationFactory::pocketmine_command_version_noSuchPlugin());
		}

		return true;
	}

	private function describeToSender(Plugin $plugin, CommandSender $sender) : void{
		$desc = $plugin->getDescription();
		$sender->sendMessage(KnownTranslationFactory::pocketmine_command_version_plugin_header(
			TextFormat::DARK_GREEN . $desc->getName() . TextFormat::RESET,
			TextFormat::DARK_GREEN . $desc->getVersion() . TextFormat::RESET
		));

		if($desc->getDescription() !== ""){
			$sender->sendMessage($desc->getDescription());
		}

		if($desc->getWebsite() !== ""){
			$sender->sendMessage(KnownTranslationFactory::pocketmine_command_version_plugin_website($desc->getWebsite()));
		}

		if(count($authors = $desc->getAuthors()) > 0){
			if(count($authors) === 1){
				$sender->sendMessage(KnownTranslationFactory::pocketmine_command_version_plugin_author(implode(", ", $authors)));
			}else{
				$sender->sendMessage(KnownTranslationFactory::pocketmine_command_version_plugin_authors(implode(", ", $authors)));
			}
		}
	}
}
