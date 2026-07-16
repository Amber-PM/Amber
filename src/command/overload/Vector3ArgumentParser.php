<?php

declare(strict_types=1);

namespace pocketmine\command\overload;

use pocketmine\command\CommandSender;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use function is_numeric;
use function str_starts_with;
use function substr;

final class Vector3ArgumentParser implements ArgumentParser{

	public function __construct(
		private ?int $baseParamIndex = null,
		private float $min = -30000000,
		private float $max = 30000000
	){}

	public function getConsumedTokens() : int{
		return 3;
	}

	public function getTypeHint() : string{
		return "x y z";
	}

	public function parse(array $tokens, CommandSender $sender, array $previousValues) : ParseResult{
		$base = $this->resolveBase($sender, $previousValues);
		$axes = [];

		foreach($tokens as $i => $token){
			$relative = str_starts_with($token, "~");
			$raw = $relative ? substr($token, 1) : $token;
			if($raw !== "" && !is_numeric($raw)){
				return ParseResult::fail("\"$token\" is not a valid coordinate");
			}
			if($relative && $base === null){
				return ParseResult::fail("Relative coordinates are not available in this context");
			}

			$value = $raw === "" ? 0.0 : (float) $raw;
			if($relative){
				$value += match($i){
					0 => $base->x,
					1 => $base->y,
					2 => $base->z,
				};
			}
			if($value < $this->min || $value > $this->max){
				return ParseResult::fail("Coordinate \"$token\" is out of range");
			}

			$axes[] = $value;
		}

		return ParseResult::ok(new Vector3($axes[0], $axes[1], $axes[2]));
	}

	private function resolveBase(CommandSender $sender, array $previousValues) : ?Vector3{
		if($this->baseParamIndex !== null){
			$subject = $previousValues[$this->baseParamIndex] ?? null;
			return $subject instanceof Player ? $subject->getPosition()->asVector3() : null;
		}

		return $sender instanceof Player ? $sender->getPosition()->asVector3() : null;
	}
}
