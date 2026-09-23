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

namespace pocketmine\addon;

use pocketmine\crafting\CraftingManager;
use pocketmine\crafting\ExactRecipeIngredient;
use pocketmine\crafting\FurnaceRecipe;
use pocketmine\crafting\FurnaceType;
use pocketmine\crafting\MetaWildcardRecipeIngredient;
use pocketmine\crafting\RecipeIngredient;
use pocketmine\crafting\ShapedRecipe;
use pocketmine\crafting\ShapelessRecipe;
use pocketmine\crafting\ShapelessRecipeType;
use pocketmine\crafting\TagWildcardRecipeIngredient;
use pocketmine\item\Item;
use pocketmine\item\StringToItemParser;
use pocketmine\world\format\io\GlobalItemDataHandlers;
use function array_is_list;
use function count;
use function explode;
use function in_array;
use function is_array;
use function is_int;
use function is_numeric;
use function is_string;
use function max;
use function str_contains;
use function strlen;
use function substr_count;

/**
 * Behavior-pack recipes (recipes/*.json) registered as server recipes, which every client then receives in the
 * crafting data like any vanilla recipe.
 *
 * Supported: minecraft:recipe_shaped (crafting table), minecraft:recipe_shapeless (crafting table, stonecutter,
 * cartography and smithing tables) and minecraft:recipe_furnace (furnace, blast furnace, smoker, campfires).
 * Ingredients may be an exact item ({"item", "data"}), any variant of an item ({"item"} with no data) or an item
 * tag ({"tag"}). Other recipe types (brewing, smithing transforms and trims, material reduction) are counted and
 * reported rather than guessed at.
 */
final class AddonRecipes{
	private const SHAPELESS_TYPES = [
		"crafting_table" => ShapelessRecipeType::CRAFTING,
		"stonecutter" => ShapelessRecipeType::STONECUTTER,
		"cartography_table" => ShapelessRecipeType::CARTOGRAPHY,
		"smithing_table" => ShapelessRecipeType::SMITHING,
	];
	private const FURNACE_TYPES = [
		"furnace" => FurnaceType::FURNACE,
		"blast_furnace" => FurnaceType::BLAST_FURNACE,
		"smoker" => FurnaceType::SMOKER,
		"campfire" => FurnaceType::CAMPFIRE,
		"soul_campfire" => FurnaceType::SOUL_CAMPFIRE,
	];

	/** @var list<string> */
	private array $errors = [];
	private int $registered = 0;
	private int $unsupported = 0;

	public function __construct(private CraftingManager $manager){}

	/**
	 * Registers one decoded recipe file. Problems are collected in getErrors(), never thrown: a broken recipe
	 * must not stop the rest of the add-on from loading.
	 *
	 * @param mixed[] $json
	 */
	public function register(array $json, string $source) : void{
		try{
			foreach($json as $type => $recipe){
				if($type === "format_version" || !is_array($recipe)){
					continue;
				}
				match($type){
					"minecraft:recipe_shaped" => $this->shaped($recipe),
					"minecraft:recipe_shapeless" => $this->shapeless($recipe),
					"minecraft:recipe_furnace" => $this->furnace($recipe),
					default => $this->unsupported++,
				};
			}
		}catch(AddonException $e){
			$this->errors[] = "$source: " . $e->getMessage();
		}
	}

	public function getRegisteredCount() : int{ return $this->registered; }

	public function getUnsupportedCount() : int{ return $this->unsupported; }

	/** @return list<string> */
	public function getErrors() : array{ return $this->errors; }

	/**
	 * @param mixed[] $recipe
	 * @throws AddonException
	 */
	private function shaped(array $recipe) : void{
		if(!in_array("crafting_table", self::tags($recipe), true)){
			$this->unsupported++;
			return;
		}
		$pattern = $recipe["pattern"] ?? null;
		if(!is_array($pattern) || $pattern === [] || count($pattern) > 3){
			throw new AddonException("shaped recipe needs a pattern of 1 to 3 rows");
		}
		$keys = is_array($recipe["key"] ?? null) ? $recipe["key"] : [];
		$ingredients = [];
		$width = 0;
		foreach($pattern as $row){
			if(!is_string($row) || strlen($row) > 3){
				throw new AddonException("shaped recipe rows must be strings of up to 3 characters");
			}
			$width = max($width, strlen($row));
		}
		$shape = [];
		foreach($pattern as $row){
			$row = str_pad($row, $width);
			for($i = 0; $i < $width; ++$i){
				$char = $row[$i];
				if($char === " " || isset($ingredients[$char])){
					continue;
				}
				if(!isset($keys[$char])){
					throw new AddonException("shaped recipe pattern uses '$char', which is not in its key");
				}
				$ingredients[$char] = $this->ingredient($keys[$char]);
			}
			$shape[] = $row;
		}
		$this->manager->registerShapedRecipe(new ShapedRecipe($shape, $ingredients, $this->results($recipe["result"] ?? null)));
		$this->registered++;
	}

	/**
	 * @param mixed[] $recipe
	 * @throws AddonException
	 */
	private function shapeless(array $recipe) : void{
		$type = null;
		foreach(self::tags($recipe) as $tag){
			if(isset(self::SHAPELESS_TYPES[$tag])){
				$type = self::SHAPELESS_TYPES[$tag];
				break;
			}
		}
		if($type === null){
			$this->unsupported++;
			return;
		}
		$ingredients = [];
		foreach(is_array($recipe["ingredients"] ?? null) ? $recipe["ingredients"] : [] as $entry){
			$count = is_array($entry) && is_int($entry["count"] ?? null) ? max(1, $entry["count"]) : 1;
			$ingredient = $this->ingredient($entry);
			for($i = 0; $i < $count; ++$i){
				$ingredients[] = $ingredient;
			}
		}
		if($ingredients === [] || count($ingredients) > 9){
			throw new AddonException("shapeless recipe needs 1 to 9 ingredients");
		}
		$this->manager->registerShapelessRecipe(new ShapelessRecipe($ingredients, $this->results($recipe["result"] ?? null), $type));
		$this->registered++;
	}

	/**
	 * @param mixed[] $recipe
	 * @throws AddonException
	 */
	private function furnace(array $recipe) : void{
		$input = $this->ingredient($recipe["input"] ?? null);
		$output = $this->results($recipe["output"] ?? null)[0];
		$any = false;
		foreach(self::tags($recipe) as $tag){
			if(isset(self::FURNACE_TYPES[$tag])){
				$this->manager->getFurnaceRecipeManager(self::FURNACE_TYPES[$tag])->register(new FurnaceRecipe($output, $input));
				$any = true;
			}
		}
		$any ? $this->registered++ : $this->unsupported++;
	}

	/**
	 * @param mixed[] $recipe
	 * @return list<string>
	 */
	private static function tags(array $recipe) : array{
		$tags = [];
		foreach(is_array($recipe["tags"] ?? null) ? $recipe["tags"] : [] as $tag){
			if(is_string($tag)){
				$tags[] = $tag;
			}
		}
		return $tags;
	}

	/** @throws AddonException */
	private function ingredient(mixed $value) : RecipeIngredient{
		if(is_array($value) && is_string($value["tag"] ?? null)){
			return new TagWildcardRecipeIngredient($value["tag"]);
		}
		[$name, $meta] = self::itemRef($value);
		if($meta === null){
			//no data given: any variant of the item. Match on its CURRENT id: an old name ("minecraft:planks")
			//is upgraded, and a wildcard on the old name would never match a real item.
			$item = $this->item($name, 0); //also fails early for an unknown item
			return new MetaWildcardRecipeIngredient(GlobalItemDataHandlers::getSerializer()->serializeType($item)->getName());
		}
		return new ExactRecipeIngredient($this->item($name, $meta));
	}

	/**
	 * @return list<Item>
	 * @throws AddonException
	 */
	private function results(mixed $value) : array{
		$entries = is_array($value) && array_is_list($value) ? $value : [$value];
		$results = [];
		foreach($entries as $entry){
			[$name, $meta] = self::itemRef($entry);
			$count = is_array($entry) && is_numeric($entry["count"] ?? null) ? max(1, (int) $entry["count"]) : 1;
			$results[] = $this->item($name, $meta ?? 0)->setCount($count);
		}
		if($results === []){
			throw new AddonException("recipe has no result");
		}
		return $results;
	}

	/**
	 * "minecraft:stick", "minecraft:wool:14" or {"item": ..., "data": ...}.
	 *
	 * @return array{0: string, 1: int|null} name and data (null when none was given)
	 * @throws AddonException
	 */
	private static function itemRef(mixed $value) : array{
		$meta = null;
		if(is_array($value)){
			$name = $value["item"] ?? null;
			if(is_numeric($value["data"] ?? null)){
				$meta = (int) $value["data"];
			}
		}else{
			$name = $value;
		}
		if(!is_string($name) || $name === ""){
			throw new AddonException("recipe item is missing its name");
		}
		if(substr_count($name, ":") === 2){
			[$namespace, $short, $data] = explode(":", $name, 3);
			$name = "$namespace:$short";
			$meta = is_numeric($data) ? (int) $data : $meta;
		}elseif(!str_contains($name, ":")){
			$name = "minecraft:$name";
		}
		return [$name, $meta];
	}

	/** @throws AddonException */
	private function item(string $name, int $meta) : Item{
		try{
			//the upgrader maps old ids and data values ("minecraft:red_flower" 9 -> pink tulip) and fills in block
			//state for block items, exactly as it does for items loaded from old worlds
			return GlobalItemDataHandlers::getDeserializer()->deserializeStack(
				GlobalItemDataHandlers::getUpgrader()->upgradeItemTypeDataString($name, $meta, 1, null)
			);
		}catch(\Throwable $e){
			if($meta === 0){
				//add-on items and aliases resolve by name
				$parser = StringToItemParser::getInstance();
				$item = $parser->parse($name) ?? $parser->parse(explode(":", $name, 2)[1]);
				if($item !== null){
					return $item;
				}
			}
			throw new AddonException("unknown recipe item $name" . ($meta !== 0 ? ":$meta" : "") . ", the server has no such item (" . $e->getMessage() . ")");
		}
	}
}
