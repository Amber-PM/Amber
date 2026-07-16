<?php

declare(strict_types=1);

namespace pocketmine\command\overload;

use pocketmine\command\CommandSender;
use pocketmine\command\overload\attribute\DynamicEnumProvider;
use function implode;
use function in_array;

final class DynamicEnumArgumentParser implements ArgumentParser{

	/**
	 * @param class-string<DynamicEnumProvider> $provider
	 */
	public function __construct(
		private string $provider
	){}

	public function getConsumedTokens() : int{
		return 1;
	}

	public function getTypeHint() : string{
		return "string";
	}

	/**
	 * @return string[]
	 */
	public function getValues() : array{
		return ($this->provider)::getEnumValues();
	}

	public function parse(array $tokens, CommandSender $sender, array $previousValues) : ParseResult{
		$token = $tokens[0];
		$values = $this->getValues();
		if(!in_array($token, $values, true)){
			return ParseResult::fail("\"$token\" is not one of: " . implode(", ", $values));
		}

		return ParseResult::ok($token);
	}
}
