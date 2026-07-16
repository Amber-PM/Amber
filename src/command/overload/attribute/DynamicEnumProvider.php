<?php

declare(strict_types=1);

namespace pocketmine\command\overload\attribute;

interface DynamicEnumProvider{

	/**
	 * @return string[]
	 */
	public static function getEnumValues() : array;
}
