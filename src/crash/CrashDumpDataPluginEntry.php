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

final class CrashDumpDataPluginEntry{
	/**
	 * @param string[] $authors
	 * @param string[] $api
	 * @param string[] $depends
	 * @param string[] $softDepends
	 */
	public function __construct(
		public string $name,
		public string $version,
		public array $authors,
		public array $api,
		public bool $enabled,
		public array $depends,
		public array $softDepends,
		public string $main,
		public string $load,
		public string $website,
	){}
}
