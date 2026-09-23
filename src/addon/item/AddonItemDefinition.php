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

use pocketmine\addon\AddonException;
use pocketmine\addon\AddonJson;
use pocketmine\inventory\CreativeCategory;
use pocketmine\nbt\tag\CompoundTag;
use function array_is_list;
use function explode;
use function is_array;
use function is_numeric;
use function is_string;
use function max;
use function str_replace;
use function ucwords;

/**
 * A custom item read from a behavior pack's items/*.json ("minecraft:item").
 */
final class AddonItemDefinition{
	public const KIND_ITEM = "item";
	public const KIND_TOOL = "tool";
	public const KIND_FOOD = "food";
	public const KIND_ARMOR = "armor";

	/**
	 * @param mixed[] $components raw "components" object from the JSON
	 */
	private function __construct(
		private string $identifier,
		private string $displayName,
		private array $components,
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
		$item = $json["minecraft:item"] ?? null;
		if(!is_array($item)){
			throw new AddonException("$source: not a minecraft:item definition");
		}
		$description = is_array($item["description"] ?? null) ? $item["description"] : [];
		$identifier = $description["identifier"] ?? null;
		if(!is_string($identifier) || !AddonJson::isValidIdentifier($identifier)){
			throw new AddonException("$source: missing or invalid identifier");
		}
		if(str_starts_with($identifier, "minecraft:")){
			throw new AddonException("$source: $identifier overrides a vanilla item, which is not supported", AddonException::VANILLA_OVERRIDE);
		}
		$components = is_array($item["components"] ?? null) && !array_is_list($item["components"]) ? $item["components"] : [];

		$menu = is_array($description["menu_category"] ?? null) ? $description["menu_category"] : [];
		$categoryName = is_string($menu["category"] ?? null) ? $menu["category"] : (is_string($description["category"] ?? null) ? $description["category"] : "items");
		$group = is_string($menu["group"] ?? null) && $menu["group"] !== "" ? $menu["group"] : null;
		$hidden = (bool) ($menu["is_hidden_in_commands"] ?? false);

		$displayName = AddonJson::scalar($components["minecraft:display_name"] ?? null);
		if(!is_string($displayName) || $displayName === ""){
			$displayName = ucwords(str_replace("_", " ", explode(":", $identifier, 2)[1]));
		}

		return new self($identifier, $displayName, $components, self::category($categoryName), $group, $hidden, $packName);
	}

	public static function category(string $name) : CreativeCategory{
		return match(strtolower($name)){
			"construction" => CreativeCategory::CONSTRUCTION,
			"nature" => CreativeCategory::NATURE,
			"equipment" => CreativeCategory::EQUIPMENT,
			default => CreativeCategory::ITEMS,
		};
	}

	public static function categoryId(CreativeCategory $category) : int{
		return match($category){
			CreativeCategory::CONSTRUCTION => 1,
			CreativeCategory::NATURE => 2,
			CreativeCategory::EQUIPMENT => 3,
			CreativeCategory::ITEMS => 4,
		};
	}

	public function getIdentifier() : string{ return $this->identifier; }

	public function getDisplayName() : string{ return $this->displayName; }

	/** @return mixed[] */
	public function getComponents() : array{ return $this->components; }

	public function hasComponent(string $name) : bool{ return isset($this->components[$name]); }

	public function getComponent(string $name) : mixed{ return $this->components[$name] ?? null; }

	public function getCategory() : CreativeCategory{ return $this->category; }

	public function getGroup() : ?string{ return $this->group; }

	public function isHiddenInCommands() : bool{ return $this->hiddenInCommands; }

	public function getPackName() : string{ return $this->packName; }

	public function getMaxStackSize() : int{
		if($this->getMaxDurability() > 0 || $this->getArmorSlot() !== null){
			return 1;
		}
		$value = AddonJson::scalar($this->components["minecraft:max_stack_size"] ?? null);
		return is_numeric($value) ? max(1, min(64, (int) $value)) : 64;
	}

	public function getMaxDurability() : int{
		$durability = $this->components["minecraft:durability"] ?? null;
		$value = is_array($durability) ? ($durability["max_durability"] ?? null) : $durability;
		return is_numeric($value) ? max(0, (int) $value) : 0;
	}

	public function getAttackDamage() : int{
		$value = AddonJson::scalar($this->components["minecraft:damage"] ?? null);
		return is_numeric($value) ? max(0, (int) $value) : 0;
	}

	/** @return array{nutrition: int, saturation: float, canAlwaysEat: bool}|null */
	public function getFood() : ?array{
		$food = $this->components["minecraft:food"] ?? null;
		if(!is_array($food)){
			return null;
		}
		$nutrition = (int) ($food["nutrition"] ?? 0);
		$modifier = $food["saturation_modifier"] ?? 0.6;
		//JSON may name the modifier ("low", "normal", ...) or give it as a number
		$saturationModifier = is_numeric($modifier) ? (float) $modifier : match($modifier){
			"poor" => 0.1, "low" => 0.3, "good" => 0.8, "max" => 1.0, "supernatural" => 1.2, default => 0.6,
		};
		return [
			"nutrition" => $nutrition,
			"saturation" => $nutrition * $saturationModifier * 2.0,
			"canAlwaysEat" => (bool) ($food["can_always_eat"] ?? false),
		];
	}

	/** @return string|null one of slot.armor.head / chest / legs / feet */
	public function getArmorSlot() : ?string{
		$wearable = $this->components["minecraft:wearable"] ?? null;
		$slot = is_array($wearable) ? ($wearable["slot"] ?? null) : null;
		return is_string($slot) && in_array($slot, ["slot.armor.head", "slot.armor.chest", "slot.armor.legs", "slot.armor.feet"], true) ? $slot : null;
	}

	public function getArmorProtection() : int{
		$wearable = $this->components["minecraft:wearable"] ?? null;
		return is_array($wearable) && is_numeric($wearable["protection"] ?? null) ? (int) $wearable["protection"] : 0;
	}

	/** @return array{category: string, ticks: int}|null */
	public function getCooldown() : ?array{
		$cooldown = $this->components["minecraft:cooldown"] ?? null;
		if(!is_array($cooldown) || !is_numeric($cooldown["duration"] ?? null)){
			return null;
		}
		return [
			"category" => is_string($cooldown["category"] ?? null) ? $cooldown["category"] : $this->identifier,
			"ticks" => (int) round((float) $cooldown["duration"] * 20),
		];
	}

	public function getKind() : string{
		if($this->getArmorSlot() !== null){
			return self::KIND_ARMOR;
		}
		if($this->getFood() !== null){
			return self::KIND_FOOD;
		}
		if($this->getMaxDurability() > 0){
			return self::KIND_TOOL;
		}
		return self::KIND_ITEM;
	}

	public function getIconTexture() : string{
		$icon = $this->components["minecraft:icon"] ?? null;
		if(is_string($icon)){
			return $icon;
		}
		if(is_array($icon)){
			if(is_string($icon["texture"] ?? null)){
				return $icon["texture"];
			}
			if(is_array($icon["textures"] ?? null) && is_string($icon["textures"]["default"] ?? null)){
				return $icon["textures"]["default"];
			}
		}
		return explode(":", $this->identifier, 2)[1];
	}

	/**
	 * The item's entry for the item registry (ItemRegistryPacket / ItemComponentPacket): the client reads the
	 * item's behaviour and appearance from this, since it never sees the behavior pack itself.
	 */
	public function buildNetworkNbt(int $runtimeId) : CompoundTag{
		$properties = CompoundTag::create()
			->setInt("max_stack_size", $this->getMaxStackSize())
			->setTag("minecraft:icon", CompoundTag::create()->setTag("textures", CompoundTag::create()->setString("default", $this->getIconTexture())))
			->setInt("creative_category", self::categoryId($this->category))
			->setString("creative_group", $this->group ?? "")
			->setByte("hand_equipped", (bool) AddonJson::scalar($this->components["minecraft:hand_equipped"] ?? ($this->getKind() === self::KIND_TOOL)) ? 1 : 0)
			->setByte("allow_off_hand", (bool) AddonJson::scalar($this->components["minecraft:allow_off_hand"] ?? false) ? 1 : 0)
			->setByte("foil", (bool) AddonJson::scalar($this->components["minecraft:glint"] ?? false) ? 1 : 0)
			->setByte("can_destroy_in_creative", (bool) AddonJson::scalar($this->components["minecraft:can_destroy_in_creative"] ?? true) ? 1 : 0);

		$damage = $this->getAttackDamage();
		if($damage > 0){
			$properties->setInt("damage", $damage);
		}
		$enchantable = $this->components["minecraft:enchantable"] ?? null;
		if(is_array($enchantable)){
			$properties->setString("enchantable_slot", (string) ($enchantable["slot"] ?? "all"));
			$properties->setInt("enchantable_value", (int) ($enchantable["value"] ?? 1));
		}
		$useModifiers = $this->components["minecraft:use_modifiers"] ?? null;
		$food = $this->getFood();
		if(is_array($useModifiers) && is_numeric($useModifiers["use_duration"] ?? null)){
			$properties->setInt("use_duration", (int) round((float) $useModifiers["use_duration"] * 20));
		}elseif($food !== null){
			$properties->setInt("use_duration", 32);
		}
		$useAnimation = AddonJson::scalar($this->components["minecraft:use_animation"] ?? ($food !== null ? "eat" : null));
		if(is_string($useAnimation)){
			$properties->setInt("use_animation", match($useAnimation){ "eat" => 1, "drink" => 2, "bow" => 4, "block" => 5, "spear" => 6, default => 0 });
		}

		$components = CompoundTag::create()->setTag("item_properties", $properties);
		foreach($this->components as $name => $value){
			$name = (string) $name;
			if($name === "minecraft:icon"){
				continue; //sent inside item_properties
			}
			//components that are a bare value in JSON are {"value": x} on the network
			if(!is_array($value) || array_is_list($value)){
				$value = ["value" => $value];
			}
			$components->setTag($name, AddonJson::toTag($value));
		}
		if(!$components->getTag("minecraft:display_name") instanceof CompoundTag){
			$components->setTag("minecraft:display_name", CompoundTag::create()->setString("value", $this->displayName));
		}

		return CompoundTag::create()
			->setTag("components", $components)
			->setInt("id", $runtimeId)
			->setString("name", $this->identifier);
	}
}
