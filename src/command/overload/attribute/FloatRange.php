<?php

declare(strict_types=1);

namespace pocketmine\command\overload\attribute;

use pocketmine\command\overload\ArgumentParser;
use pocketmine\command\overload\FloatArgumentParser;

#[\Attribute(\Attribute::TARGET_PARAMETER)]
final class FloatRange implements ParserAttribute{

	public function __construct(
		private ?float $min = null,
		private ?float $max = null
	){}

	public function createParser() : ArgumentParser{
		return new FloatArgumentParser($this->min, $this->max);
	}
}
