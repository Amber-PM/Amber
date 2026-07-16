<?php

declare(strict_types=1);

namespace pocketmine\command\overload;

use pocketmine\command\CommandSender;
use pocketmine\lang\KnownTranslationFactory;

final class PlayerArgumentParser implements ArgumentParser{

	public function getConsumedTokens() : int{
		return 1;
	}

	public function getTypeHint() : string{
		return "player";
	}

	public function parse(array $tokens, CommandSender $sender, array $previousValues) : ParseResult{
		$player = $sender->getServer()->getPlayerByPrefix($tokens[0]);
		if($player === null){
			return ParseResult::fail(KnownTranslationFactory::commands_generic_player_notFound());
		}

		return ParseResult::ok($player);
	}
}
