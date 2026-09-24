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

use pocketmine\block\Lava;
use pocketmine\block\Water;
use pocketmine\entity\effect\StringToEffectParser;
use pocketmine\entity\Entity;
use pocketmine\entity\Living;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\StringToItemParser;
use pocketmine\player\Player;
use pocketmine\world\World;
use function array_is_list;
use function count;
use function floor;
use function in_array;
use function is_array;
use function is_bool;
use function is_numeric;
use function is_string;
use function max;
use function mt_getrandmax;
use function mt_rand;
use function str_contains;
use function str_replace;
use function strtolower;

/**
 * Evaluates Bedrock entity filters ({"test": ..., "subject": ..., "operator": ..., "value": ...} and the
 * all_of / any_of / none_of groups) as used by events, sensors, behaviors and damage sensors.
 *
 * A test this server cannot evaluate is reported once through the callback and treated as false, which is
 * the safe answer for almost every filter (a trigger does not fire rather than firing when it should not).
 */
final class EntityFilter{
	/**
	 * Damage causes by their Bedrock names, for has_damage and the damage sensor.
	 */
	public const DAMAGE_CAUSES = [
		"all" => -1,
		"entity_attack" => EntityDamageEvent::CAUSE_ENTITY_ATTACK,
		"projectile" => EntityDamageEvent::CAUSE_PROJECTILE,
		"suffocation" => EntityDamageEvent::CAUSE_SUFFOCATION,
		"fall" => EntityDamageEvent::CAUSE_FALL,
		"fire" => EntityDamageEvent::CAUSE_FIRE,
		"fire_tick" => EntityDamageEvent::CAUSE_FIRE_TICK,
		"lava" => EntityDamageEvent::CAUSE_LAVA,
		"drowning" => EntityDamageEvent::CAUSE_DROWNING,
		"block_explosion" => EntityDamageEvent::CAUSE_BLOCK_EXPLOSION,
		"entity_explosion" => EntityDamageEvent::CAUSE_ENTITY_EXPLOSION,
		"void" => EntityDamageEvent::CAUSE_VOID,
		"suicide" => EntityDamageEvent::CAUSE_SUICIDE,
		"magic" => EntityDamageEvent::CAUSE_MAGIC,
		"override" => EntityDamageEvent::CAUSE_CUSTOM,
		"starve" => EntityDamageEvent::CAUSE_STARVATION,
		"fall_damage" => EntityDamageEvent::CAUSE_FALL,
	];

	/** Families of vanilla entities, so is_family works on them too. */
	private const VANILLA_FAMILIES = [
		"minecraft:player" => ["player", "mob"],
		"minecraft:zombie" => ["zombie", "undead", "monster", "mob"],
		"minecraft:husk" => ["husk", "zombie", "undead", "monster", "mob"],
		"minecraft:skeleton" => ["skeleton", "undead", "monster", "mob"],
		"minecraft:stray" => ["stray", "skeleton", "undead", "monster", "mob"],
		"minecraft:creeper" => ["creeper", "monster", "mob"],
		"minecraft:spider" => ["spider", "arthropod", "monster", "mob"],
		"minecraft:enderman" => ["enderman", "monster", "mob"],
		"minecraft:villager_v2" => ["villager", "peasant", "mob"],
		"minecraft:squid" => ["squid", "mob"],
		"minecraft:cow" => ["cow", "mob"],
		"minecraft:pig" => ["pig", "mob"],
		"minecraft:sheep" => ["sheep", "mob"],
		"minecraft:chicken" => ["chicken", "mob"],
		"minecraft:wolf" => ["wolf", "mob"],
		"minecraft:iron_golem" => ["irongolem", "mob"],
		"minecraft:item" => ["item"],
		"minecraft:arrow" => ["arrow"],
	];

	/** @var array<string, true> */
	private static array $reported = [];

	private function __construct(){
		//NOOP
	}

	/**
	 * @param mixed                       $filter   a filter object, a group object, or a list (all_of)
	 * @param \Closure(string) : void     $report   told about each unsupported test, once
	 */
	public static function test(mixed $filter, FilterContext $context, ?\Closure $report = null) : bool{
		if(!is_array($filter) || count($filter) === 0){
			return true;
		}
		if(array_is_list($filter)){
			foreach($filter as $child){
				if(!self::test($child, $context, $report)){
					return false;
				}
			}
			return true;
		}
		$result = true;
		$grouped = false;
		if(isset($filter["all_of"])){
			$grouped = true;
			foreach(self::children($filter["all_of"]) as $child){
				if(!self::test($child, $context, $report)){
					return false;
				}
			}
		}
		if(isset($filter["any_of"])){
			$grouped = true;
			$any = false;
			foreach(self::children($filter["any_of"]) as $child){
				if(self::test($child, $context, $report)){
					$any = true;
					break;
				}
			}
			$result = $any;
		}
		if($result && isset($filter["none_of"])){
			$grouped = true;
			foreach(self::children($filter["none_of"]) as $child){
				if(self::test($child, $context, $report)){
					return false;
				}
			}
		}
		if(!$result){
			return false;
		}
		if(isset($filter["test"]) && is_string($filter["test"])){
			return self::single($filter, $context, $report);
		}
		return $grouped || $result;
	}

	/** @return list<mixed> */
	private static function children(mixed $value) : array{
		if(!is_array($value)){
			return [];
		}
		return array_is_list($value) ? $value : [$value];
	}

	/**
	 * @param mixed[] $filter
	 * @param \Closure(string) : void $report
	 */
	private static function single(array $filter, FilterContext $context, ?\Closure $report) : bool{
		$test = (string) $filter["test"];
		$subject = $context->subject(is_string($filter["subject"] ?? null) ? $filter["subject"] : "self");
		$operator = is_string($filter["operator"] ?? null) ? $filter["operator"] : "equals";
		$value = $filter["value"] ?? true;
		$domain = is_string($filter["domain"] ?? null) ? $filter["domain"] : null;

		//tests that do not need a subject
		switch($test){
			case "random_chance":
				$max = is_numeric($value) ? max(1, (int) $value) : 2;
				return self::compare(mt_rand(0, $max - 1) === 0, $operator, true);
			case "is_daytime":
				return self::compare(self::isDay($context->self->getWorld()), $operator, (bool) $value);
			case "hourly_clock_time":
				return self::compare($context->self->getWorld()->getTimeOfDay() % World::TIME_FULL, $operator, $value);
			case "clock_time":
				return self::compare(($context->self->getWorld()->getTimeOfDay() % World::TIME_FULL) / World::TIME_FULL, $operator, $value);
			case "moon_phase":
				return self::compare((int) ($context->self->getWorld()->getTime() / World::TIME_FULL) % 8, $operator, $value);
			case "is_difficulty":
				return self::compare(self::difficultyName($context->self->getWorld()->getDifficulty()), $operator, strtolower((string) $value));
			case "has_damage":
				if($context->damageCause === null){
					return false;
				}
				$cause = self::DAMAGE_CAUSES[strtolower((string) $value)] ?? null;
				$matches = $cause === -1 || $cause === $context->damageCause || ($value === "fatal" && $context->damageFatal);
				return self::compare($matches, $operator, true);
			case "is_weather":
			case "weather":
			case "weather_at_position":
				//PocketMine has no weather
				return self::compare(strtolower((string) $value) === "clear", $operator, true);
		}

		if($subject === null){
			return false;
		}
		$addon = $subject instanceof AddonEntity ? $subject : null;
		$world = $subject->getWorld();
		$pos = $subject->getPosition();

		switch($test){
			case "is_family":
				return self::compare(in_array(strtolower((string) $value), self::familiesOf($subject), true), $operator, true);
			case "has_component":
				return self::compare($addon !== null && $addon->hasComponent((string) $value), $operator, true);
			case "has_property":
				return self::compare($addon !== null && $addon->hasProperty((string) $value), $operator, true);
			case "bool_property":
			case "int_property":
			case "float_property":
			case "enum_property":
				if($addon === null || $domain === null){
					return false;
				}
				$property = $addon->getProperty($domain);
				return $property !== null && self::compare($property, $operator, $test === "bool_property" && !isset($filter["value"]) ? true : $value);
			case "is_variant":
				return self::compare($addon?->getVariant() ?? 0, $operator, $value);
			case "is_mark_variant":
				return self::compare($addon?->getMarkVariant() ?? 0, $operator, $value);
			case "is_skin_id":
				return self::compare($addon?->getSkinId() ?? 0, $operator, $value);
			case "is_color":
				return self::compare($addon?->getColor() ?? 0, $operator, $value);
			case "is_baby":
				return self::compare($addon?->isBaby() ?? false, $operator, (bool) $value);
			case "is_tamed":
				return self::compare($addon?->isTamed() ?? false, $operator, (bool) $value);
			case "is_sitting":
				return self::compare($addon?->isSitting() ?? false, $operator, (bool) $value);
			case "is_sneaking":
				return self::compare($subject instanceof Living && $subject->isSneaking(), $operator, (bool) $value);
			case "is_sprinting":
				return self::compare($subject instanceof Living && $subject->isSprinting(), $operator, (bool) $value);
			case "on_ground":
				return self::compare($subject->isOnGround(), $operator, (bool) $value);
			case "on_fire":
				return self::compare($subject->isOnFire(), $operator, (bool) $value);
			case "is_moving":
				$m = $subject->getMotion();
				return self::compare($m->x * $m->x + $m->z * $m->z > 0.0001, $operator, (bool) $value);
			case "in_water":
			case "is_in_water":
				return self::compare($world->getBlock($pos) instanceof Water, $operator, (bool) $value);
			case "in_water_or_rain":
			case "is_in_water_or_rain":
			case "in_contact_with_water":
			case "is_in_contact_with_water":
				return self::compare($world->getBlock($pos) instanceof Water || $world->getBlock($pos->up()) instanceof Water, $operator, (bool) $value);
			case "is_underwater":
				return self::compare($subject->isUnderwater(), $operator, (bool) $value);
			case "in_lava":
				return self::compare($world->getBlock($pos) instanceof Lava, $operator, (bool) $value);
			case "in_nether":
				return self::compare(str_replace(" ", "_", strtolower($world->getFolderName())) === "nether", $operator, (bool) $value);
			case "is_underground":
				return self::compare($world->getHighestBlockAt((int) floor($pos->x), (int) floor($pos->z)) > (int) floor($pos->y) + 1, $operator, (bool) $value);
			case "is_altitude":
			case "y":
				return self::compare((int) floor($pos->y), $operator, $value);
			case "is_brightness":
				return self::compare($world->getFullLight($pos->floor()) / 15, $operator, $value);
			case "is_biome":
			case "has_biome_tag":
				return self::compare(BiomeTags::has($world->getBiomeId((int) floor($pos->x), (int) floor($pos->y), (int) floor($pos->z)), strtolower((string) $value)), $operator, true);
			case "is_snow_covered":
				return self::compare(BiomeTags::has($world->getBiomeId((int) floor($pos->x), (int) floor($pos->y), (int) floor($pos->z)), "frozen"), $operator, (bool) $value);
			case "actor_health":
				return self::compare($subject->getHealth(), $operator, $value);
			case "has_target":
				return self::compare($subject->getTargetEntity() !== null, $operator, (bool) $value);
			case "is_target":
				return self::compare($context->self->getTargetEntityId() === $subject->getId(), $operator, (bool) $value);
			case "is_owner":
				return self::compare($context->self->getOwningEntityId() === $subject->getId(), $operator, (bool) $value);
			case "is_riding":
			case "is_leashed":
			case "in_caravan":
			case "is_in_village":
			case "is_bound_to_creaking_heart":
				return self::compare(false, $operator, (bool) $value);
			case "rider_count":
				return self::compare(0, $operator, $value);
			case "has_tag":
				return self::compare($addon !== null && $addon->hasTag((string) $value), $operator, true);
			case "has_mob_effect":
				if(!$subject instanceof Living){
					return false;
				}
				$effect = StringToEffectParser::getInstance()->parse((string) $value);
				return self::compare($effect !== null && $subject->getEffects()->has($effect), $operator, true);
			case "has_equipment":
				return self::compare(self::hasEquipment($subject, $filter), $operator, true);
			case "distance_to_nearest_player":
				$nearest = self::nearestPlayer($subject, 64.0);
				return self::compare($nearest === null ? 1000.0 : $nearest->getPosition()->distance($pos), $operator, $value);
			case "target_distance":
				$target = $context->self->getTargetEntity();
				return self::compare($target === null ? 1000.0 : $target->getPosition()->distance($context->self->getPosition()), $operator, $value);
			case "is_visible":
				return self::compare(!$subject->isInvisible(), $operator, (bool) $value);
			case "is_block":
				return self::compare(self::blockName($subject), $operator, self::normalize((string) $value));
			case "in_block":
				return self::compare(self::blockName($subject), $operator, self::normalize((string) $value));
			case "surface_mob":
				return self::compare(!BiomeTags::isUnderground($world, $pos), $operator, (bool) $value);
			case "is_navigating":
				return self::compare($addon !== null && $addon->getBrain()?->isNavigating() === true, $operator, (bool) $value);
			case "is_avoiding_mobs":
				return self::compare(false, $operator, (bool) $value);
		}

		if($report !== null && !isset(self::$reported[$test])){
			self::$reported[$test] = true;
			($report)("filter test \"$test\" is not supported and is treated as false");
		}
		return false;
	}

	/** Bedrock's filter comparison: ==, !=, <, <=, >, >= and the word forms. */
	public static function compare(mixed $actual, string $operator, mixed $expected) : bool{
		if(is_bool($actual)){
			$expected = is_bool($expected) ? $expected : (is_string($expected) ? $expected === "true" : (bool) $expected);
		}elseif(is_numeric($actual) && is_numeric($expected)){
			$actual = (float) $actual;
			$expected = (float) $expected;
		}elseif(is_string($actual)){
			$actual = strtolower($actual);
			$expected = strtolower((string) (is_bool($expected) ? ($expected ? "true" : "false") : $expected));
		}
		return match($operator){
			"!=", "<>", "not" => $actual != $expected,
			"<", "less" => $actual < $expected,
			"<=", "less_or_equal" => $actual <= $expected,
			">", "greater" => $actual > $expected,
			">=", "greater_or_equal" => $actual >= $expected,
			default => $actual == $expected,
		};
	}

	/** @return list<string> */
	public static function familiesOf(Entity $entity) : array{
		if($entity instanceof AddonEntity){
			return $entity->getFamilies();
		}
		if($entity instanceof Player){
			return ["player", "mob"];
		}
		return self::VANILLA_FAMILIES[$entity::getNetworkTypeId()] ?? [strtolower(str_replace("minecraft:", "", $entity::getNetworkTypeId()))];
	}

	/** @param mixed[] $filter */
	private static function hasEquipment(Entity $subject, array $filter) : bool{
		if(!$subject instanceof Living){
			return false;
		}
		$wanted = StringToItemParser::getInstance()->parse(self::normalize((string) ($filter["value"] ?? "")));
		if($wanted === null){
			return false;
		}
		$domain = is_string($filter["domain"] ?? null) ? $filter["domain"] : "any";
		$items = [];
		if(($domain === "hand" || $domain === "any") && $subject instanceof Player){
			$items[] = $subject->getInventory()->getItemInHand();
			$items[] = $subject->getOffHandInventory()->getItem(0);
		}
		if($domain !== "hand"){
			$armor = $subject->getArmorInventory();
			$items = [...$items, ...match($domain){
				"head" => [$armor->getHelmet()],
				"torso" => [$armor->getChestplate()],
				"leg" => [$armor->getLeggings()],
				"feet" => [$armor->getBoots()],
				"armor", "any" => $armor->getContents(),
				default => [],
			}];
		}
		foreach($items as $item){
			if(!$item->isNull() && $item->getTypeId() === $wanted->getTypeId()){
				return true;
			}
		}
		return false;
	}

	private static function blockName(Entity $subject) : string{
		$block = $subject->getWorld()->getBlock($subject->getPosition());
		return self::normalize(strtolower(str_replace(" ", "_", $block->getName())));
	}

	private static function normalize(string $name) : string{
		$name = strtolower($name);
		return str_contains($name, ":") ? $name : "minecraft:" . $name;
	}

	public static function isDay(World $world) : bool{
		$time = $world->getTimeOfDay() % World::TIME_FULL;
		return $time < World::TIME_SUNSET || $time >= World::TIME_SUNRISE;
	}

	private static function difficultyName(int $difficulty) : string{
		return match($difficulty){
			World::DIFFICULTY_PEACEFUL => "peaceful",
			World::DIFFICULTY_EASY => "easy",
			World::DIFFICULTY_HARD => "hard",
			default => "normal",
		};
	}

	/**
	 * The nearest living, non-spectator player within $radius. Walks the world's player list, which is far
	 * cheaper than World::getNearestEntity() (that scans every entity in the chunks in range).
	 */
	public static function nearestPlayer(Entity $from, float $radius) : ?Player{
		$pos = $from->getPosition();
		$best = null;
		$bestDist = $radius * $radius;
		foreach($from->getWorld()->getPlayers() as $player){
			if(!$player->isAlive() || $player->isSpectator()){
				continue;
			}
			$dist = $player->getPosition()->distanceSquared($pos);
			if($dist <= $bestDist){
				$best = $player;
				$bestDist = $dist;
			}
		}
		return $best;
	}

	/** A chance roll in [0, 1). */
	public static function chance(float $chance) : bool{
		return $chance >= 1 || ($chance > 0 && mt_rand() / (mt_getrandmax() + 1) < $chance);
	}
}
