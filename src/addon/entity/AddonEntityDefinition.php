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
use function is_numeric;
use function is_string;
use function max;
use function str_replace;
use function ucwords;

/**
 * A custom entity read from a behavior pack's entities/*.json ("minecraft:entity").
 *
 * The client draws it from the resource pack's client entity definition; the server knows its identifier,
 * size, health and a few physical components. Behaviour JSON (AI goals, component groups, events) is not
 * executed - plugins drive add-on entities through AddonEntity.
 */
final class AddonEntityDefinition{
	/**
	 * @param mixed[] $components
	 */
	private function __construct(
		private string $identifier,
		private string $displayName,
		private array $components,
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
		$components = is_array($entity["components"] ?? null) && !array_is_list($entity["components"]) ? $entity["components"] : [];
		$name = AddonJson::scalar($components["minecraft:nameable"] ?? null, "name");
		return new self(
			$identifier,
			is_string($name) && $name !== "" ? $name : ucwords(str_replace("_", " ", explode(":", $identifier, 2)[1])),
			$components,
			(bool) ($description["is_spawnable"] ?? false),
			(bool) ($description["is_summonable"] ?? true),
			(bool) ($description["is_experimental"] ?? false),
			$packName
		);
	}

	public function getIdentifier() : string{ return $this->identifier; }

	public function getDisplayName() : string{ return $this->displayName; }

	/** @return mixed[] */
	public function getComponents() : array{ return $this->components; }

	public function getPackName() : string{ return $this->packName; }

	public function isSpawnable() : bool{ return $this->spawnable; }

	public function isSummonable() : bool{ return $this->summonable; }

	public function getMaxHealth() : int{
		$health = $this->components["minecraft:health"] ?? null;
		$value = is_array($health) ? ($health["max"] ?? $health["value"] ?? null) : $health;
		if(is_array($value)){
			$value = $value["range_max"] ?? $value["range_min"] ?? null;
		}
		return is_numeric($value) ? max(1, (int) $value) : 20;
	}

	/** @return array{width: float, height: float} */
	public function getSize() : array{
		$box = $this->components["minecraft:collision_box"] ?? null;
		$scale = AddonJson::scalar($this->components["minecraft:scale"] ?? null);
		$scale = is_numeric($scale) ? (float) $scale : 1.0;
		return [
			"width" => (is_array($box) && is_numeric($box["width"] ?? null) ? (float) $box["width"] : 0.6) * $scale,
			"height" => (is_array($box) && is_numeric($box["height"] ?? null) ? (float) $box["height"] : 1.8) * $scale,
		];
	}

	public function hasGravity() : bool{
		$physics = $this->components["minecraft:physics"] ?? null;
		return !is_array($physics) || (bool) ($physics["has_gravity"] ?? true);
	}

	public function isFireImmune() : bool{
		return isset($this->components["minecraft:fire_immune"]);
	}

	public function getKnockbackResistance() : float{
		$value = AddonJson::scalar($this->components["minecraft:knockback_resistance"] ?? null);
		return is_numeric($value) ? (float) $value : 0.0;
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
