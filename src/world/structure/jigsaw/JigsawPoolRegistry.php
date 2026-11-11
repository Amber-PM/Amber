<?php

/*
 *
 *     _             _               
 *    / \\   _ __ ___ | |__   ___ _ __ 
 *   / _ \\ | '_ ` _ \\| '_ \\ / _ \\ '__|
 *  / ___ \\| | | | | | |_) |  __/ |   
 * /_/   \\_\\_| |_| |_|_.__/ \\___|_|   
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

namespace pocketmine\world\structure\jigsaw;

use pocketmine\utils\SingletonTrait;

final class JigsawPoolRegistry{
	use SingletonTrait;

	/** @var array<string, JigsawPool> */
	private array $pools = [];

	public function __construct(){
		// Register fallback empty pool
		$this->registerPool(new JigsawPool("minecraft:empty"));
	}

	public function registerPool(JigsawPool $pool) : void{
		$this->pools[$pool->getId()] = $pool;
	}

	public function getPool(string $id) : ?JigsawPool{
		return $this->pools[$id] ?? null;
	}

	public function hasPool(string $id) : bool{
		return isset($this->pools[$id]);
	}

	public function clear() : void{
		$this->pools = [
			"minecraft:empty" => new JigsawPool("minecraft:empty")
		];
	}
}
