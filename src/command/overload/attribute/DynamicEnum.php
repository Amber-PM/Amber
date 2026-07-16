<?php

declare(strict_types=1);

namespace pocketmine\command\overload\attribute;

use pocketmine\command\overload\ArgumentParser;
use pocketmine\command\overload\DynamicEnumArgumentParser;
use RuntimeException;
use function is_a;

#[\Attribute(\Attribute::TARGET_PARAMETER)]
final class DynamicEnum implements ParserAttribute{

	/**
	 * @param class-string<DynamicEnumProvider> $provider
	 */
	public function __construct(
		private string $provider
	){
		if(!is_a($provider, DynamicEnumProvider::class, true)){
			throw new RuntimeException("$provider must implement " . DynamicEnumProvider::class);
		}
	}

	public function createParser() : ArgumentParser{
		return new DynamicEnumArgumentParser($this->provider);
	}
}
