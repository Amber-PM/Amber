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
use pocketmine\nbt\NBT;
use function array_filter;
use function array_reverse;
use function array_unique;
use function array_values;
use function min;
use function strtolower;

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

	/** First protocol with the 1.21.130 block component format (collision "boxes", int light values). */
	public const PROTOCOL_BOXES = 898;

	/** Block components read by the server only. */
	public const SERVER_ONLY = [
		"minecraft:loot", "minecraft:tick", "minecraft:queued_ticking", "minecraft:random_ticking", "minecraft:custom_components",
		"minecraft:destruction_particles", "minecraft:entity_fall_on", "minecraft:redstone_conductivity",
	];

	/** @var list<string>|null */
	private ?array $unsentComponents = null;

	/**
	 * This block's entry in the StartGamePacket block palette, for one client protocol. The client builds the
	 * block's model, collision, lighting and permutations from it.
	 *
	 * Shapes follow CustomiesDevs/Customies (MIT, 1.21.x clients) and df-mc/dragonfly (1.21.130+ clients).
	 * 1.21.130 changed the collision box to a list of min/max boxes and light values to ints.
	 *
	 * @param int $blockId the client-order id (see AddonManager: FNV-1 64 order of block names)
	 */
	public function buildPaletteNbt(int $blockId, int $protocolId) : CompoundTag{
		$unsent = [];
		$components = self::convertComponents($this->components, true, $protocolId, $unsent, $this->identifier);

		$permutations = [];
		foreach($this->permutations as $permutation){
			$json = is_array($permutation["components"] ?? null) ? $permutation["components"] : [];
			$permutations[] = CompoundTag::create()
				->setString("condition", (string) $permutation["condition"])
				->setTag("components", self::convertComponents($json, false, $protocolId, $unsent, $this->identifier));
		}
		$this->unsentComponents = array_values(array_unique($unsent));

		if($permutations !== [] || $this->customStates !== []){
			//lets the client predict placement of a block whose look depends on its state
			$components->setTag("minecraft:on_player_placing", CompoundTag::create()->setString("triggerType", "placement_trigger"));
		}
		if(!$components->getTag("minecraft:display_name") instanceof CompoundTag){
			$components->setTag("minecraft:display_name", CompoundTag::create()->setString("value", $this->displayName));
		}
		$category = strtolower($this->category->name);
		$components->setTag("minecraft:creative_category", CompoundTag::create()->setString("category", $category)->setString("group", $this->group ?? ""));

		$root = CompoundTag::create()
			->setTag("components", $components)
			->setTag("menu_category", CompoundTag::create()
				->setString("category", $category)
				->setString("group", $this->group ?? "")
				->setByte("is_hidden_in_commands", $this->hiddenInCommands ? 1 : 0))
			->setInt("molangVersion", 10)
			->setTag("vanilla_block_data", CompoundTag::create()->setInt("block_id", $blockId));

		//custom states, in the reverse of the order their permutations are generated in (the client expands
		//the list the other way round; see getPermutationValues())
		$properties = [];
		foreach(array_reverse($this->customStateNamesInOrder()) as $name){
			$enum = [];
			foreach($this->customStates[$name] as $value){
				$enum[] = self::stateValueTag($value);
			}
			$properties[] = CompoundTag::create()->setString("name", $name)->setTag("enum", new ListTag($enum));
		}
		if($properties !== []){
			$root->setTag("properties", new ListTag($properties));
		}
		if($permutations !== []){
			$root->setTag("permutations", new ListTag($permutations));
		}

		$traits = [];
		foreach($this->traits as $name => $trait){
			$enabled = CompoundTag::create();
			$wanted = is_array($trait["enabled_states"] ?? null) ? $trait["enabled_states"] : [];
			foreach(self::TRAIT_FLAGS[$name] ?? [] as $stateName){
				$enabled->setByte(explode(":", $stateName, 2)[1], in_array($stateName, $wanted, true) ? 1 : 0);
			}
			$tag = CompoundTag::create()->setString("name", $name)->setTag("enabled_states", $enabled);
			if($name === "minecraft:placement_direction"){
				$tag->setFloat("y_rotation_offset", is_numeric($trait["y_rotation_offset"] ?? null) ? (float) $trait["y_rotation_offset"] : 0.0);
			}
			$traits[] = $tag;
		}
		if($traits !== []){
			$root->setTag("traits", new ListTag($traits));
		}
		return $root;
	}

	/** Trait => every state flag the client expects in enabled_states. */
	private const TRAIT_FLAGS = [
		"minecraft:placement_direction" => ["minecraft:cardinal_direction", "minecraft:facing_direction"],
		"minecraft:placement_position" => ["minecraft:block_face", "minecraft:vertical_half"],
	];

	/**
	 * JSON components the client is not sent (server-only ones and unknown ones). Filled by buildPaletteNbt().
	 *
	 * @return list<string>
	 */
	public function getUnsentComponents() : array{
		if($this->unsentComponents === null){
			$this->buildPaletteNbt(0, self::PROTOCOL_BOXES);
		}
		return $this->unsentComponents ?? [];
	}

	/** @return list<string> unsent components that are not simply server-only */
	public function getUnknownComponents() : array{
		return array_values(array_filter($this->getUnsentComponents(), static fn(string $name) : bool => !in_array($name, self::SERVER_ONLY, true)));
	}

	/** @return list<string> custom state names, in the order getPermutationValues() nests them */
	private function customStateNamesInOrder() : array{
		return array_values(array_filter(array_keys($this->states), fn(string $name) : bool => isset($this->customStates[$name])));
	}

	private static function stateValueTag(int|string|bool $value) : Tag{
		return match(true){
			is_bool($value) => new ByteTag($value ? 1 : 0),
			is_int($value) => new IntTag($value),
			default => new StringTag($value),
		};
	}

	/**
	 * Block components in the network form, for one protocol.
	 *
	 * @param mixed[]      $components
	 * @param list<string> $unsent     receives the names not sent
	 */
	private static function convertComponents(array $components, bool $base, int $protocolId, array &$unsent, string $identifier) : CompoundTag{
		$boxes = $protocolId >= self::PROTOCOL_BOXES;
		$out = CompoundTag::create();
		foreach($components as $name => $value){
			$name = (string) $name;
			switch($name){
				case "minecraft:unit_cube":
					$out->setTag("minecraft:geometry", self::geometry("minecraft:geometry.full_block"));
					break;
				case "minecraft:geometry":
					$out->setTag($name, self::geometry($value));
					break;
				case "minecraft:material_instances":
					$out->setTag($name, self::materialInstances(is_array($value) ? $value : []));
					break;
				case "minecraft:collision_box":
					$list = self::boxes($value);
					if($boxes){
						$tags = [];
						foreach($list["boxes"] as [$origin, $size]){
							$tags[] = CompoundTag::create()
								->setFloat("minX", $origin[0] + 8)->setFloat("minY", $origin[1])->setFloat("minZ", $origin[2] + 8)
								->setFloat("maxX", $origin[0] + 8 + $size[0])->setFloat("maxY", $origin[1] + $size[1])->setFloat("maxZ", $origin[2] + 8 + $size[2]);
						}
						$out->setTag($name, CompoundTag::create()->setByte("enabled", $list["enabled"] ? 1 : 0)->setTag("boxes", new ListTag($tags, NBT::TAG_Compound)));
					}else{
						//before 1.21.130 there is one box: the first (packs for older clients only have one)
						[$origin, $size] = $list["boxes"][0] ?? [[-8.0, 0.0, -8.0], [16.0, 16.0, 16.0]];
						$out->setTag($name, self::originSize($list["enabled"], $origin, $size));
					}
					break;
				case "minecraft:selection_box":
					$list = self::boxes($value);
					[$origin, $size] = $list["boxes"][0] ?? [[-8.0, 0.0, -8.0], [16.0, 16.0, 16.0]];
					$out->setTag($name, self::originSize($list["enabled"], $origin, $size));
					break;
				case "minecraft:light_emission":
					$level = max(0, min(15, (int) AddonJson::scalar($value, "emission")));
					$out->setTag($name, $boxes ? CompoundTag::create()->setInt("emission", $level) : CompoundTag::create()->setByte("emission", $level));
					break;
				case "minecraft:light_dampening":
					$level = max(0, min(15, (int) AddonJson::scalar($value, "lightLevel")));
					$out->setTag($name, $boxes ? CompoundTag::create()->setInt("lightLevel", $level) : CompoundTag::create()->setByte("lightLevel", $level));
					break;
				case "minecraft:destructible_by_mining":
					$seconds = $value === false ? -1.0 : (is_array($value) && is_numeric($value["seconds_to_destroy"] ?? null) ? (float) $value["seconds_to_destroy"] : (is_numeric($value) ? (float) $value : 0.0));
					$out->setTag($name, CompoundTag::create()->setFloat("value", $seconds));
					break;
				case "minecraft:destructible_by_explosion":
					$resistance = $value === false ? -1.0 : (is_array($value) && is_numeric($value["explosion_resistance"] ?? null) ? (float) $value["explosion_resistance"] : (is_numeric($value) ? (float) $value : 0.0));
					//older clients read "value" (Customies), the current format names it; both are sent
					$out->setTag($name, CompoundTag::create()->setFloat("value", $resistance)->setFloat("explosion_resistance", $resistance));
					break;
				case "minecraft:friction":
					$out->setTag($name, CompoundTag::create()->setFloat("value", (float) AddonJson::scalar($value)));
					break;
				case "minecraft:flammable":
					$catch = is_array($value) ? (int) ($value["catch_chance_modifier"] ?? 5) : ($value === false ? 0 : 5);
					$destroy = is_array($value) ? (int) ($value["destroy_chance_modifier"] ?? 20) : ($value === false ? 0 : 20);
					//key names differ between client generations (Customies / dragonfly); both are sent
					$out->setTag($name, CompoundTag::create()
						->setInt("catch_chance_modifier", $catch)->setInt("destroy_chance_modifier", $destroy)
						->setInt("flame_odds", $catch)->setInt("burn_odds", $destroy));
					break;
				case "minecraft:display_name":
					$out->setTag($name, CompoundTag::create()->setString("value", (string) AddonJson::scalar($value)));
					break;
				case "minecraft:map_color":
					$color = is_array($value) ? ($value["color"] ?? $value["value"] ?? "") : $value;
					$out->setTag($name, CompoundTag::create()->setString("value", is_string($color) ? $color : ""));
					break;
				case "minecraft:breathability":
					$out->setTag($name, CompoundTag::create()->setString("value", (string) AddonJson::scalar($value)));
					break;
				case "minecraft:transformation":
					$out->setTag($name, self::transformation(is_array($value) ? $value : []));
					break;
				default:
					$unsent[] = $name;
			}
		}
		if($base && !$out->getTag("minecraft:geometry") instanceof CompoundTag){
			//no model given: a full block, textured from terrain_texture.json under the block's short name
			$out->setTag("minecraft:geometry", self::geometry("minecraft:geometry.full_block"));
			if(!$out->getTag("minecraft:material_instances") instanceof CompoundTag){
				$out->setTag("minecraft:material_instances", self::materialInstances(["*" => ["texture" => explode(":", $identifier, 2)[1], "render_method" => "opaque"]]));
			}
		}
		return $out;
	}

	private static function geometry(mixed $value) : CompoundTag{
		$geometry = is_string($value) ? ["identifier" => $value] : (is_array($value) ? $value : []);
		$bones = CompoundTag::create();
		foreach(is_array($geometry["bone_visibility"] ?? null) ? $geometry["bone_visibility"] : [] as $bone => $visible){
			if(is_bool($visible)){
				$bones->setFloat((string) $bone, $visible ? 1.0 : 0.0);
			}elseif(is_string($visible)){
				//molang: the client evaluates it
				$bones->setTag((string) $bone, CompoundTag::create()->setString("expression", $visible)->setShort("version", 12));
			}
		}
		return CompoundTag::create()
			->setTag("bone_visibility", $bones)
			->setString("culling", is_string($geometry["culling"] ?? null) ? $geometry["culling"] : "")
			->setString("identifier", is_string($geometry["identifier"] ?? null) ? $geometry["identifier"] : "minecraft:geometry.full_block");
	}

	/**
	 * A collision or selection box in JSON: true/false, one {"origin", "size"}, or (1.21.130+) a list of them.
	 *
	 * @return array{enabled: bool, boxes: list<array{0: array{float, float, float}, 1: array{float, float, float}}>}
	 */
	private static function boxes(mixed $value) : array{
		if($value === false){
			return ["enabled" => false, "boxes" => []];
		}
		if(!is_array($value)){
			return ["enabled" => true, "boxes" => [[[-8.0, 0.0, -8.0], [16.0, 16.0, 16.0]]]];
		}
		$list = array_is_list($value) ? $value : [$value];
		$out = [];
		foreach($list as $box){
			if(!is_array($box)){
				continue;
			}
			$origin = is_array($box["origin"] ?? null) ? $box["origin"] : [-8, 0, -8];
			$size = is_array($box["size"] ?? null) ? $box["size"] : [16, 16, 16];
			$out[] = [
				[(float) ($origin[0] ?? -8), (float) ($origin[1] ?? 0), (float) ($origin[2] ?? -8)],
				[(float) ($size[0] ?? 16), (float) ($size[1] ?? 16), (float) ($size[2] ?? 16)],
			];
		}
		return ["enabled" => $out !== [], "boxes" => $out];
	}

	/**
	 * @param array{float, float, float} $origin
	 * @param array{float, float, float} $size
	 */
	private static function originSize(bool $enabled, array $origin, array $size) : CompoundTag{
		return CompoundTag::create()
			->setByte("enabled", $enabled ? 1 : 0)
			->setTag("origin", new ListTag([new FloatTag($origin[0]), new FloatTag($origin[1]), new FloatTag($origin[2])]))
			->setTag("size", new ListTag([new FloatTag($size[0]), new FloatTag($size[1]), new FloatTag($size[2])]));
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
			$ao = $material["ambient_occlusion"] ?? true;
			$tag = CompoundTag::create()
				->setString("texture", is_string($material["texture"] ?? null) ? $material["texture"] : "")
				->setString("render_method", is_string($material["render_method"] ?? null) ? $material["render_method"] : "opaque")
				->setByte("face_dimming", (bool) ($material["face_dimming"] ?? true) ? 1 : 0)
				//1.21.80 JSON allows an occlusion strength; clients read a flag
				->setByte("ambient_occlusion", (is_numeric($ao) ? (float) $ao > 0 : (bool) $ao) ? 1 : 0);
			if(isset($material["isotropic"])){
				$tag->setByte("isotropic", (bool) $material["isotropic"] ? 1 : 0);
			}
			if(is_string($material["tint_method"] ?? null)){
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
			->setInt("RX", ((int) round(((float) ($rotation[0] ?? 0)) / 90) % 4 + 4) % 4)
			->setInt("RY", ((int) round(((float) ($rotation[1] ?? 0)) / 90) % 4 + 4) % 4)
			->setInt("RZ", ((int) round(((float) ($rotation[2] ?? 0)) / 90) % 4 + 4) % 4)
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
