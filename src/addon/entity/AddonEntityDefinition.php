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

use pocketmine\addon\AddonException;
use pocketmine\addon\AddonJson;
use pocketmine\nbt\tag\CompoundTag;
use function array_is_list;
use function explode;
use function is_array;
use function is_bool;
use function is_numeric;
use function is_string;
use function max;
use function min;
use function mt_rand;
use function str_replace;
use function str_starts_with;
use function ucwords;

/**
 * A custom entity read from a behavior pack's entities/*.json ("minecraft:entity").
 *
 * Bedrock entities are data driven: the base "components" are always on, "component_groups" are switched on
 * and off by "events", and those events are fired by the engine (minecraft:entity_spawned...), by sensors and
 * timers, and by other events. AddonEntity runs that model; this class holds the definition.
 */
final class AddonEntityDefinition{
	/**
	 * @param mixed[]                $components      base components
	 * @param array<string, mixed[]> $componentGroups name => components
	 * @param array<string, mixed[]> $events          name => event
	 * @param array<string, mixed[]> $properties      name => property definition (description.properties)
	 */
	private function __construct(
		private string $identifier,
		private string $displayName,
		private array $components,
		private array $componentGroups,
		private array $events,
		private array $properties,
		private bool $spawnable,
		private bool $summonable,
		private bool $experimental,
		private string $packName
	){}

	/**
	 * @param mixed[] $json decoded file
	 * @throws AddonException
	 */
	public static function fromJson(array $json, string $source, string $packName) : self{
		$entity = $json["minecraft:entity"] ?? null;
		if(!is_array($entity)){
			throw new AddonException("$source: not a minecraft:entity definition");
		}
		$description = is_array($entity["description"] ?? null) ? $entity["description"] : [];
		$identifier = $description["identifier"] ?? null;
		if(!is_string($identifier) || !AddonJson::isValidIdentifier($identifier)){
			throw new AddonException("$source: missing or invalid identifier");
		}
		if(str_starts_with($identifier, "minecraft:")){
			throw new AddonException("$source: $identifier overrides a vanilla entity, which is not supported", AddonException::VANILLA_OVERRIDE);
		}
		$components = self::map($entity["components"] ?? null);
		$groups = [];
		foreach(self::map($entity["component_groups"] ?? null) as $name => $group){
			$groups[(string) $name] = self::map($group);
		}
		$events = [];
		foreach(self::map($entity["events"] ?? null) as $name => $event){
			if(is_array($event)){
				$events[(string) $name] = $event;
			}
		}
		$properties = [];
		foreach(self::map($description["properties"] ?? null) as $name => $property){
			if(is_array($property)){
				$properties[(string) $name] = $property;
			}
		}
		$name = AddonJson::scalar($components["minecraft:nameable"] ?? null, "name");
		return new self(
			$identifier,
			is_string($name) && $name !== "" ? $name : ucwords(str_replace("_", " ", explode(":", $identifier, 2)[1])),
			$components,
			$groups,
			$events,
			$properties,
			(bool) ($description["is_spawnable"] ?? false),
			(bool) ($description["is_summonable"] ?? true),
			(bool) ($description["is_experimental"] ?? false),
			$packName
		);
	}

	/** @return mixed[] */
	private static function map(mixed $value) : array{
		return is_array($value) && !array_is_list($value) ? $value : [];
	}

	public function getIdentifier() : string{ return $this->identifier; }

	public function getDisplayName() : string{ return $this->displayName; }

	/** @return mixed[] the base components (always active) */
	public function getComponents() : array{ return $this->components; }

	/** @return array<string, mixed[]> */
	public function getComponentGroups() : array{ return $this->componentGroups; }

	/** @return mixed[]|null */
	public function getEvent(string $name) : ?array{ return $this->events[$name] ?? null; }

	public function hasEvent(string $name) : bool{ return isset($this->events[$name]); }

	/** @return array<string, mixed[]> */
	public function getProperties() : array{ return $this->properties; }

	public function getPackName() : string{ return $this->packName; }

	public function isSpawnable() : bool{ return $this->spawnable; }

	public function isSummonable() : bool{ return $this->summonable; }

	/**
	 * The components in effect with the given groups on: the base components, then each group in the order
	 * it was added (a later group's component replaces an earlier one's, as in the game).
	 *
	 * @param list<string> $activeGroups
	 * @return mixed[]
	 */
	public function resolveComponents(array $activeGroups) : array{
		$components = $this->components;
		foreach($activeGroups as $group){
			foreach($this->componentGroups[$group] ?? [] as $name => $value){
				$components[$name] = $value;
			}
		}
		return $components;
	}

	/** Default value of every declared entity property. @return array<string, int|float|bool|string> */
	public function getDefaultProperties() : array{
		$values = [];
		foreach($this->properties as $name => $property){
			$default = $property["default"] ?? null;
			$type = (string) ($property["type"] ?? "int");
			$values[$name] = match($type){
				"bool" => is_bool($default) ? $default : false,
				"float" => is_numeric($default) ? (float) $default : (float) (($property["range"] ?? [0])[0] ?? 0),
				"enum" => is_string($default) ? $default : (string) (($property["values"] ?? [""])[0] ?? ""),
				default => is_numeric($default) ? (int) $default : (int) (($property["range"] ?? [0])[0] ?? 0),
			};
		}
		return $values;
	}

	/** Max health from the base components. */
	public function getMaxHealth() : int{ return self::maxHealth($this->components); }

	/** Size from the base components. @return array{width: float, height: float} */
	public function getSize() : array{ return self::size($this->components); }

	public function hasGravity() : bool{ return self::gravityOf($this->components); }

	public function isFireImmune() : bool{ return isset($this->components["minecraft:fire_immune"]); }

	public function getKnockbackResistance() : float{ return self::knockbackResistance($this->components); }

	// ------------------------------------------------------------------ component readers (active set)

	/** @param mixed[] $components */
	public static function maxHealth(array $components) : int{
		$health = $components["minecraft:health"] ?? null;
		$value = is_array($health) ? ($health["max"] ?? $health["value"] ?? null) : $health;
		if(is_array($value)){
			$value = $value["range_max"] ?? $value["range_min"] ?? null;
		}
		return is_numeric($value) ? max(1, (int) $value) : 20;
	}

	/** @param mixed[] $components */
	public static function startHealth(array $components) : int{
		$health = $components["minecraft:health"] ?? null;
		$value = is_array($health) ? ($health["value"] ?? $health["max"] ?? null) : $health;
		if(is_array($value)){
			$min = (int) ($value["range_min"] ?? 1);
			$max = (int) ($value["range_max"] ?? $min);
			return mt_rand(min($min, $max), max($min, $max));
		}
		return is_numeric($value) ? max(1, (int) $value) : self::maxHealth($components);
	}

	/**
	 * @param mixed[] $components
	 * @return array{width: float, height: float}
	 */
	public static function size(array $components) : array{
		$box = $components["minecraft:collision_box"] ?? null;
		$scale = self::scale($components);
		return [
			"width" => max(0.01, (is_array($box) && is_numeric($box["width"] ?? null) ? (float) $box["width"] : 0.6) * $scale),
			"height" => max(0.01, (is_array($box) && is_numeric($box["height"] ?? null) ? (float) $box["height"] : 1.8) * $scale),
		];
	}

	/** @param mixed[] $components */
	public static function scale(array $components) : float{
		$scale = AddonJson::scalar($components["minecraft:scale"] ?? null);
		return is_numeric($scale) ? (float) $scale : 1.0;
	}

	/** @param mixed[] $components */
	public static function gravityOf(array $components) : bool{
		$physics = $components["minecraft:physics"] ?? null;
		return !is_array($physics) || (bool) ($physics["has_gravity"] ?? true);
	}

	/** @param mixed[] $components */
	public static function knockbackResistance(array $components) : float{
		$value = AddonJson::scalar($components["minecraft:knockback_resistance"] ?? null);
		return is_numeric($value) ? (float) $value : 0.0;
	}

	/** @param mixed[] $components movement speed in blocks per tick at speed multiplier 1 */
	public static function movementSpeed(array $components) : float{
		$value = AddonJson::scalar($components["minecraft:movement"] ?? null);
		//Bedrock's movement attribute (0.25 for most mobs) walks about half its value in blocks per tick
		return is_numeric($value) ? (float) $value * 0.5 : 0.0;
	}

	/** @param mixed[] $components @return list<string> */
	public static function families(array $components) : array{
		$family = $components["minecraft:type_family"] ?? null;
		$list = is_array($family) ? ($family["family"] ?? []) : [];
		$out = [];
		foreach(is_array($list) ? $list : [] as $name){
			if(is_string($name)){
				$out[] = $name;
			}
		}
		return $out;
	}

	/**
	 * This entity's entry in AvailableActorIdentifiersPacket, which tells the client the identifier exists.
	 */
	public function buildIdentifierNbt(int $runtimeId) : CompoundTag{
		return CompoundTag::create()
			->setString("bid", "")
			->setByte("experimental", $this->experimental ? 1 : 0)
			->setByte("hasspawnegg", $this->spawnable ? 1 : 0)
			->setString("id", $this->identifier)
			->setInt("rid", $runtimeId)
			->setByte("summonable", $this->summonable ? 1 : 0);
	}
}
