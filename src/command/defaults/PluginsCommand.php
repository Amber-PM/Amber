<?php

declare(strict_types=1);

namespace pocketmine\command\defaults;

use pocketmine\command\CommandSender;
use pocketmine\command\OverloadedCommand;
use pocketmine\lang\KnownTranslationFactory;
use pocketmine\permission\DefaultPermissionNames;
use pocketmine\plugin\Plugin;
use pocketmine\utils\TextFormat;
use function array_map;
use function count;
use function implode;
use function sort;
use const SORT_STRING;

class PluginsCommand extends OverloadedCommand{

	public function __construct(){
		parent::__construct(
			"plugins",
			KnownTranslationFactory::pocketmine_command_plugins_description(),
			null,
			["pl"]
		);
		$this->setPermission(DefaultPermissionNames::COMMAND_PLUGINS);

		$this->addOverload(
			fn(CommandSender $sender) => $this->listPlugins($sender)
		);
	}

	private function listPlugins(CommandSender $sender) : bool{
		$list = array_map(
			fn(Plugin $plugin) : string => ($plugin->isEnabled() ? TextFormat::GREEN : TextFormat::RED) . $plugin->getDescription()->getFullName(),
			$sender->getServer()->getPluginManager()->getPlugins()
		);
		sort($list, SORT_STRING);

		$sender->sendMessage(KnownTranslationFactory::pocketmine_command_plugins_success((string) count($list), implode(TextFormat::RESET . ", ", $list)));
		return true;
	}
}
