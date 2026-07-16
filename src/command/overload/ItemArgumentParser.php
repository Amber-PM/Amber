<?php

declare(strict_types=1);

namespace pocketmine\command\overload;

use pocketmine\command\CommandSender;
use pocketmine\item\LegacyStringToItemParser;
use pocketmine\item\LegacyStringToItemParserException;
use pocketmine\item\StringToItemParser;
use pocketmine\lang\KnownTranslationFactory;

final class ItemArgumentParser implements ArgumentParser{

	public function getConsumedTokens() : int{
		return 1;
	}

	public function getTypeHint() : string{
		return "item";
	}

	public function parse(array $tokens, CommandSender $sender, array $previousValues) : ParseResult{
		$token = $tokens[0];
		try{
			$item = StringToItemParser::getInstance()->parse($token) ?? LegacyStringToItemParser::getInstance()->parse($token);
		}catch(LegacyStringToItemParserException){
			$item = null;
		}

		if($item === null){
			return ParseResult::fail(KnownTranslationFactory::commands_give_item_notFound($token));
		}

		return ParseResult::ok($item);
	}
}
