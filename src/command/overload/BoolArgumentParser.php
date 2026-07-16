<?php

declare(strict_types=1);

namespace pocketmine\command\overload;

use pocketmine\command\CommandSender;
use function strtolower;

final class BoolArgumentParser implements ArgumentParser{

	public function getConsumedTokens() : int{
		return 1;
	}

	public function getTypeHint() : string{
		return "bool";
	}

	public function parse(array $tokens, CommandSender $sender, array $previousValues) : ParseResult{
		$token = strtolower($tokens[0]);
		return match($token){
			"true", "1", "yes", "on" => ParseResult::ok(true),
			"false", "0", "no", "off" => ParseResult::ok(false),
			default => ParseResult::fail("\"$token\" is not a valid boolean"),
		};
	}
}
