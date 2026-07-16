<?php

declare(strict_types=1);

namespace pocketmine\command\overload;

use pocketmine\command\CommandSender;

interface ArgumentParser{

	public function getConsumedTokens() : int;

	public function getTypeHint() : string;

	public function parse(array $tokens, CommandSender $sender, array $previousValues) : ParseResult;
}
