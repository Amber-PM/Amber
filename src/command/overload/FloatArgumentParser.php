<?php

declare(strict_types=1);

namespace pocketmine\command\overload;

use pocketmine\command\CommandSender;
use pocketmine\lang\KnownTranslationFactory;
use function is_numeric;

final class FloatArgumentParser implements ArgumentParser{

	public function __construct(
		private ?float $min = null,
		private ?float $max = null
	){}

	public function getConsumedTokens() : int{
		return 1;
	}

	public function getTypeHint() : string{
		return "float";
	}

	public function parse(array $tokens, CommandSender $sender, array $previousValues) : ParseResult{
		$token = $tokens[0];
		if(!is_numeric($token)){
			return ParseResult::fail("\"$token\" is not a valid number");
		}

		$value = (float) $token;
		if($this->min !== null && $value < $this->min){
			return ParseResult::fail(KnownTranslationFactory::commands_generic_num_tooSmall($token, (string) $this->min));
		}
		if($this->max !== null && $value > $this->max){
			return ParseResult::fail(KnownTranslationFactory::commands_generic_num_tooBig($token, (string) $this->max));
		}

		return ParseResult::ok($value);
	}
}
