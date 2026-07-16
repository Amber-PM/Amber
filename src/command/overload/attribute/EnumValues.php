<?php

declare(strict_types=1);

namespace pocketmine\command\overload\attribute;

use pocketmine\command\overload\ArgumentParser;
use pocketmine\command\overload\StringArgumentParser;

#[\Attribute(\Attribute::TARGET_PARAMETER)]
final class EnumValues implements ParserAttribute{

	private array $values;

	public function __construct(string ...$values){
		$this->values = $values;
	}

	public function createParser() : ArgumentParser{
		return new StringArgumentParser($this->values);
	}
}
