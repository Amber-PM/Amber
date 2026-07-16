<?php

declare(strict_types=1);

namespace pocketmine\command\overload;

use pocketmine\command\CommandSender;
use function implode;
use function in_array;

final class StringArgumentParser implements ArgumentParser{

	public function __construct(
		private ?array $allowedValues = null
	){}

	public function getConsumedTokens() : int{
		return 1;
	}

	public function getTypeHint() : string{
		return "string";
	}

	public function getAllowedValues() : ?array{
		return $this->allowedValues;
	}

	public function parse(array $tokens, CommandSender $sender, array $previousValues) : ParseResult{
		$token = $tokens[0];
		if($this->allowedValues !== null && !in_array($token, $this->allowedValues, true)){
			return ParseResult::fail("\"$token\" is not one of: " . implode(", ", $this->allowedValues));
		}

		return ParseResult::ok($token);
	}
}
