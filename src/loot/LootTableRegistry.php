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

namespace pocketmine\loot;

use pocketmine\utils\SingletonTrait;
use function file_exists;
use function file_get_contents;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_numeric;
use function is_string;
use function json_decode;
use function str_ends_with;
use function str_starts_with;
use function substr;
use const JSON_THROW_ON_ERROR;

final class LootTableRegistry{
	use SingletonTrait;

	/** @var array<string, LootTable> */
	private array $tables = [];

	public function register(string $id, LootTable $table) : void{
		$this->tables[$this->normalizeId($id)] = $table;
	}

	public function has(string $id) : bool{
		$normalized = $this->normalizeId($id);
		return isset($this->tables[$normalized]) || $this->findTableFile($normalized) !== null;
	}

	public function get(string $id) : ?LootTable{
		$normalized = $this->normalizeId($id);
		if(isset($this->tables[$normalized])){
			return $this->tables[$normalized];
		}

		$path = $this->findTableFile($normalized);
		if($path !== null){
			$raw = (string) file_get_contents($path);
			try{
				$data = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
				if(is_array($data)){
					/** @var array<string, mixed> $typedData */
					$typedData = $data;
					$table = $this->parseTable($normalized, $typedData);
					$this->tables[$normalized] = $table;
					return $table;
				}
			}catch(\JsonException){
				return null;
			}
		}

		return null;
	}

	private function normalizeId(string $id) : string{
		if(str_starts_with($id, "loot_tables/")){
			$id = substr($id, 12);
		}
		if(str_ends_with($id, ".json")){
			$id = substr($id, 0, -5);
		}
		return $id;
	}

	private function findTableFile(string $normalizedId) : ?string{
		$candidates = [
			\pocketmine\RESOURCE_PATH . "vanilla/loot_tables/" . $normalizedId . ".json",
			\pocketmine\RESOURCE_PATH . "loot_tables/" . $normalizedId . ".json"
		];

		foreach($candidates as $path){
			if(file_exists($path)){
				return $path;
			}
		}
		return null;
	}

	private static function asInt(mixed $val, int $default) : int{
		if(is_int($val)){
			return $val;
		}
		if(is_numeric($val)){
			return (int) $val;
		}
		return $default;
	}

	private static function asFloat(mixed $val, float $default) : float{
		if(is_float($val) || is_int($val)){
			return (float) $val;
		}
		if(is_numeric($val)){
			return (float) $val;
		}
		return $default;
	}

	private static function asString(mixed $val, string $default) : string{
		return is_string($val) ? $val : $default;
	}

	private static function asBool(mixed $val, bool $default) : bool{
		return is_bool($val) ? $val : $default;
	}

	/**
	 * @param array<string, mixed> $data
	 */
	public function parseTable(string $id, array $data) : LootTable{
		$pools = [];
		$poolsRaw = $data["pools"] ?? null;
		if(is_array($poolsRaw)){
			foreach($poolsRaw as $poolData){
				if(is_array($poolData)){
					/** @var array<string, mixed> $typedPool */
					$typedPool = $poolData;
					$pools[] = $this->parsePool($typedPool);
				}
			}
		}
		return new LootTable($id, $pools);
	}

	/**
	 * @param array<string, mixed> $poolData
	 */
	private function parsePool(array $poolData) : LootPool{
		$minRolls = 1;
		$maxRolls = 1;
		$rollsRaw = $poolData["rolls"] ?? null;
		if(is_numeric($rollsRaw)){
			$minRolls = $maxRolls = self::asInt($rollsRaw, 1);
		}elseif(is_array($rollsRaw)){
			$minRolls = self::asInt($rollsRaw["min"] ?? 1, 1);
			$maxRolls = self::asInt($rollsRaw["max"] ?? $minRolls, $minRolls);
		}

		$minBonus = 0.0;
		$maxBonus = 0.0;
		$bonusRaw = $poolData["bonus_rolls"] ?? null;
		if(is_numeric($bonusRaw)){
			$minBonus = $maxBonus = self::asFloat($bonusRaw, 0.0);
		}elseif(is_array($bonusRaw)){
			$minBonus = self::asFloat($bonusRaw["min"] ?? 0.0, 0.0);
			$maxBonus = self::asFloat($bonusRaw["max"] ?? $minBonus, $minBonus);
		}

		$conditions = [];
		$condsRaw = $poolData["conditions"] ?? null;
		if(is_array($condsRaw)){
			foreach($condsRaw as $condData){
				if(is_array($condData)){
					/** @var array<string, mixed> $typedCond */
					$typedCond = $condData;
					$conditions[] = $this->parseCondition($typedCond);
				}
			}
		}

		$entries = [];
		$entriesRaw = $poolData["entries"] ?? null;
		if(is_array($entriesRaw)){
			foreach($entriesRaw as $entryData){
				if(is_array($entryData)){
					/** @var array<string, mixed> $typedEntry */
					$typedEntry = $entryData;
					$entries[] = $this->parseEntry($typedEntry);
				}
			}
		}

		return new LootPool($minRolls, $maxRolls, $minBonus, $maxBonus, $conditions, $entries);
	}

	/**
	 * @param array<string, mixed> $entryData
	 */
	private function parseEntry(array $entryData) : LootEntry{
		$type = self::asString($entryData["type"] ?? "item", "item");
		$name = self::asString($entryData["name"] ?? "", "");
		$weight = self::asInt($entryData["weight"] ?? 1, 1);
		$quality = self::asFloat($entryData["quality"] ?? 0.0, 0.0);

		$conditions = [];
		$condsRaw = $entryData["conditions"] ?? null;
		if(is_array($condsRaw)){
			foreach($condsRaw as $condData){
				if(is_array($condData)){
					/** @var array<string, mixed> $typedCond */
					$typedCond = $condData;
					$conditions[] = $this->parseCondition($typedCond);
				}
			}
		}

		$functions = [];
		$funcsRaw = $entryData["functions"] ?? null;
		if(is_array($funcsRaw)){
			foreach($funcsRaw as $funcData){
				if(is_array($funcData)){
					/** @var array<string, mixed> $typedFunc */
					$typedFunc = $funcData;
					$func = $this->parseFunction($typedFunc);
					if($func !== null){
						$functions[] = $func;
					}
				}
			}
		}

		return new LootEntry($type, $name, $weight, $quality, $conditions, $functions);
	}

	/**
	 * @param array<string, mixed> $condData
	 */
	private function parseCondition(array $condData) : LootCondition{
		$type = self::asString($condData["condition"] ?? "", "");
		if($type === "killed_by_player"){
			return new KilledByPlayerCondition(allowPets: false);
		}
		if($type === "killed_by_player_or_pets"){
			return new KilledByPlayerCondition(allowPets: true);
		}
		if($type === "random_chance"){
			return new RandomChanceCondition(self::asFloat($condData["chance"] ?? 1.0, 1.0));
		}
		if($type === "random_chance_with_looting"){
			return new RandomChanceWithLootingCondition(
				self::asFloat($condData["chance"] ?? 0.0, 0.0),
				self::asFloat($condData["looting_multiplier"] ?? 0.0, 0.0)
			);
		}
		if($type === "is_baby"){
			return new class implements LootCondition{
				public function test(LootContext $context) : bool{
					$victim = $context->victim;
					return $victim instanceof \pocketmine\entity\Ageable && $victim->isBaby();
				}
			};
		}
		if($type === "inverted"){
			$termRaw = $condData["term"] ?? null;
			if(is_array($termRaw)){
				/** @var array<string, mixed> $typedTerm */
				$typedTerm = $termRaw;
				$term = $this->parseCondition($typedTerm);
				return new InvertedCondition($term);
			}
		}
		return new AlwaysFalseCondition();
	}

	/**
	 * @param array<string, mixed> $funcData
	 */
	private function parseFunction(array $funcData) : ?LootFunction{
		$type = preg_replace('/^minecraft:/', '', self::asString($funcData["function"] ?? "", ""));
		$func = match($type){
			"set_count" => $this->parseSetCount($funcData),
			"set_damage" => $this->parseSetDamage($funcData),
			"set_data" => $this->parseSetData($funcData),
			"set_name" => new SetNameFunction(self::asString($funcData["name"] ?? "", "")),
			"set_lore" => $this->parseSetLore($funcData),
			"furnace_smelt" => new FurnaceSmeltFunction(),
			"specific_enchants" => $this->parseSpecificEnchants($funcData),
			"looting_enchant" => $this->parseLootingEnchant($funcData),
			"enchant_randomly" => new EnchantRandomlyFunction(self::asBool($funcData["treasure"] ?? true, true)),
			"enchant_with_levels" => $this->parseEnchantWithLevels($funcData),
			default => null
		};

		if($func === null){
			return null;
		}

		$conditions = [];
		$condsRaw = $funcData["conditions"] ?? null;
		if(is_array($condsRaw)){
			foreach($condsRaw as $condData){
				if(is_array($condData)){
					/** @var array<string, mixed> $typedCond */
					$typedCond = $condData;
					$conditions[] = $this->parseCondition($typedCond);
				}
			}
		}

		if(count($conditions) > 0){
			return new ConditionalFunction($func, $conditions);
		}

		return $func;
	}

	/**
	 * @param array<string, mixed> $funcData
	 */
	private function parseSetCount(array $funcData) : SetCountFunction{
		$min = 1;
		$max = 1;
		$countRaw = $funcData["count"] ?? null;
		if(is_numeric($countRaw)){
			$min = $max = self::asInt($countRaw, 1);
		}elseif(is_array($countRaw)){
			$min = self::asInt($countRaw["min"] ?? 1, 1);
			$max = self::asInt($countRaw["max"] ?? $min, $min);
		}
		$add = self::asBool($funcData["add"] ?? false, false);
		return new SetCountFunction($min, $max, $add);
	}

	/**
	 * @param array<string, mixed> $funcData
	 */
	private function parseSetDamage(array $funcData) : SetDamageFunction{
		$min = 0.0;
		$max = 0.0;
		$dmgRaw = $funcData["damage"] ?? null;
		if(is_numeric($dmgRaw)){
			$min = $max = self::asFloat($dmgRaw, 0.0);
		}elseif(is_array($dmgRaw)){
			$min = self::asFloat($dmgRaw["min"] ?? 0.0, 0.0);
			$max = self::asFloat($dmgRaw["max"] ?? $min, $min);
		}
		return new SetDamageFunction($min, $max);
	}

	/**
	 * @param array<string, mixed> $funcData
	 */
	private function parseSetData(array $funcData) : SetDataFunction{
		$min = 0;
		$max = 0;
		$dataRaw = $funcData["data"] ?? null;
		if(is_numeric($dataRaw)){
			$min = $max = self::asInt($dataRaw, 0);
		}elseif(is_array($dataRaw)){
			$min = self::asInt($dataRaw["min"] ?? 0, 0);
			$max = self::asInt($dataRaw["max"] ?? $min, $min);
		}
		return new SetDataFunction($min, $max);
	}

	/**
	 * @param array<string, mixed> $funcData
	 */
	private function parseLootingEnchant(array $funcData) : LootingEnchantFunction{
		$min = 0;
		$max = 1;
		$countRaw = $funcData["count"] ?? null;
		if(is_numeric($countRaw)){
			$min = $max = self::asInt($countRaw, 1);
		}elseif(is_array($countRaw)){
			$min = self::asInt($countRaw["min"] ?? 0, 0);
			$max = self::asInt($countRaw["max"] ?? $min, $min);
		}
		return new LootingEnchantFunction($min, $max);
	}

	/**
	 * @param array<string, mixed> $funcData
	 */
	private function parseEnchantWithLevels(array $funcData) : EnchantWithLevelsFunction{
		$min = 1;
		$max = 30;
		$levelsRaw = $funcData["levels"] ?? null;
		if(is_numeric($levelsRaw)){
			$min = $max = self::asInt($levelsRaw, 1);
		}elseif(is_array($levelsRaw)){
			$min = self::asInt($levelsRaw["min"] ?? 1, 1);
			$max = self::asInt($levelsRaw["max"] ?? $min, $min);
		}
		$treasure = self::asBool($funcData["treasure"] ?? false, false);
		return new EnchantWithLevelsFunction($min, $max, $treasure);
	}

	/**
	 * @param array<string, mixed> $funcData
	 */
	private function parseSetLore(array $funcData) : SetLoreFunction{
		$lore = [];
		$loreRaw = $funcData["lore"] ?? null;
		if(is_array($loreRaw)){
			foreach($loreRaw as $line){
				if(is_string($line)){
					$lore[] = $line;
				}
			}
		}elseif(is_string($loreRaw)){
			$lore[] = $loreRaw;
		}
		return new SetLoreFunction($lore);
	}

	/**
	 * @param array<string, mixed> $funcData
	 */
	private function parseSpecificEnchants(array $funcData) : SpecificEnchantsFunction{
		$enchants = [];
		$enchantsRaw = $funcData["enchants"] ?? null;
		if(is_array($enchantsRaw)){
			foreach($enchantsRaw as $key => $val){
				if(is_array($val)){
					$id = self::asString($val["id"] ?? "", "");
					$level = self::asInt($val["level"] ?? 1, 1);
					if($id !== ""){
						$enchants[$id] = $level;
					}
				}elseif(is_numeric($val)){
					$enchants[$key] = self::asInt($val, 1);
				}
			}
		}
		return new SpecificEnchantsFunction($enchants);
	}
}
