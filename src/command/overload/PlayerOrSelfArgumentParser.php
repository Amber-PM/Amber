<?php

declare(strict_types=1);

namespace pocketmine\command\overload;

use pocketmine\command\CommandSender;
use pocketmine\lang\KnownTranslationFactory;
use pocketmine\player\Player;

/**
 * Resolves a target player argument, additionally accepting the "@s" selector to mean "the sender itself"
 * (sender must be a Player in that case). Used for commands where the target is always an explicit token
 * (never implicit), e.g. /enchant and /effect.
 */
final class PlayerOrSelfArgumentParser implements ArgumentParser{

	public function getConsumedTokens() : int{
		return 1;
	}

	public function getTypeHint() : string{
		return "player";
	}

	public function parse(array $tokens, CommandSender $sender, array $previousValues) : ParseResult{
		$token = $tokens[0];
		if($token === "@s"){
			if(!($sender instanceof Player)){
				return ParseResult::fail(KnownTranslationFactory::pocketmine_command_error_playerUserOnly());
			}

			return ParseResult::ok($sender);
		}

		$player = $sender->getServer()->getPlayerByPrefix($token);
		if($player === null){
			return ParseResult::fail(KnownTranslationFactory::commands_generic_player_notFound());
		}

		return ParseResult::ok($player);
	}
}
