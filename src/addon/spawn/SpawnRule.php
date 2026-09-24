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

namespace pocketmine\addon\spawn;

use pocketmine\addon\AddonMath;
use pocketmine\addon\AddonException;
use pocketmine\addon\entity\BiomeTags;
use pocketmine\addon\entity\EntityFilter;
use pocketmine\block\Block;
use pocketmine\world\World;
use function array_is_list;
use function count;
use function explode;
use function is_array;
use function is_numeric;
use function is_string;
use function max;
use function mt_rand;
use function rtrim;
use function str_contains;
use function str_replace;
use function strtolower;

/**
 * One entity's spawn rules (spawn_rules/*.json): where and when the natural spawner may place it.
 * Each "conditions" entry is one way the entity can spawn; a spot has to satisfy every filter of one entry.
 */
final class SpawnRule{
	public const SURFACE = 0;
	public const UNDERGROUND = 1;
	public const UNDERWATER = 2;
	public const LAVA = 3;

	/**
	 * @param list<mixed[]> $conditions
	 */
	private function __construct(
		private string $identifier,
		private string $category,
		private array $conditions
	){}

	/**
	 * @param mixed[] $json
	 * @throws AddonException
	 */
	public static function fromJson(array $json, string $source) : self{
		$rules = $json["minecraft:spawn_rules"] ?? null;
		if(!is_array($rules)){
			throw new AddonException("$source: not a minecraft:spawn_rules file");
		}
		$description = is_array($rules["description"] ?? null) ? $rules["description"] : [];
		$identifier = $description["identifier"] ?? null;
		if(!is_string($identifier)){
			throw new AddonException("$source: missing identifier");
		}
		$conditions = [];
		foreach(is_array($rules["conditions"] ?? null) ? $rules["conditions"] : [] as $condition){
			if(is_array($condition)){
				$conditions[] = $condition;
			}
		}
		return new self($identifier, strtolower((string) ($description["population_control"] ?? "animal")), $conditions);
	}

	public function getIdentifier() : string{ return $this->identifier; }

	/** Population control category: animal, monster, water_animal, ambient... */
	public function getCategory() : string{ return $this->category; }

	/** @return list<mixed[]> */
	public function getConditions() : array{ return $this->conditions; }

	/** Which kinds of spot (SURFACE, ...) any condition accepts. @return array<int, true> */
	public function getSpotKinds() : array{
		$kinds = [];
		foreach($this->conditions as $condition){
			$kinds[self::spotKind($condition)] = true;
		}
		return $kinds;
	}

	/** @param mixed[] $condition */
	public static function spotKind(array $condition) : int{
		return match(true){
			isset($condition["minecraft:spawns_underwater"]) => self::UNDERWATER,
			isset($condition["minecraft:spawns_lava"]) => self::LAVA,
			isset($condition["minecraft:spawns_underground"]) && !isset($condition["minecraft:spawns_on_surface"]) => self::UNDERGROUND,
			default => self::SURFACE,
		};
	}

	/**
	 * The first condition entry the spot satisfies, or null.
	 *
	 * @param int $kind  the kind of spot (SURFACE...) the spawner found
	 * @param int $distance distance to the nearest player, in blocks
	 * @return mixed[]|null
	 */
	public function match(World $world, int $x, int $y, int $z, int $kind, float $distance) : ?array{
		foreach($this->conditions as $condition){
			$accepted = isset($condition["minecraft:spawns_on_surface"]) && $kind === self::SURFACE
				|| isset($condition["minecraft:spawns_underground"]) && $kind === self::UNDERGROUND
				|| isset($condition["minecraft:spawns_underwater"]) && $kind === self::UNDERWATER
				|| isset($condition["minecraft:spawns_lava"]) && $kind === self::LAVA;
			if(!$accepted || !$this->conditionHolds($condition, $world, $x, $y, $z, $distance)){
				continue;
			}
			return $condition;
		}
		return null;
	}

	/** @param mixed[] $c */
	private function conditionHolds(array $c, World $world, int $x, int $y, int $z, float $distance) : bool{
		$height = $c["minecraft:height_filter"] ?? null;
		if(is_array($height) && ($y < (int) ($height["min"] ?? -64) || $y > (int) ($height["max"] ?? 320))){
			return false;
		}
		$brightness = $c["minecraft:brightness_filter"] ?? null;
		if(is_array($brightness)){
			$light = $world->getFullLightAt($x, $y, $z);
			if($light < (int) ($brightness["min"] ?? 0) || $light > (int) ($brightness["max"] ?? 15)){
				return false;
			}
		}
		$difficulty = $c["minecraft:difficulty_filter"] ?? null;
		if(is_array($difficulty)){
			$current = $world->getDifficulty();
			if($current < self::difficulty($difficulty["min"] ?? "peaceful") || $current > self::difficulty($difficulty["max"] ?? "hard")){
				return false;
			}
		}
		$distanceFilter = $c["minecraft:distance_filter"] ?? null;
		if(is_array($distanceFilter) && ($distance < (float) ($distanceFilter["min"] ?? 0) || $distance > (float) ($distanceFilter["max"] ?? 1000))){
			return false;
		}
		$below = $world->getBlockAt($x, $y - 1, $z);
		if(isset($c["minecraft:spawns_on_block_filter"]) && !self::blockMatches($below, $c["minecraft:spawns_on_block_filter"])){
			return false;
		}
		if(isset($c["minecraft:spawns_on_block_prevented_filter"]) && self::blockMatches($below, $c["minecraft:spawns_on_block_prevented_filter"])){
			return false;
		}
		if(isset($c["minecraft:biome_filter"]) && !self::biomeFilter($c["minecraft:biome_filter"], $world->getBiomeId($x, $y, $z))){
			return false;
		}
		$age = $c["minecraft:world_age_filter"] ?? null;
		if(is_array($age) && $world->getTime() < (int) ((float) ($age["min"] ?? 0) * 20)){
			return false;
		}
		return true;
	}

	/** The spawn weight of a condition entry. @param mixed[] $condition */
	public static function weight(array $condition) : int{
		$weight = $condition["minecraft:weight"] ?? null;
		return is_array($weight) && is_numeric($weight["default"] ?? null) ? max(0, (int) $weight["default"]) : 100;
	}

	/**
	 * How many to spawn together, and the event the herd's members get.
	 *
	 * @param mixed[] $condition
	 * @return array{int, ?string, int}
	 */
	public static function herd(array $condition) : array{
		$herd = $condition["minecraft:herd"] ?? null;
		if(is_array($herd) && array_is_list($herd)){
			$herd = $herd[mt_rand(0, max(0, count($herd) - 1))] ?? null;
		}
		if(!is_array($herd)){
			return [1, null, 0];
		}
		$min = max(1, (int) ($herd["min_size"] ?? 1));
		$max = max($min, (int) ($herd["max_size"] ?? $min));
		return [mt_rand($min, $max), is_string($herd["event"] ?? null) ? $herd["event"] : null, (int) ($herd["event_skip_count"] ?? 0)];
	}

	/**
	 * The identifier to spawn (permute_type can swap it for another entity, with an event).
	 *
	 * @param mixed[] $condition
	 * @return array{string, ?string}
	 */
	public function permute(array $condition) : array{
		$permute = $condition["minecraft:permute_type"] ?? null;
		if(is_array($permute) && $permute !== []){
			$total = 0;
			foreach($permute as $entry){
				$total += max(0, (int) ($entry["weight"] ?? 0));
			}
			$roll = AddonMath::randomFloat() * max(1, $total);
			foreach($permute as $entry){
				$roll -= max(0, (int) ($entry["weight"] ?? 0));
				if($roll <= 0){
					$type = is_string($entry["entity_type"] ?? null) ? $entry["entity_type"] : $this->identifier;
					if(str_contains($type, "<")){
						[$type, $event] = explode("<", rtrim($type, ">"), 2);
						return [$type, $event];
					}
					return [$type, null];
				}
			}
		}
		$event = $condition["minecraft:spawn_event"] ?? null;
		return [$this->identifier, is_array($event) && is_string($event["event"] ?? null) ? $event["event"] : null];
	}

	/** Per-entity density limit for the kind of spot, or null for none. @param mixed[] $condition */
	public static function densityLimit(array $condition, int $kind) : ?int{
		$limit = $condition["minecraft:density_limit"] ?? null;
		if(!is_array($limit)){
			return null;
		}
		$value = $kind === self::SURFACE ? ($limit["surface"] ?? null) : ($limit["underground"] ?? null);
		return is_numeric($value) ? (int) $value : null;
	}

	private static function difficulty(mixed $name) : int{
		return match(strtolower((string) $name)){
			"peaceful" => World::DIFFICULTY_PEACEFUL,
			"easy" => World::DIFFICULTY_EASY,
			"normal" => World::DIFFICULTY_NORMAL,
			default => World::DIFFICULTY_HARD,
		};
	}

	private static function blockMatches(Block $block, mixed $filter) : bool{
		$name = strtolower(str_replace(" ", "_", $block->getName()));
		foreach(is_array($filter) ? $filter : [$filter] as $wanted){
			$wanted = is_array($wanted) ? ($wanted["name"] ?? null) : $wanted;
			if(!is_string($wanted)){
				continue;
			}
			$wanted = strtolower(str_replace("minecraft:", "", $wanted));
			if($wanted === $name || ($wanted === "grass" && $name === "grass_block") || ($wanted === "grass_block" && $name === "grass")){
				return true;
			}
		}
		return false;
	}

	/**
	 * Biome filters use the entity filter syntax with has_biome_tag / is_biome tests.
	 */
	public static function biomeFilter(mixed $filter, int $biomeId) : bool{
		if(!is_array($filter)){
			return true;
		}
		if(array_is_list($filter)){
			foreach($filter as $child){
				if(!self::biomeFilter($child, $biomeId)){
					return false;
				}
			}
			return true;
		}
		foreach(["all_of", "any_of", "none_of"] as $group){
			if(!isset($filter[$group]) || !is_array($filter[$group])){
				continue;
			}
			$children = array_is_list($filter[$group]) ? $filter[$group] : [$filter[$group]];
			$hits = 0;
			foreach($children as $child){
				if(self::biomeFilter($child, $biomeId)){
					$hits++;
				}
			}
			$ok = match($group){
				"all_of" => $hits === count($children),
				"any_of" => $hits > 0,
				default => $hits === 0,
			};
			if(!$ok){
				return false;
			}
		}
		$test = $filter["test"] ?? null;
		if(!is_string($test)){
			return true;
		}
		$has = BiomeTags::has($biomeId, strtolower((string) ($filter["value"] ?? "")));
		return EntityFilter::compare($has, is_string($filter["operator"] ?? null) ? $filter["operator"] : "==", true);
	}
}
