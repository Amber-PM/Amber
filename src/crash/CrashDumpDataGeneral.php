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

namespace pocketmine\crash;

final class CrashDumpDataGeneral{

	/**
	 * @param string[] $composer_libraries
	 * @phpstan-param array<string, string> $composer_libraries
	 */
	public function __construct(
		public string $name,
		public string $base_version,
		public int $build,
		public bool $is_dev,
		public int $protocol,
		public string $git,
		public string $uname,
		public string $php,
		public string $zend,
		public string $php_os,
		public string $os,
		public array $composer_libraries,
	){}
}
