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

namespace pocketmine\addon\block;

use pocketmine\addon\AddonException;
use pocketmine\addon\AddonJson;
use pocketmine\addon\item\AddonItemDefinition;
use pocketmine\inventory\CreativeCategory;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\FloatTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\nbt\tag\Tag;
use function array_is_list;
use function array_keys;
use function count;
use function explode;
use function in_array;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_numeric;
use function is_string;
use function ksort;
use function max;
use function range;
use function round;
use function str_replace;
use function ucwords;

/**
 * A custom block read from a behavior pack's blocks/*.json ("minecraft:block").
 *
 * States come from description.states (description.properties before 1.20.20) plus the states that
 * placement traits add. Permutations and every component are forwarded to the client in the block palette;
 * the server itself acts on the ones that affect the world: hardness, explosion resistance, light,
 * friction, collision and the placement traits.
 */
final class AddonBlockDefinition{
	/** Values the client uses for the states added by placement traits. */
	private const TRAIT_STATES = [
		"minecraft:cardinal_direction" => ["north", "south", "west", "east"],
		"minecraft:facing_direction" => ["down", "up", "north", "south", "west", "east"],
		"minecraft:block_face" => ["down", "up", "north", "south", "west", "east"],
		"minecraft:vertical_half" => ["bottom", "top"],
	];

	/**
	 * @param mixed[]                     $components
	 * @param mixed[][]                   $permutations
	 * @param array<string, list<int|string|bool>> $states state name => allowed values (sorted by name)
	 * @param array<string, list<int|string|bool>> $customStates the states the add-on itself declares
	 * @param array<string, mixed[]>      $traits
	 */
	private function __construct(
		private string $identifier,
		private string $displayName,
		private array $components,
		private array $permutations,
		private array $states,
		private array $customStates,
		private array $traits,
		private CreativeCategory $category,
		private ?string $group,
		private bool $hiddenInCommands,
		private string $packName
	){}

	/**
	 * @param mixed[] $json decoded file
	 * @throws AddonException
	 */
	public static function fromJson(array $json, string $source, string $packName) : self{
		$block = $json["minecraft:block"] ?? null;
		if(!is_array($block)){
			throw new AddonException("$source: not a minecraft:block definition");
		}
		$description = is_array($block["description"] ?? null) ? $block["description"] : [];
		$identifier = $description["identifier"] ?? null;
		if(!is_string($identifier) || !AddonJson::isValidIdentifier($identifier)){
			throw new AddonException("$source: missing or invalid identifier");
		}
		if(str_starts_with($identifier, "minecraft:")){
			throw new AddonException("$source: $identifier overrides a vanilla block, which is not supported", AddonException::VANILLA_OVERRIDE);
		}
		$components = is_array($block["components"] ?? null) && !array_is_list($block["components"]) ? $block["components"] : [];
		$permutations = [];
		foreach(is_array($block["permutations"] ?? null) ? $block["permutations"] : [] as $permutation){
			if(is_array($permutation) && is_string($permutation["condition"] ?? null)){
				$permutations[] = $permutation;
			}
		}

		$customStates = [];
		$declared = $description["states"] ?? $description["properties"] ?? [];
		foreach(is_array($declared) ? $declared : [] as $name => $values){
			$name = (string) $name;
			if(is_array($values) && array_is_list($values) && $values !== []){
				$customStates[$name] = $values;
			}elseif(is_array($values) && is_array($values["values"] ?? null)){
				//integer range form: {"values": {"min": 0, "max": 3}}
				$min = (int) ($values["values"]["min"] ?? 0);
				$max = (int) ($values["values"]["max"] ?? $min);
				$customStates[$name] = range($min, max($min, $max));
			}else{
				throw new AddonException("$source: state $name has no values");
			}
			foreach($customStates[$name] as $value){
				if(!is_int($value) && !is_string($value) && !is_bool($value)){
					throw new AddonException("$source: state $name has a value that is not an int, string or bool");
				}
			}
		}

		$traits = [];
		$states = $customStates;
		foreach(is_array($description["traits"] ?? null) ? $description["traits"] : [] as $traitName => $trait){
			if(!is_array($trait)){
				continue;
			}
			$traits[(string) $traitName] = $trait;
			foreach(is_array($trait["enabled_states"] ?? null) ? $trait["enabled_states"] : [] as $stateName){
				if(is_string($stateName) && isset(self::TRAIT_STATES[$stateName])){
					$states[$stateName] = self::TRAIT_STATES[$stateName];
				}
			}
		}
		ksort($states);

		$permutationCount = 1;
		foreach($states as $values){
			$permutationCount *= count($values);
		}
		if($permutationCount > 65536){
			throw new AddonException("$source: $identifier has $permutationCount state permutations, the limit is 65536");
		}

		$menu = is_array($description["menu_category"] ?? null) ? $description["menu_category"] : [];
		$displayName = AddonJson::scalar($components["minecraft:display_name"] ?? null);
		if(!is_string($displayName) || $displayName === ""){
			$displayName = ucwords(str_replace("_", " ", explode(":", $identifier, 2)[1]));
		}
		return new self(
			$identifier,
			$displayName,
			$components,
			$permutations,
			$states,
			$customStates,
			$traits,
			AddonItemDefinition::category(is_string($menu["category"] ?? null) ? $menu["category"] : "construction"),
			is_string($menu["group"] ?? null) && $menu["group"] !== "" ? $menu["group"] : null,
			(bool) ($menu["is_hidden_in_commands"] ?? false),
			$packName
		);
	}

	public function getIdentifier() : string{ return $this->identifier; }

	public function getDisplayName() : string{ return $this->displayName; }

	/** @return mixed[] */
	public function getComponents() : array{ return $this->components; }

	public function getCategory() : CreativeCategory{ return $this->category; }

	public function getGroup() : ?string{ return $this->group; }

	public function getPackName() : string{ return $this->packName; }

	/** @return array<string, list<int|string|bool>> every state of the block (including trait states), sorted by name */
	public function getStates() : array{ return $this->states; }

	public function hasTrait(string $name) : bool{ return isset($this->traits[$name]); }

	public function hasState(string $name) : bool{ return isset($this->states[$name]); }

	/**
	 * Every combination of state values, in palette order: states sorted by name, the first state varying
	 * slowest - the order the client enumerates a block's permutations in.
	 *
	 * @return list<array<string, int|string|bool>>
	 */
	public function getPermutationValues() : array{
		$result = [[]];
		foreach($this->states as $name => $values){
			$next = [];
			foreach($result as $partial){
				foreach($values as $value){
					$next[] = $partial + [$name => $value];
				}
			}
			$result = $next;
		}
		return $result;
	}

	public function getHardness() : float{
		$component = $this->components["minecraft:destructible_by_mining"] ?? null;
		if($component === false){
			return -1.0; //unbreakable
		}
		$seconds = is_array($component) ? ($component["seconds_to_destroy"] ?? null) : null;
		//a hand breaks a block in hardness * 1.5 seconds
		return is_numeric($seconds) ? max(0.0, (float) $seconds / 1.5) : 1.0;
	}

	public function getBlastResistance() : float{
		$component = $this->components["minecraft:destructible_by_explosion"] ?? null;
		if($component === false){
			return 18000000.0;
		}
		$value = is_array($component) ? ($component["explosion_resistance"] ?? null) : null;
		return is_numeric($value) ? (float) $value : $this->getHardness() * 5;
	}

	public function getLightEmission() : int{
		$value = AddonJson::scalar($this->components["minecraft:light_emission"] ?? null, "emission");
		return is_numeric($value) ? max(0, min(15, (int) $value)) : 0;
	}

	public function getLightDampening() : int{
		$value = AddonJson::scalar($this->components["minecraft:light_dampening"] ?? null, "lightLevel");
		return is_numeric($value) ? max(0, min(15, (int) $value)) : 15;
	}

	public function getFriction() : float{
		$value = AddonJson::scalar($this->components["minecraft:friction"] ?? null);
		//JSON friction is how much a block slows you (0.4 default); the engine factor is the momentum kept
		return is_numeric($value) ? max(0.0, min(1.0, 1.0 - (float) $value)) : 0.6;
	}

	/** @return array{enabled: bool, origin: float[], size: float[]} in block pixels (16 per block) */
	public function getCollisionBox() : array{
		return self::box($this->components["minecraft:collision_box"] ?? null);
	}

	/** @return array{enabled: bool, origin: float[], size: float[]} */
	private static function box(mixed $component) : array{
		if($component === false){
			return ["enabled" => false, "origin" => [-8.0, 0.0, -8.0], "size" => [16.0, 16.0, 16.0]];
		}
		$origin = is_array($component) && is_array($component["origin"] ?? null) ? $component["origin"] : [-8, 0, -8];
		$size = is_array($component) && is_array($component["size"] ?? null) ? $component["size"] : [16, 16, 16];
		return [
			"enabled" => true,
			"origin" => [(float) ($origin[0] ?? -8), (float) ($origin[1] ?? 0), (float) ($origin[2] ?? -8)],
			"size" => [(float) ($size[0] ?? 16), (float) ($size[1] ?? 16), (float) ($size[2] ?? 16)],
		];
	}

	/** Whether the block renders and blocks light like a full opaque cube. */
	public function isOpaqueCube() : bool{
		$box = $this->getCollisionBox();
		if(!$box["enabled"] || $box["size"] !== [16.0, 16.0, 16.0] || $this->getLightDampening() < 15){
			return false;
		}
		$geometry = $this->components["minecraft:geometry"] ?? null;
		$geometryId = is_array($geometry) ? ($geometry["identifier"] ?? null) : $geometry;
		if(is_string($geometryId) && $geometryId !== "minecraft:geometry.full_block"){
			return false;
		}
		$materials = $this->components["minecraft:material_instances"] ?? null;
		if(is_array($materials)){
			foreach($materials as $material){
				$method = is_array($material) ? ($material["render_method"] ?? "opaque") : "opaque";
				if($method !== "opaque"){
					return false;
				}
			}
		}
		return true;
	}

	/**
	 * This block's entry in the StartGamePacket block palette. The client builds the block's model,
	 * collision, lighting and permutations from it.
	 */
	public function buildPaletteNbt(int $numericId) : CompoundTag{
		$properties = [];
		foreach($this->customStates as $name => $values){
			$enum = [];
			foreach($values as $value){
				$enum[] = self::stateValueTag($value);
			}
			$properties[] = CompoundTag::create()->setString("name", $name)->setTag("enum", new ListTag($enum));
		}

		$permutations = [];
		foreach($this->permutations as $permutation){
			$components = is_array($permutation["components"] ?? null) ? $permutation["components"] : [];
			$permutations[] = CompoundTag::create()
				->setString("condition", (string) $permutation["condition"])
				->setTag("components", self::convertComponents($components, false));
		}

		$traits = [];
		foreach($this->traits as $name => $trait){
			$enabled = CompoundTag::create();
			foreach(is_array($trait["enabled_states"] ?? null) ? $trait["enabled_states"] : [] as $stateName){
				if(is_string($stateName)){
					$enabled->setByte(explode(":", $stateName, 2)[1] ?? $stateName, 1);
				}
			}
			$tag = CompoundTag::create()->setString("name", $name)->setTag("enabled_states", $enabled);
			if(is_numeric($trait["y_rotation_offset"] ?? null)){
				$tag->setFloat("y_rotation_offset", (float) $trait["y_rotation_offset"]);
			}
			$traits[] = $tag;
		}

		$components = self::convertComponents($this->components, true);
		if(!$components->getTag("minecraft:display_name") instanceof CompoundTag){
			$components->setTag("minecraft:display_name", CompoundTag::create()->setString("value", $this->displayName));
		}

		return CompoundTag::create()
			->setTag("components", $components)
			->setTag("menu_category", CompoundTag::create()
				->setString("category", strtolower($this->category->name))
				->setString("group", $this->group ?? "")
				->setByte("is_hidden_in_commands", $this->hiddenInCommands ? 1 : 0))
			->setInt("molangVersion", 12)
			->setTag("properties", new ListTag($properties))
			->setTag("permutations", new ListTag($permutations))
			->setTag("traits", new ListTag($traits))
			->setTag("vanilla_block_data", CompoundTag::create()->setInt("block_id", $numericId));
	}

	private static function stateValueTag(int|string|bool $value) : Tag{
		return match(true){
			is_bool($value) => new ByteTag($value ? 1 : 0),
			is_int($value) => new IntTag($value),
			default => new StringTag($value),
		};
	}

	/**
	 * Block components in the shape the client reads them from the palette. A few components are written
	 * differently in JSON than on the network; everything else is converted as-is.
	 *
	 * @param mixed[] $components
	 */
	private static function convertComponents(array $components, bool $base) : CompoundTag{
		$out = CompoundTag::create();
		foreach($components as $name => $value){
			$name = (string) $name;
			switch($name){
				case "minecraft:geometry":
					$geometry = is_string($value) ? ["identifier" => $value] : (is_array($value) ? $value : []);
					$tag = CompoundTag::create()
						->setString("identifier", (string) ($geometry["identifier"] ?? "minecraft:geometry.full_block"))
						->setString("culling", (string) ($geometry["culling"] ?? ""))
						->setTag("bone_visibility", self::boneVisibility($geometry["bone_visibility"] ?? []));
					$out->setTag($name, $tag);
					break;
				case "minecraft:material_instances":
					$out->setTag($name, self::materialInstances(is_array($value) ? $value : []));
					break;
				case "minecraft:collision_box":
				case "minecraft:selection_box":
					$box = self::box($value);
					$out->setTag($name, CompoundTag::create()
						->setByte("enabled", $box["enabled"] ? 1 : 0)
						->setTag("origin", new ListTag([new FloatTag($box["origin"][0]), new FloatTag($box["origin"][1]), new FloatTag($box["origin"][2])]))
						->setTag("size", new ListTag([new FloatTag($box["size"][0]), new FloatTag($box["size"][1]), new FloatTag($box["size"][2])])));
					break;
				case "minecraft:light_emission":
					$out->setTag($name, CompoundTag::create()->setByte("emission", (int) AddonJson::scalar($value, "emission")));
					break;
				case "minecraft:light_dampening":
					$out->setTag($name, CompoundTag::create()->setByte("lightLevel", (int) AddonJson::scalar($value, "lightLevel")));
					break;
				case "minecraft:destructible_by_mining":
					$seconds = $value === false ? -1.0 : (is_array($value) && is_numeric($value["seconds_to_destroy"] ?? null) ? (float) $value["seconds_to_destroy"] : 0.0);
					$out->setTag($name, CompoundTag::create()->setFloat("value", $seconds));
					break;
				case "minecraft:destructible_by_explosion":
					$resistance = $value === false ? -1.0 : (is_array($value) && is_numeric($value["explosion_resistance"] ?? null) ? (float) $value["explosion_resistance"] : 0.0);
					$out->setTag($name, CompoundTag::create()->setFloat("explosion_resistance", $resistance));
					break;
				case "minecraft:friction":
					$out->setTag($name, CompoundTag::create()->setFloat("value", (float) AddonJson::scalar($value)));
					break;
				case "minecraft:display_name":
					$out->setTag($name, CompoundTag::create()->setString("value", (string) AddonJson::scalar($value)));
					break;
				case "minecraft:transformation":
					$out->setTag($name, self::transformation(is_array($value) ? $value : []));
					break;
				default:
					if(!is_array($value) || array_is_list($value)){
						$value = ["value" => $value];
					}
					$out->setTag($name, AddonJson::toTag($value));
			}
		}
		if($base && !$out->getTag("minecraft:material_instances") instanceof CompoundTag && !$out->getTag("minecraft:geometry") instanceof CompoundTag){
			//no model given: a unit cube textured from terrain_texture.json under the block's short name
			$out->setTag("minecraft:unit_cube", CompoundTag::create());
		}
		return $out;
	}

	private static function boneVisibility(mixed $bones) : CompoundTag{
		$tag = CompoundTag::create();
		foreach(is_array($bones) ? $bones : [] as $bone => $visible){
			if(is_bool($visible)){
				$tag->setByte((string) $bone, $visible ? 1 : 0);
			}elseif(is_string($visible)){
				//molang condition: the client evaluates it
				$tag->setString((string) $bone, $visible);
			}
		}
		return $tag;
	}

	/** @param mixed[] $instances */
	private static function materialInstances(array $instances) : CompoundTag{
		$mappings = CompoundTag::create();
		$materials = CompoundTag::create();
		foreach($instances as $face => $material){
			$face = (string) $face;
			if(is_string($material)){
				//"north": "side" points one face at another material
				$mappings->setString($face, $material);
				continue;
			}
			if(!is_array($material)){
				continue;
			}
			$tag = CompoundTag::create()
				->setString("texture", (string) ($material["texture"] ?? ""))
				->setString("render_method", (string) ($material["render_method"] ?? "opaque"));
			if(isset($material["face_dimming"])){
				$tag->setByte("face_dimming", (bool) $material["face_dimming"] ? 1 : 0);
			}
			if(isset($material["ambient_occlusion"])){
				$ao = $material["ambient_occlusion"];
				is_bool($ao) ? $tag->setByte("ambient_occlusion", $ao ? 1 : 0) : $tag->setFloat("ambient_occlusion", (float) $ao);
			}
			if(isset($material["isotropic"])){
				$tag->setByte("isotropic", (bool) $material["isotropic"] ? 1 : 0);
			}
			if(isset($material["tint_method"]) && is_string($material["tint_method"])){
				$tag->setString("tint_method", $material["tint_method"]);
			}
			$materials->setTag($face, $tag);
		}
		return CompoundTag::create()->setTag("mappings", $mappings)->setTag("materials", $materials);
	}

	/** @param mixed[] $value */
	private static function transformation(array $value) : CompoundTag{
		$rotation = is_array($value["rotation"] ?? null) ? $value["rotation"] : [0, 0, 0];
		$scale = is_array($value["scale"] ?? null) ? $value["scale"] : [1, 1, 1];
		$translation = is_array($value["translation"] ?? null) ? $value["translation"] : [0, 0, 0];
		return CompoundTag::create()
			->setInt("RX", (int) round(((float) ($rotation[0] ?? 0)) / 90) % 4)
			->setInt("RY", (int) round(((float) ($rotation[1] ?? 0)) / 90) % 4)
			->setInt("RZ", (int) round(((float) ($rotation[2] ?? 0)) / 90) % 4)
			->setFloat("SX", (float) ($scale[0] ?? 1))
			->setFloat("SY", (float) ($scale[1] ?? 1))
			->setFloat("SZ", (float) ($scale[2] ?? 1))
			->setFloat("TX", (float) ($translation[0] ?? 0))
			->setFloat("TY", (float) ($translation[1] ?? 0))
			->setFloat("TZ", (float) ($translation[2] ?? 0));
	}

	/** Suppresses an "unused" warning for helpers kept for completeness. */
	public static function traitStateNames() : array{
		return array_keys(self::TRAIT_STATES);
	}
}
