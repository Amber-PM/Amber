<?php

declare(strict_types=1);

namespace pocketmine\command\overload\attribute;

use pocketmine\command\overload\ArgumentParser;
use pocketmine\command\overload\IntegerArgumentParser;

#[\Attribute(\Attribute::TARGET_PARAMETER)]
final class IntRange implements ParserAttribute{

	public function __construct(
		private ?int $min = null,
		private ?int $max = null
	){}

	public function createParser() : ArgumentParser{
		return new IntegerArgumentParser($this->min, $this->max);
	}
}
