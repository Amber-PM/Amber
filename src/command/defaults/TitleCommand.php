<?php

declare(strict_types=1);

namespace pocketmine\command\defaults;

use pocketmine\command\CommandSender;
use pocketmine\command\OverloadedCommand;
use pocketmine\command\overload\GreedyStringArgumentParser;
use pocketmine\command\overload\PlayerOrSelfArgumentParser;
use pocketmine\command\overload\StringArgumentParser;
use pocketmine\lang\KnownTranslationFactory;
use pocketmine\permission\DefaultPermissionNames;
use pocketmine\player\Player;

class TitleCommand extends OverloadedCommand{

	public function __construct(){
		parent::__construct(
			"title",
			KnownTranslationFactory::pocketmine_command_title_description(),
			KnownTranslationFactory::commands_title_usage()
		);
		$this->setPermissions([
			DefaultPermissionNames::COMMAND_TITLE_SELF,
			DefaultPermissionNames::COMMAND_TITLE_OTHER
		]);

		$targetParser = new PlayerOrSelfArgumentParser();

		$this->addOverload(
			fn(CommandSender $sender, Player $target, string $action) => $this->clear($sender, $target),
			explicitParsers: ["target" => $targetParser, "action" => new StringArgumentParser(["clear"])]
		);
		$this->addOverload(
			fn(CommandSender $sender, Player $target, string $action) => $this->reset($sender, $target),
			explicitParsers: ["target" => $targetParser, "action" => new StringArgumentParser(["reset"])]
		);
		$this->addOverload(
			fn(CommandSender $sender, Player $target, string $action, string $text) => $this->sendTitle($sender, $target, $text),
			explicitParsers: ["target" => $targetParser, "action" => new StringArgumentParser(["title"]), "text" => new GreedyStringArgumentParser()]
		);
		$this->addOverload(
			fn(CommandSender $sender, Player $target, string $action, string $text) => $this->sendSubtitle($sender, $target, $text),
			explicitParsers: ["target" => $targetParser, "action" => new StringArgumentParser(["subtitle"]), "text" => new GreedyStringArgumentParser()]
		);
		$this->addOverload(
			fn(CommandSender $sender, Player $target, string $action, string $text) => $this->sendActionBar($sender, $target, $text),
			explicitParsers: ["target" => $targetParser, "action" => new StringArgumentParser(["actionbar"]), "text" => new GreedyStringArgumentParser()]
		);
		$this->addOverload(
			fn(CommandSender $sender, Player $target, string $action, int $fadeIn, int $stay, int $fadeOut) => $this->setDuration($sender, $target, $fadeIn, $stay, $fadeOut),
			explicitParsers: ["target" => $targetParser, "action" => new StringArgumentParser(["times"])]
		);
	}

	private function authorize(CommandSender $sender, Player $target) : bool{
		return $this->testPermission($sender, $target === $sender ? DefaultPermissionNames::COMMAND_TITLE_SELF : DefaultPermissionNames::COMMAND_TITLE_OTHER);
	}

	private function clear(CommandSender $sender, Player $target) : bool{
		if(!$this->authorize($sender, $target)){
			return true;
		}
		$target->removeTitles();
		$sender->sendMessage(KnownTranslationFactory::commands_title_success());
		return true;
	}

	private function reset(CommandSender $sender, Player $target) : bool{
		if(!$this->authorize($sender, $target)){
			return true;
		}
		$target->resetTitles();
		$sender->sendMessage(KnownTranslationFactory::commands_title_success());
		return true;
	}

	private function sendTitle(CommandSender $sender, Player $target, string $text) : bool{
		if(!$this->authorize($sender, $target)){
			return true;
		}
		$target->sendTitle($text);
		$sender->sendMessage(KnownTranslationFactory::commands_title_success());
		return true;
	}

	private function sendSubtitle(CommandSender $sender, Player $target, string $text) : bool{
		if(!$this->authorize($sender, $target)){
			return true;
		}
		$target->sendSubTitle($text);
		$sender->sendMessage(KnownTranslationFactory::commands_title_success());
		return true;
	}

	private function sendActionBar(CommandSender $sender, Player $target, string $text) : bool{
		if(!$this->authorize($sender, $target)){
			return true;
		}
		$target->sendActionBarMessage($text);
		$sender->sendMessage(KnownTranslationFactory::commands_title_success());
		return true;
	}

	private function setDuration(CommandSender $sender, Player $target, int $fadeIn, int $stay, int $fadeOut) : bool{
		if(!$this->authorize($sender, $target)){
			return true;
		}
		$target->setTitleDuration($fadeIn, $stay, $fadeOut);
		$sender->sendMessage(KnownTranslationFactory::commands_title_success());
		return true;
	}
}
