<?php

/*
 *
 *     _             _               
 *    / \   _ __ ___ | |__   ___ _ __ 
 *   / _ \ | '_ ` _ \| '_ \ / _ \ '__|
 *  / ___ \| | | | | | |_) |  __/ |   
 * /_/   \_\_| |_| |_|_.__/ \___|_|   
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author AmberPM Team
 * @link https://github.com/Amber-PM/Amber
 *
 *
 */

declare(strict_types=1);

namespace pocketmine\world\generator\executor;

use pmmp\thread\ThreadSafe;
use pocketmine\world\generator\Generator;

final class GeneratorExecutorSetupParameters extends ThreadSafe{

	/**
	 * @phpstan-param class-string<covariant \pocketmine\world\generator\Generator> $generatorClass
	 */
	public function __construct(
		public readonly int $worldMinY,
		public readonly int $worldMaxY,
		public readonly int $generatorSeed,
		public readonly string $generatorClass,
		public readonly string $generatorSettings,
	){}

	public function createGenerator() : Generator{
		/**
		 * @var Generator $generator
		 * @see Generator::__construct()
		 */
		$generator = new $this->generatorClass($this->generatorSeed, $this->generatorSettings);
		return $generator;
	}
}
