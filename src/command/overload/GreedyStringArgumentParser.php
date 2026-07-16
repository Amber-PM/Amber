<?php

declare(strict_types=1);

namespace pocketmine\command\overload;

use pocketmine\command\CommandSender;
use function implode;

final class GreedyStringArgumentParser implements ArgumentParser{

	public function getConsumedTokens() : int{
		return 1;
	}

	public function getTypeHint() : string{
		return "text";
	}

	public function parse(array $tokens, CommandSender $sender, array $previousValues) : ParseResult{
		return ParseResult::ok(implode(" ", $tokens));
	}
}
