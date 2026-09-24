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

namespace pocketmine\addon\entity\ai;

use pocketmine\addon\AddonJson;
use pocketmine\addon\entity\AddonEntityDefinition;
use function is_array;
use function is_numeric;

/**
 * How a mob moves, read from its movement.* and navigation.* components.
 */
final class MovementProfile{
	public function __construct(
		public readonly float $speed,
		public readonly bool $direct,
		public readonly bool $canSwim,
		public readonly bool $avoidWater,
		public readonly int $maxFall,
		public readonly float $range,
		public readonly bool $flying
	){}

	/** @param mixed[] $components */
	public static function fromComponents(array $components) : self{
		$navigation = null;
		$navigationType = "walk";
		foreach(["walk", "generic", "climb", "float", "fly", "hover", "swim"] as $type){
			if(isset($components["minecraft:navigation.$type"])){
				$navigation = $components["minecraft:navigation.$type"];
				$navigationType = $type;
				break;
			}
		}
		$navigation = is_array($navigation) ? $navigation : [];
		$flying = $navigationType === "fly" || $navigationType === "hover" || isset($components["minecraft:movement.fly"])
			|| isset($components["minecraft:movement.hover"]) || isset($components["minecraft:can_fly"]);
		$swimmer = $navigationType === "swim" || isset($components["minecraft:movement.sway"]) || isset($components["minecraft:underwater_movement"]) && !isset($components["minecraft:navigation.walk"]);

		$speed = AddonEntityDefinition::movementSpeed($components);
		if($flying){
			$fly = AddonJson::scalar($components["minecraft:flying_speed"] ?? null);
			$speed = is_numeric($fly) ? (float) $fly * 2.5 : ($speed > 0 ? $speed : 0.1);
		}elseif($swimmer){
			$water = AddonJson::scalar($components["minecraft:underwater_movement"] ?? null);
			$speed = is_numeric($water) ? (float) $water * 2.5 : ($speed > 0 ? $speed : 0.1);
		}
		$jump = $components["minecraft:jump.static"] ?? null;
		return new self(
			$speed,
			$flying || $swimmer,
			(bool) ($navigation["can_swim"] ?? $navigation["can_float"] ?? $swimmer),
			(bool) ($navigation["avoid_water"] ?? false),
			is_array($components["minecraft:behavior.random_stroll"] ?? null) || $jump !== null ? 3 : 2,
			is_numeric($navigation["search_range"] ?? null) ? (float) $navigation["search_range"] : 24.0,
			$flying
		);
	}
}
