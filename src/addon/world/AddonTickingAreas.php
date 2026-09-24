<?php

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketMine Team
 * @link http://www.pocketmine.net/
 *
 *
 */

declare(strict_types=1);

namespace pocketmine\addon\world;

use pocketmine\Server;
use pocketmine\utils\Filesystem;
use pocketmine\world\ChunkLoader;
use pocketmine\world\ChunkTicker;
use pocketmine\world\World;
use function array_keys;
use function count;
use function file_get_contents;
use function is_array;
use function is_file;
use function json_decode;
use function json_encode;
use function max;
use function min;

/**
 * Ticking areas (/tickingarea): named regions whose chunks stay loaded and ticking with no player nearby,
 * so add-on machines, farms and mobs keep running. Saved across restarts. Like the game, at most 10 areas
 * of up to 100 chunks each.
 */
final class AddonTickingAreas implements ChunkLoader{
	public const MAX_AREAS = 10;
	public const MAX_CHUNKS = 100;

	/** @var array<string, array{world: string, x1: int, z1: int, x2: int, z2: int}> name => chunk bounds */
	private array $areas = [];
	private ChunkTicker $ticker;

	public function __construct(private Server $server, private string $file){
		$this->ticker = new ChunkTicker();
		$data = is_file($file) ? json_decode((string) @file_get_contents($file), true) : null;
		foreach(is_array($data) ? $data : [] as $name => $area){
			if(is_array($area) && isset($area["world"], $area["x1"], $area["z1"], $area["x2"], $area["z2"])){
				$this->areas[(string) $name] = ["world" => (string) $area["world"], "x1" => (int) $area["x1"], "z1" => (int) $area["z1"], "x2" => (int) $area["x2"], "z2" => (int) $area["z2"]];
			}
		}
	}

	/** Loads the saved areas of a world (when the world loads). */
	public function applyToWorld(World $world) : void{
		foreach($this->areas as $area){
			if($area["world"] === $world->getFolderName()){
				$this->hold($world, $area, true);
			}
		}
	}

	/** @return string|null an error, or null when added */
	public function add(World $world, string $name, int $minX, int $minZ, int $maxX, int $maxZ) : ?string{
		if(isset($this->areas[$name])){
			return "a ticking area named $name already exists";
		}
		if(count($this->areas) >= self::MAX_AREAS){
			return "at most " . self::MAX_AREAS . " ticking areas can exist";
		}
		$area = ["world" => $world->getFolderName(), "x1" => min($minX, $maxX) >> 4, "z1" => min($minZ, $maxZ) >> 4, "x2" => max($minX, $maxX) >> 4, "z2" => max($minZ, $maxZ) >> 4];
		if(($area["x2"] - $area["x1"] + 1) * ($area["z2"] - $area["z1"] + 1) > self::MAX_CHUNKS){
			return "a ticking area may cover at most " . self::MAX_CHUNKS . " chunks";
		}
		$this->areas[$name] = $area;
		$this->hold($world, $area, true);
		$this->save();
		return null;
	}

	public function remove(string $name) : bool{
		$area = $this->areas[$name] ?? null;
		if($area === null){
			return false;
		}
		unset($this->areas[$name]);
		$world = $this->server->getWorldManager()->getWorldByName($area["world"]);
		if($world !== null){
			$this->hold($world, $area, false);
		}
		$this->save();
		return true;
	}

	public function removeAll() : int{
		$count = count($this->areas);
		foreach(array_keys($this->areas) as $name){
			$this->remove($name);
		}
		return $count;
	}

	/** @return array<string, array{world: string, x1: int, z1: int, x2: int, z2: int}> */
	public function getAreas() : array{ return $this->areas; }

	/** @param array{world: string, x1: int, z1: int, x2: int, z2: int} $area */
	private function hold(World $world, array $area, bool $on) : void{
		for($x = $area["x1"]; $x <= $area["x2"]; ++$x){
			for($z = $area["z1"]; $z <= $area["z2"]; ++$z){
				if($on){
					$world->registerChunkLoader($this, $x, $z, true);
					$world->registerTickingChunk($this->ticker, $x, $z);
				}else{
					$world->unregisterTickingChunk($this->ticker, $x, $z);
					$world->unregisterChunkLoader($this, $x, $z);
				}
			}
		}
	}

	private function save() : void{
		Filesystem::safeFilePutContents($this->file, (string) json_encode($this->areas));
	}
}
