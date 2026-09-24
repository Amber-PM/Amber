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

namespace pocketmine\addon\entity;

use pocketmine\data\bedrock\BedrockDataFiles;
use pocketmine\math\Vector3;
use pocketmine\world\World;
use function file_get_contents;
use function floor;
use function is_array;
use function is_int;
use function is_string;
use function json_decode;
use function str_replace;

/**
 * Biome tags (from BedrockData's biome definitions), for has_biome_tag filters and spawn rule biome filters.
 * Each biome also carries its own name as a tag, so is_biome "plains" works the same way.
 */
final class BiomeTags{
	/** @var array<int, array<string, true>>|null */
	private static ?array $tags = null;

	private function __construct(){
		//NOOP
	}

	public static function has(int $biomeId, string $tag) : bool{
		return isset(self::all()[$biomeId][str_replace("minecraft:", "", $tag)]);
	}

	/** @return array<string, true> */
	public static function of(int $biomeId) : array{
		return self::all()[$biomeId] ?? [];
	}

	/** Whether the position is covered from the sky (a block above it). */
	public static function isUnderground(World $world, Vector3 $pos) : bool{
		$highest = $world->getHighestBlockAt((int) floor($pos->x), (int) floor($pos->z));
		return $highest !== null && $highest > (int) floor($pos->y);
	}

	/** @return array<int, array<string, true>> */
	private static function all() : array{
		if(self::$tags !== null){
			return self::$tags;
		}
		self::$tags = [];
		$ids = json_decode((string) @file_get_contents(BedrockDataFiles::BIOME_ID_MAP_JSON), true);
		$definitions = json_decode((string) @file_get_contents(BedrockDataFiles::BIOME_DEFINITIONS_JSON), true);
		if(!is_array($ids) || !is_array($definitions)){
			return self::$tags;
		}
		foreach($ids as $name => $id){
			if(!is_string($name) || !is_int($id)){
				continue;
			}
			$set = [$name => true];
			$definition = $definitions["minecraft:" . $name] ?? $definitions[$name] ?? null;
			foreach(is_array($definition) && is_array($definition["tags"] ?? null) ? $definition["tags"] : [] as $tag){
				if(is_string($tag)){
					$set[$tag] = true;
				}
			}
			self::$tags[$id] = $set;
		}
		return self::$tags;
	}
}
