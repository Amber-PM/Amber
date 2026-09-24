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

namespace pocketmine\addon\item;

use pocketmine\nbt\NBT;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\nbt\tag\Tag;
use function array_is_list;
use function is_array;
use function is_bool;
use function is_numeric;
use function is_string;
use function max;
use function min;
use function round;

/**
 * Turns behavior-pack item components (any JSON shape used from format 1.21.0 to the latest) into the exact
 * network form the client reads from the item registry.
 *
 * JSON leaves types loose: `"damage": 5` and `"damage": {"value": 5}` are both valid, and `1.0` versus `1` picks
 * float or int. The client does not accept that looseness on the wire, so every component has a fixed network
 * shape with fixed tag types here. Shapes follow CustomiesDevs/Customies (MIT) and df-mc/dragonfly, both
 * tested against real clients.
 *
 * Components that only matter server-side (tags, repairable, custom script components...) and components this
 * codec does not know are not sent: an unknown component with a wrong type can make the client reject the item.
 * They are reported instead (see AddonItemDefinition::getUnsentComponents()).
 */
final class ItemComponentCodec{
	/** Read by the server only; never needed by the client. */
	public const SERVER_ONLY = [
		"minecraft:tags", "minecraft:repairable", "minecraft:custom_components", "minecraft:entity_placer",
		"minecraft:compostable", "minecraft:storage_item", "minecraft:storage_weight_limit", "minecraft:storage_weight_modifier",
		"minecraft:max_damage", "minecraft:mining_speed", "minecraft:fire_resistant",
	];

	/**
	 * Adds one JSON component to the network NBT. Returns false when the component is not sent.
	 *
	 * @param mixed $value the component exactly as decoded from JSON
	 */
	public static function encode(string $name, mixed $value, CompoundTag $components, CompoundTag $properties) : bool{
		switch($name){
			// ---- item_properties (flat values)
			case "minecraft:max_stack_size":
				$properties->setInt("max_stack_size", max(1, min(64, self::int($value, 64))));
				return true;
			case "minecraft:hand_equipped":
				$properties->setByte("hand_equipped", self::bool($value) ? 1 : 0);
				return true;
			case "minecraft:glint":
			case "minecraft:foil":
				$properties->setByte("foil", self::bool($value) ? 1 : 0);
				return true;
			case "minecraft:allow_off_hand":
				$properties->setByte("allow_off_hand", self::bool($value) ? 1 : 0);
				return true;
			case "minecraft:can_destroy_in_creative":
				$properties->setByte("can_destroy_in_creative", self::bool($value) ? 1 : 0);
				return true;
			case "minecraft:liquid_clipped":
				$properties->setByte("liquid_clipped", self::bool($value) ? 1 : 0);
				return true;
			case "minecraft:should_despawn":
				$properties->setByte("should_despawn", self::bool($value) ? 1 : 0);
				return true;
			case "minecraft:stacked_by_data":
				$properties->setByte("stacked_by_data", self::bool($value) ? 1 : 0);
				return true;
			case "minecraft:damage":
				$properties->setInt("damage", max(0, self::int($value, 0)));
				return true;
			case "minecraft:hover_text_color":
				$properties->setString("hover_text_color", self::string($value, ""));
				return true;
			case "minecraft:enchantable":
				$value = is_array($value) ? $value : [];
				$properties->setString("enchantable_slot", self::string($value["slot"] ?? null, "all"));
				$properties->setInt("enchantable_value", self::int($value["value"] ?? null, 1));
				return true;
			case "minecraft:use_animation":
				$properties->setInt("use_animation", self::animation(self::string($value, "none")));
				return true;
			case "minecraft:icon":
				//sent by AddonItemDefinition, which also covers the no-icon case
				return true;

			// ---- components (compounds)
			case "minecraft:display_name":
				$components->setTag($name, CompoundTag::create()->setString("value", self::string($value, "")));
				return true;
			case "minecraft:durability":
				$value = is_array($value) ? $value : ["max_durability" => $value];
				$chance = is_array($value["damage_chance"] ?? null) ? $value["damage_chance"] : [];
				$components->setTag($name, CompoundTag::create()
					->setTag("damage_chance", CompoundTag::create()
						->setInt("min", self::int($chance["min"] ?? null, 100))
						->setInt("max", self::int($chance["max"] ?? null, 100)))
					->setInt("max_durability", max(0, self::int($value["max_durability"] ?? null, 0))));
				return true;
			case "minecraft:food":
				$value = is_array($value) ? $value : [];
				$converts = $value["using_converts_to"] ?? "";
				$components->setTag($name, CompoundTag::create()
					->setByte("can_always_eat", self::bool($value["can_always_eat"] ?? false) ? 1 : 0)
					->setInt("nutrition", self::int($value["nutrition"] ?? null, 0))
					->setFloat("saturation_modifier", self::saturation($value["saturation_modifier"] ?? 0.6))
					->setTag("using_converts_to", CompoundTag::create()->setString("name", is_array($converts) ? self::string($converts["item"] ?? $converts["name"] ?? null, "") : self::string($converts, ""))));
				return true;
			case "minecraft:use_modifiers":
				$value = is_array($value) ? $value : [];
				$components->setTag($name, CompoundTag::create()
					->setFloat("movement_modifier", self::float($value["movement_modifier"] ?? null, 1.0))
					->setFloat("use_duration", self::float($value["use_duration"] ?? null, 0.0)));
				//older clients read the use time from item_properties, in ticks
				$properties->setInt("use_duration", (int) round(self::float($value["use_duration"] ?? null, 0.0) * 20));
				return true;
			case "minecraft:wearable":
				$value = is_array($value) ? $value : [];
				$components->setTag($name, CompoundTag::create()
					->setString("slot", self::string($value["slot"] ?? null, "slot.armor"))
					->setInt("protection", self::int($value["protection"] ?? null, 0))
					->setByte("dispensable", self::bool($value["dispensable"] ?? true) ? 1 : 0));
				return true;
			case "minecraft:cooldown":
				$value = is_array($value) ? $value : [];
				$components->setTag($name, CompoundTag::create()
					->setString("category", self::string($value["category"] ?? null, ""))
					->setFloat("duration", self::float($value["duration"] ?? null, 0.0)));
				return true;
			case "minecraft:fuel":
				$components->setTag($name, CompoundTag::create()->setFloat("duration", self::float(is_array($value) ? ($value["duration"] ?? null) : $value, 0.0)));
				return true;
			case "minecraft:rarity":
				$components->setTag($name, CompoundTag::create()->setString("value", self::string($value, "common")));
				return true;
			case "minecraft:dyeable":
				$value = is_array($value) ? $value : [];
				$components->setTag($name, CompoundTag::create()->setString("default_color", self::string($value["default_color"] ?? null, "")));
				return true;
			case "minecraft:interact_button":
				$text = is_string($value) ? $value : (is_array($value) ? self::string($value["interact_text"] ?? $value["value"] ?? null, "") : "");
				$components->setTag($name, CompoundTag::create()->setString("interact_text", $text)->setByte("requires_interact", 1));
				return true;
			case "minecraft:throwable":
				$value = is_array($value) ? $value : [];
				$components->setTag($name, CompoundTag::create()
					->setByte("do_swing_animation", self::bool($value["do_swing_animation"] ?? false) ? 1 : 0)
					->setFloat("launch_power_scale", self::float($value["launch_power_scale"] ?? null, 1.0))
					->setFloat("max_draw_duration", self::float($value["max_draw_duration"] ?? null, 0.0))
					->setFloat("max_launch_power", self::float($value["max_launch_power"] ?? null, 1.0))
					->setFloat("min_draw_duration", self::float($value["min_draw_duration"] ?? null, 0.0))
					->setByte("scale_power_by_draw_duration", self::bool($value["scale_power_by_draw_duration"] ?? false) ? 1 : 0));
				return true;
			case "minecraft:projectile":
				$value = is_array($value) ? $value : [];
				$components->setTag($name, CompoundTag::create()
					->setFloat("minimum_critical_power", self::float($value["minimum_critical_power"] ?? null, 1.0))
					->setString("projectile_entity", self::string($value["projectile_entity"] ?? null, "")));
				return true;
			case "minecraft:shooter":
				$value = is_array($value) ? $value : [];
				$ammo = [];
				foreach(is_array($value["ammunition"] ?? null) ? $value["ammunition"] : [] as $entry){
					if(!is_array($entry)){
						continue;
					}
					$ammo[] = CompoundTag::create()
						->setString("item", self::string($entry["item"] ?? null, ""))
						->setByte("use_offhand", self::bool($entry["use_offhand"] ?? false) ? 1 : 0)
						->setByte("search_inventory", self::bool($entry["search_inventory"] ?? false) ? 1 : 0)
						->setByte("use_in_creative", self::bool($entry["use_in_creative"] ?? false) ? 1 : 0);
				}
				$components->setTag($name, CompoundTag::create()
					->setTag("ammunition", new ListTag($ammo))
					->setByte("charge_on_draw", self::bool($value["charge_on_draw"] ?? false) ? 1 : 0)
					->setFloat("max_draw_duration", self::float($value["max_draw_duration"] ?? null, 0.0))
					->setByte("scale_power_by_draw_duration", self::bool($value["scale_power_by_draw_duration"] ?? false) ? 1 : 0));
				return true;
			case "minecraft:record":
				$value = is_array($value) ? $value : [];
				$components->setTag($name, CompoundTag::create()
					->setInt("comparator_signal", self::int($value["comparator_signal"] ?? null, 1))
					->setFloat("duration", self::float($value["duration"] ?? null, 0.0))
					->setString("sound_event", self::string($value["sound_event"] ?? null, "")));
				return true;
			case "minecraft:bundle_interaction":
				$value = is_array($value) ? $value : [];
				$components->setTag($name, CompoundTag::create()->setInt("num_viewable_slots", self::int($value["num_viewable_slots"] ?? null, 12)));
				return true;
			case "minecraft:damage_absorption":
				$value = is_array($value) ? $value : [];
				$causes = [];
				foreach(is_array($value["absorbable_causes"] ?? null) ? $value["absorbable_causes"] : [] as $cause){
					if(is_string($cause)){
						$causes[] = new StringTag($cause);
					}
				}
				$components->setTag($name, CompoundTag::create()->setTag("absorbable_causes", new ListTag($causes, $causes === [] ? NBT::TAG_String : 0)));
				return true;
			case "minecraft:block_placer":
				$value = is_array($value) ? $value : [];
				$block = $value["block"] ?? "";
				$components->setTag($name, CompoundTag::create()
					->setString("block", is_array($block) ? self::string($block["name"] ?? null, "") : self::string($block, ""))
					->setTag("use_on", self::blockList($value["use_on"] ?? [])));
				return true;
			case "minecraft:digger":
				$value = is_array($value) ? $value : [];
				$speeds = [];
				foreach(is_array($value["destroy_speeds"] ?? null) ? $value["destroy_speeds"] : [] as $entry){
					if(!is_array($entry)){
						continue;
					}
					$block = $entry["block"] ?? "";
					$blockTag = CompoundTag::create();
					if(is_string($block)){
						$blockTag->setString("name", $block);
					}elseif(is_array($block)){
						if(is_string($block["name"] ?? null)){
							$blockTag->setString("name", $block["name"]);
						}
						if(is_string($block["tags"] ?? null)){
							$blockTag->setString("tags", $block["tags"]);
						}
					}
					$speeds[] = CompoundTag::create()->setTag("block", $blockTag)->setInt("speed", self::int($entry["speed"] ?? null, 1));
				}
				$components->setTag($name, CompoundTag::create()
					->setByte("use_efficiency", self::bool($value["use_efficiency"] ?? false) ? 1 : 0)
					->setTag("destroy_speeds", new ListTag($speeds, $speeds === [] ? NBT::TAG_Compound : 0)));
				return true;
		}
		return false;
	}

	/** "value" wrapper used by many 1.21 components: {"value": x} and a bare x mean the same. */
	private static function unwrap(mixed $value) : mixed{
		return is_array($value) && !array_is_list($value) && isset($value["value"]) ? $value["value"] : $value;
	}

	private static function bool(mixed $value) : bool{
		$value = self::unwrap($value);
		return is_bool($value) ? $value : (is_numeric($value) ? (float) $value !== 0.0 : (bool) $value);
	}

	private static function int(mixed $value, int $default) : int{
		$value = self::unwrap($value);
		return is_numeric($value) ? (int) round((float) $value) : $default;
	}

	private static function float(mixed $value, float $default) : float{
		$value = self::unwrap($value);
		return is_numeric($value) ? (float) $value : $default;
	}

	private static function string(mixed $value, string $default) : string{
		$value = self::unwrap($value);
		return is_string($value) ? $value : $default;
	}

	/** Food saturation: a number, or one of the named levels older packs use. */
	private static function saturation(mixed $value) : float{
		if(is_numeric($value)){
			return (float) $value;
		}
		return match(is_string($value) ? $value : ""){
			"poor" => 0.1, "low" => 0.3, "good" => 0.8, "max" => 1.0, "supernatural" => 1.2, default => 0.6,
		};
	}

	/** Use animations as the client's enum. */
	public static function animation(string $name) : int{
		//the client's ItemUseAnimation values (as in Customies' UseAnimationComponent)
		return match($name){ "eat" => 1, "drink" => 2, "block" => 3, "bow" => 4, "camera" => 5, "spear" => 6, "crossbow" => 9, "spyglass" => 10, "brush" => 12, default => 0 };
	}

	/** @param mixed $list JSON list of block names or {"name": ...} objects */
	private static function blockList(mixed $list) : ListTag{
		$out = [];
		foreach(is_array($list) ? $list : [] as $entry){
			$name = is_array($entry) ? ($entry["name"] ?? null) : $entry;
			if(is_string($name)){
				$out[] = CompoundTag::create()->setString("name", $name);
			}
		}
		return new ListTag($out, $out === [] ? NBT::TAG_Compound : 0);
	}

	/** @internal for tests: the tag class each property must have */
	public static function propertyType(string $key) : string{
		return match($key){
			"max_stack_size", "damage", "enchantable_value", "use_animation", "use_duration", "creative_category" => IntTag::class,
			"hand_equipped", "foil", "allow_off_hand", "can_destroy_in_creative", "liquid_clipped", "should_despawn", "stacked_by_data" => ByteTag::class,
			"hover_text_color", "enchantable_slot", "creative_group" => StringTag::class,
			"minecraft:icon" => CompoundTag::class,
			default => Tag::class,
		};
	}
}
