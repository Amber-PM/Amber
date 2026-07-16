<?php

declare(strict_types=1);

namespace pocketmine\command\overload\attribute;

use pocketmine\command\overload\ArgumentParser;

interface ParserAttribute{

	public function createParser() : ArgumentParser;
}
