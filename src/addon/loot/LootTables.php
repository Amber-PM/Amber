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

namespace pocketmine\addon\loot;

use pocketmine\addon\AddonJson;
use pocketmine\item\enchantment\AvailableEnchantmentRegistry;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\Item;
use pocketmine\item\StringToItemParser;
use pocketmine\world\format\io\GlobalItemDataHandlers;
use Symfony\Component\Filesystem\Path;
use function array_key_exists;
use function array_rand;
use function explode;
use function file_get_contents;
use function floor;
use function is_array;
use function is_file;
use function is_numeric;
use function is_string;
use function max;
use function min;
use function mt_getrandmax;
use function mt_rand;
use function round;
use function str_contains;
use function str_replace;
use function strtolower;
use function substr_count;

/**
 * Behavior-pack loot tables (loot_tables/*.json), rolled for add-on entity deaths and add-on block breaks.
 *
 * Supported, as in the Bedrock format:
 * - pools with "rolls" (a number or {"min", "max"}), "bonus_rolls" and pool "conditions";
 * - entries of type item, loot_table (nested) and empty, with "weight", "quality" and entry "conditions";
 * - functions set_count, set_data, looting_enchant, set_name, set_lore, set_damage, furnace_smelt and
 *   enchant_randomly;
 * - conditions random_chance, random_chance_with_looting, random_regional_difficulty_chance (as its max
 *   chance), killed_by_player, killed_by_player_or_pets and entity_properties (on_fire).
 *
 * Unknown functions are skipped, unknown conditions pass, and both are reported once, so a table using newer
 * features still drops its items.
 */
final class LootTables{
	/** @var array<string, mixed[]|null> normalised path => decoded table (null when missing or broken) */
	private array $tables = [];
	/** @var array<string, true> */
	private array $reported = [];

	/**
	 * @param list<string> $packRoots behavior pack directories, searched in order
	 */
	public function __construct(
		private array $packRoots,
		private \Logger $logger
	){}

	public function exists(string $path) : bool{
		return $this->load($path) !== null;
	}

	/**
	 * Rolls a table. Returns an empty list for a missing table (reported once).
	 *
	 * @return list<Item>
	 */
	public function roll(string $path, LootContext $context, int $depth = 0) : array{
		$table = $this->load($path);
		if($table === null || $depth > 8){
			return [];
		}
		$drops = [];
		foreach(is_array($table["pools"] ?? null) ? $table["pools"] : [] as $pool){
			if(!is_array($pool) || !$this->conditionsPass($pool["conditions"] ?? [], $context)){
				continue;
			}
			$rolls = self::range($pool["rolls"] ?? 1) + (int) floor(self::number($pool["bonus_rolls"] ?? 0) * $context->lootingLevel());
			$entries = [];
			$totalWeight = 0;
			foreach(is_array($pool["entries"] ?? null) ? $pool["entries"] : [] as $entry){
				if(!is_array($entry) || !$this->conditionsPass($entry["conditions"] ?? [], $context)){
					continue;
				}
				$weight = max(0, (int) self::number($entry["weight"] ?? 1) + (int) floor(self::number($entry["quality"] ?? 0) * $context->lootingLevel()));
				if($weight > 0){
					$entries[] = [$entry, $weight];
					$totalWeight += $weight;
				}
			}
			if($entries === []){
				continue;
			}
			for($i = 0; $i < $rolls; ++$i){
				$pick = mt_rand(1, $totalWeight);
				foreach($entries as [$entry, $weight]){
					$pick -= $weight;
					if($pick <= 0){
						foreach($this->entry($entry, $context, $depth) as $item){
							$drops[] = $item;
						}
						break;
					}
				}
			}
		}
		return $drops;
	}

	/**
	 * @param mixed[] $entry
	 * @return list<Item>
	 */
	private function entry(array $entry, LootContext $context, int $depth) : array{
		$type = is_string($entry["type"] ?? null) ? $entry["type"] : "item";
		if($type === "empty"){
			return [];
		}
		if($type === "loot_table"){
			return is_string($entry["name"] ?? null) ? $this->roll($entry["name"], $context, $depth + 1) : [];
		}
		$name = $entry["name"] ?? null;
		if(!is_string($name)){
			return [];
		}
		$item = $this->item($name, 0);
		if($item === null){
			$this->reportOnce("item:$name", "Loot table item $name is not an item this server has; it is not dropped");
			return [];
		}
		foreach(is_array($entry["functions"] ?? null) ? $entry["functions"] : [] as $function){
			if(!is_array($function) || !$this->conditionsPass($function["conditions"] ?? [], $context)){
				continue;
			}
			$item = $this->applyFunction($item, $function, $context) ?? $item;
		}
		if($item->getCount() <= 0){
			return [];
		}
		//split into stacks the item allows
		$stacks = [];
		$remaining = $item->getCount();
		while($remaining > 0){
			$count = min($remaining, $item->getMaxStackSize());
			$stacks[] = (clone $item)->setCount($count);
			$remaining -= $count;
		}
		return $stacks;
	}

	/** @param mixed[] $function */
	private function applyFunction(Item $item, array $function, LootContext $context) : ?Item{
		$name = strtolower(str_replace("minecraft:", "", (string) ($function["function"] ?? "")));
		switch($name){
			case "set_count":
				return $item->setCount(self::range($function["count"] ?? 1));
			case "looting_enchant":
				return $item->setCount($item->getCount() + self::range($function["count"] ?? ["min" => 0, "max" => 1]) * $context->lootingLevel());
			case "set_data":
			case "set_data_from_color_index":
				$data = self::range($function["data"] ?? 0);
				$typeId = GlobalItemDataHandlers::getSerializer()->serializeType($item)->getName();
				return ($this->item($typeId, $data) ?? $item)->setCount($item->getCount());
			case "set_name":
				return is_string($function["name"] ?? null) ? $item->setCustomName($function["name"]) : $item;
			case "set_lore":
				$lore = [];
				foreach(is_array($function["lore"] ?? null) ? $function["lore"] : [] as $line){
					if(is_string($line)){
						$lore[] = $line;
					}
				}
				return $item->setLore($lore);
			case "set_damage":
				if($item instanceof \pocketmine\item\Durable){
					$fraction = self::randomFloat($function["damage"] ?? 1.0);
					$item->setDamage((int) round($item->getMaxDurability() * (1.0 - max(0.0, min(1.0, $fraction)))));
				}
				return $item;
			case "furnace_smelt":
				if($context->thisEntity !== null && $context->thisEntity->isOnFire()){
					$recipe = \pocketmine\Server::getInstance()->getCraftingManager()->getFurnaceRecipeManager(\pocketmine\crafting\FurnaceType::FURNACE)->match($item);
					if($recipe !== null){
						return $recipe->getResult()->setCount($item->getCount());
					}
				}
				return $item;
			case "enchant_randomly":
			case "enchant_with_levels":
				//one random enchantment that applies to the item, at a random level
				$candidates = AvailableEnchantmentRegistry::getInstance()->getPrimaryEnchantmentsForItem($item);
				if($candidates !== []){
					$enchantment = $candidates[array_rand($candidates)];
					$item->addEnchantment(new EnchantmentInstance($enchantment, mt_rand(1, max(1, $enchantment->getMaxLevel()))));
				}
				return $item;
		}
		$this->reportOnce("function:$name", "Loot table function $name is not supported; it is skipped");
		return $item;
	}

	/** @param mixed $conditions */
	private function conditionsPass(mixed $conditions, LootContext $context) : bool{
		foreach(is_array($conditions) ? $conditions : [] as $condition){
			if(!is_array($condition)){
				continue;
			}
			$name = strtolower(str_replace("minecraft:", "", (string) ($condition["condition"] ?? "")));
			$pass = match($name){
				"random_chance" => self::chance(self::number($condition["chance"] ?? 1)),
				"random_chance_with_looting" => self::chance(self::number($condition["chance"] ?? 1) + self::number($condition["looting_multiplier"] ?? 0) * $context->lootingLevel()),
				"random_regional_difficulty_chance" => self::chance(self::number($condition["max_chance"] ?? 1)),
				"killed_by_player", "killed_by_player_or_pets" => $context->killedByPlayer,
				"entity_properties" => $this->entityProperties($condition, $context),
				default => $this->unknownCondition($name),
			};
			if(!$pass){
				return false;
			}
		}
		return true;
	}

	/** @param mixed[] $condition */
	private function entityProperties(array $condition, LootContext $context) : bool{
		$properties = is_array($condition["properties"] ?? null) ? $condition["properties"] : [];
		$entity = ($condition["entity"] ?? "this") === "killer" ? $context->killer : $context->thisEntity;
		if(isset($properties["on_fire"])){
			return $entity !== null && $entity->isOnFire() === (bool) $properties["on_fire"];
		}
		return true;
	}

	private function unknownCondition(string $name) : bool{
		$this->reportOnce("condition:$name", "Loot table condition $name is not supported; it is treated as passing");
		return true;
	}

	/** @return mixed[]|null */
	private function load(string $path) : ?array{
		$key = strtolower(str_replace("\\", "/", $path));
		if(!str_contains($key, ".json")){
			$key .= ".json";
		}
		if(array_key_exists($key, $this->tables)){
			return $this->tables[$key];
		}
		$table = null;
		foreach($this->packRoots as $root){
			$file = Path::join($root, $key);
			if(is_file($file)){
				try{
					$table = AddonJson::decode((string) file_get_contents($file), $key);
				}catch(\pocketmine\addon\AddonException $e){
					$this->logger->warning("Loot table $key could not be read: " . $e->getMessage());
				}
				break;
			}
		}
		if($table === null){
			$this->reportOnce("table:$key", "Loot table $key was not found in any behavior pack");
		}
		return $this->tables[$key] = $table;
	}

	private function item(string $name, int $meta) : ?Item{
		//resolved once per name; every roll gets its own copy
		$key = "$name#$meta";
		if(!isset($this->items[$key])){
			$this->items[$key] = $this->resolveItem($name, $meta) ?? false;
		}
		$item = $this->items[$key];
		return $item === false ? null : clone $item;
	}

	/** @var array<string, Item|false> */
	private array $items = [];

	private function resolveItem(string $name, int $meta) : ?Item{
		if(substr_count($name, ":") === 2){
			[$namespace, $short, $data] = explode(":", $name, 3);
			$name = "$namespace:$short";
			$meta = is_numeric($data) ? (int) $data : $meta;
		}elseif(!str_contains($name, ":")){
			$name = "minecraft:$name";
		}
		try{
			return GlobalItemDataHandlers::getDeserializer()->deserializeStack(GlobalItemDataHandlers::getUpgrader()->upgradeItemTypeDataString($name, $meta, 1, null));
		}catch(\Throwable){
			$parser = StringToItemParser::getInstance();
			return $meta === 0 ? ($parser->parse($name) ?? $parser->parse(explode(":", $name, 2)[1])) : null;
		}
	}

	private function reportOnce(string $key, string $message) : void{
		if(!isset($this->reported[$key])){
			$this->reported[$key] = true;
			$this->logger->warning($message);
		}
	}

	/** A number, or a random integer in {"min", "max"}. */
	private static function range(mixed $value) : int{
		if(is_array($value)){
			$min = (int) floor(self::number($value["min"] ?? 0));
			$max = (int) floor(self::number($value["max"] ?? $min));
			return mt_rand(min($min, $max), max($min, $max));
		}
		return (int) floor(self::number($value));
	}

	private static function randomFloat(mixed $value) : float{
		if(is_array($value)){
			$min = self::number($value["min"] ?? 0);
			$max = self::number($value["max"] ?? $min);
			return $min + (mt_rand() / mt_getrandmax()) * ($max - $min);
		}
		return self::number($value);
	}

	private static function number(mixed $value) : float{
		return is_numeric($value) ? (float) $value : 0.0;
	}

	private static function chance(float $chance) : bool{
		return $chance >= 1.0 || (mt_rand() / mt_getrandmax()) < $chance;
	}
}
