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

use PHPUnit\Framework\TestCase;
use pocketmine\addon\entity\AddonEntityDefinition;
use pocketmine\addon\entity\EntityFilter;
use pocketmine\addon\script\CommandBridge;
use pocketmine\addon\spawn\SpawnRule;

final class AddonRuntimeHelpersTest extends TestCase{

	public function testFilterComparisons() : void{
		self::assertTrue(EntityFilter::compare(5, ">=", "5"));
		self::assertFalse(EntityFilter::compare(4, ">", 4));
		self::assertTrue(EntityFilter::compare(3, "<", 4.5));
		self::assertTrue(EntityFilter::compare(true, "==", "true"));
		self::assertTrue(EntityFilter::compare(false, "!=", true));
		self::assertTrue(EntityFilter::compare("Angry", "equals", "angry"));
		self::assertTrue(EntityFilter::compare("calm", "not", "angry"));
	}

	public function testChanceBounds() : void{
		self::assertTrue(EntityFilter::chance(1.0));
		self::assertFalse(EntityFilter::chance(0.0));
		$value = AddonMath::randomFloat();
		self::assertGreaterThanOrEqual(0.0, $value);
		self::assertLessThan(1.0, $value);
	}

	public function testTokenizeKeepsSelectorsAndJsonWhole() : void{
		$tokens = CommandBridge::tokenize('execute as @e[type=amber:x,tag=!a b,r=5] at @s run tellraw @a {"rawtext":[{"text":"hi there"}]}');
		self::assertSame("@e[type=amber:x,tag=!a b,r=5]", $tokens[2]);
		self::assertSame('{"rawtext":[{"text":"hi there"}]}', $tokens[count($tokens) - 1]);
		self::assertSame(["say", "quoted words", "plain"], CommandBridge::tokenize('say "quoted words" plain'));
	}

	public function testEntityDefinitionGroupsAndProperties() : void{
		$definition = AddonEntityDefinition::fromJson(["minecraft:entity" => [
			"description" => ["identifier" => "test:mob", "properties" => [
				"test:level" => ["type" => "int", "range" => [0, 5], "default" => 2],
				"test:mood" => ["type" => "enum", "values" => ["calm", "angry"]],
				"test:flag" => ["type" => "bool"],
			]],
			"components" => ["minecraft:health" => ["value" => 10, "max" => 10], "minecraft:movement" => ["value" => 0.3]],
			"component_groups" => ["test:big" => ["minecraft:health" => ["value" => 40, "max" => 40], "minecraft:scale" => ["value" => 2]]],
			"events" => ["test:grow" => ["add" => ["component_groups" => ["test:big"]]]],
		]], "test.json", "test");

		self::assertSame(["test:level" => 2, "test:mood" => "calm", "test:flag" => false], $definition->getDefaultProperties());
		self::assertSame(10, AddonEntityDefinition::maxHealth($definition->getComponents()));
		$big = $definition->resolveComponents(["test:big"]);
		self::assertSame(40, AddonEntityDefinition::maxHealth($big));
		self::assertSame(2.0, AddonEntityDefinition::scale($big));
		self::assertEqualsWithDelta(0.15, AddonEntityDefinition::movementSpeed($big), 0.0001);
		self::assertTrue($definition->hasEvent("test:grow"));
	}

	public function testSpawnRuleParsing() : void{
		$rule = SpawnRule::fromJson(["minecraft:spawn_rules" => [
			"description" => ["identifier" => "test:mob", "population_control" => "monster"],
			"conditions" => [["minecraft:spawns_underground" => [], "minecraft:herd" => ["min_size" => 2, "max_size" => 3], "minecraft:weight" => ["default" => 7], "minecraft:density_limit" => ["underground" => 4]]],
		]], "rule.json");

		self::assertSame("monster", $rule->getCategory());
		$condition = $rule->getConditions()[0];
		self::assertSame(SpawnRule::UNDERGROUND, SpawnRule::spotKind($condition));
		self::assertSame(7, SpawnRule::weight($condition));
		self::assertSame(4, SpawnRule::densityLimit($condition, SpawnRule::UNDERGROUND));
		[$size] = SpawnRule::herd($condition);
		self::assertGreaterThanOrEqual(2, $size);
		self::assertLessThanOrEqual(3, $size);
		self::assertSame(["test:mob", null], $rule->permute($condition));
	}
}
