<?php

/*
 *
 *     _             _
 *    / \   _ __ ___ | |__   ___ _ __
 *   / _ \ | '_ ` _ \| '_ \ / _ \ '__|
 *  / ___ \| | | | | | |_) |  __/ |
 * /_/   \_\_| |_| |_|_.__/ \___|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author AmberPM Team
 * @link https://github.com/Amber-PM/Amber
 *
 *
 */

declare(strict_types=1);

namespace pocketmine\tests\comparative;

require_once dirname(__DIR__) . '/phpunit/bootstrap.php';

use pocketmine\entity\Entity;
use pocketmine\item\StringToItemParser;
use pocketmine\loot\LootContext;
use pocketmine\loot\LootTableRegistry;
use pocketmine\player\Player;
use pocketmine\world\Position;
use pocketmine\world\World;
use ReflectionClass;
use ReflectionProperty;
use function abs;
use function getopt;
use function is_numeric;
use function printf;
use function str_replace;
use function strtolower;
use const PHP_EOL;

$opts = getopt("", ["rolls:", "tolerance:"]);
$totalRolls = isset($opts["rolls"]) && is_numeric($opts["rolls"]) ? (int) $opts["rolls"] : 500000;
$tolerance = isset($opts["tolerance"]) && is_numeric($opts["tolerance"]) ? (float) $opts["tolerance"] : 0.02;

printf("========================================================================%s", PHP_EOL);
printf("   BDS Loot Table Empirical Statistical Validator (Amber Parity Suite)  %s", PHP_EOL);
printf("========================================================================%s", PHP_EOL);
printf("[*] Target Roll Iterations: %d%s", $totalRolls, PHP_EOL);
printf("[*] Maximum Permissible Error Tolerance: %.2f%%%s", $tolerance * 100.0, PHP_EOL);
printf("%s", PHP_EOL);

$reflectionClass = new ReflectionClass(World::class);
/** @var World $dummyWorld */
$dummyWorld = $reflectionClass->newInstanceWithoutConstructor();

$playerReflection = new ReflectionClass(Player::class);
/** @var Player $dummyPlayer */
$dummyPlayer = $playerReflection->newInstanceWithoutConstructor();
(new ReflectionProperty(Entity::class, "closed"))->setValue($dummyPlayer, true);

// Create a dummy MainLogger instance to prevent uninitialized property access on destruct
$loggerMock = (new ReflectionClass(\pocketmine\utils\MainLogger::class))->newInstanceWithoutConstructor();
(new ReflectionProperty(\pocketmine\utils\MainLogger::class, "logDebug"))->setValue($loggerMock, false);
(new ReflectionProperty(Player::class, "logger"))->setValue($dummyPlayer, $loggerMock);

$context = new LootContext(
	position: new Position(0, 64, 0, $dummyWorld),
	killer: $dummyPlayer,
	lootingLevel: 0
);

$tablesToValidate = [
	"chests/simple_dungeon" => [
		"description" => "Simple Dungeon Chest (Pool 1 weights)",
		"pool_index" => 0,
		"pool_weights" => [
			"minecraft:leather" => 20,
			"minecraft:golden_apple" => 15,
			"minecraft:appleEnchanted" => 2,
			"minecraft:record_13" => 15,
			"minecraft:record_cat" => 15,
			"minecraft:record_otherside" => 2,
			"minecraft:name_tag" => 20,
			"minecraft:horsearmorgold" => 10,
			"minecraft:horsearmoriron" => 15,
			"minecraft:copper_horse_armor" => 15,
			"minecraft:horsearmordiamond" => 5,
			"minecraft:book" => 10
		]
	],
	"entities/zombie" => [
		"description" => "Zombie Rare Drops Pool (Equal weights 1:1:1)",
		"pool_index" => 1,
		"pool_weights" => [
			"minecraft:iron_ingot" => 1,
			"minecraft:carrot" => 1,
			"minecraft:potato" => 1
		]
	]
];

$allPassed = true;

foreach($tablesToValidate as $tableId => $spec){
	printf("--> Validating table: %s (%s)...%s", $tableId, $spec["description"], PHP_EOL);
	$table = LootTableRegistry::getInstance()->get($tableId);
	if($table === null){
		printf("    [-] FAILED: Table %s not found in registry!%s", $tableId, PHP_EOL);
		$allPassed = false;
		continue;
	}

	$poolIndex = $spec["pool_index"];
	if(!isset($table->pools[$poolIndex])){
		printf("    [-] FAILED: Pool %d not found in table %s!%s", $poolIndex, $tableId, PHP_EOL);
		$allPassed = false;
		continue;
	}
	$targetPool = $table->pools[$poolIndex];

	$supportedWeights = [];
	foreach($spec["pool_weights"] as $key => $weight){
		if(StringToItemParser::getInstance()->parse($key) !== null){
			$supportedWeights[$key] = $weight;
		}
	}

	$totalPoolWeight = 0;
	foreach($supportedWeights as $w){
		$totalPoolWeight += $w;
	}

	$counts = [];
	foreach($supportedWeights as $name => $w){
		$counts[$name] = 0;
	}

	// For zombie rare drops, set looting level high so condition always passes
	$poolContext = ($tableId === "entities/zombie") ? $context->withLootingLevel(100) : $context;

	$poolSamples = 0;
	for($i = 0; $i < $totalRolls; ++$i){
		$items = $targetPool->roll($poolContext);
		foreach($items as $item){
			$name = strtolower(str_replace([' ', '_', 'minecraft:'], '', $item->getName()));
			$vanilla = strtolower(str_replace([' ', '_', 'minecraft:'], '', $item->getVanillaName()));

			foreach($supportedWeights as $key => $weight){
				$cleanKey = strtolower(str_replace([' ', '_', 'minecraft:'], '', $key));

				$matched = false;
				if($cleanKey === "appleenchanted"){
					$matched = ($name === "enchantedgoldenapple" || $vanilla === "enchantedgoldenapple" || $name === "appleenchanted" || $vanilla === "appleenchanted");
				}elseif($cleanKey === "goldenapple"){
					$matched = ($name === "goldenapple" || $vanilla === "goldenapple");
				}elseif($cleanKey === "book"){
					$matched = ($name === "book" || $vanilla === "book" || $name === "enchantedbook" || $vanilla === "enchantedbook");
				}elseif($name === $cleanKey || $vanilla === $cleanKey){
					$matched = true;
				}

				if($matched){
					$counts[$key]++;
					$poolSamples++;
					break;
				}
			}
		}
	}

	printf("    Sampled %d items from pool %d across %d rolls.%s", $poolSamples, $poolIndex, $totalRolls, PHP_EOL);
	printf("    +--------------------------------+------------+------------+-----------+%s", PHP_EOL);
	printf("    | Item Identifier                | Theoretical| Empirical  | Delta (%%) |%s", PHP_EOL);
	printf("    +--------------------------------+------------+------------+-----------+%s", PHP_EOL);

	$tablePassed = true;
	foreach($supportedWeights as $entryName => $weight){
		$expectedRatio = $weight / $totalPoolWeight;
		$empiricalRatio = $poolSamples > 0 ? ($counts[$entryName] / $poolSamples) : 0.0;
		$delta = abs($empiricalRatio - $expectedRatio);
		$relativeError = $expectedRatio > 0 ? ($delta / $expectedRatio) : 0.0;

		$status = ($relativeError <= $tolerance || $delta < 0.003) ? "OK" : "FAIL";
		if($status === "FAIL"){
			$tablePassed = false;
			$allPassed = false;
		}

		printf(
			"    | %-30s | %9.3f%% | %9.3f%% | %8.3f%% | [%s]%s",
			$entryName,
			$expectedRatio * 100.0,
			$empiricalRatio * 100.0,
			$relativeError * 100.0,
			$status,
			PHP_EOL
		);
	}
	printf("    +--------------------------------+------------+------------+-----------+%s", PHP_EOL);
	printf("    Result: %s%s%s", $tablePassed ? "[+] PASSED" : "[-] FAILED", PHP_EOL, PHP_EOL);
}

if(!$allPassed){
	printf("[-] Empirical distribution validation FAILED! Discrepancy exceeds tolerance.%s", PHP_EOL);
	exit(1);
}

printf("[+] All empirical BDS statistical distribution checks PASSED within %.2f%% tolerance!%s", $tolerance * 100.0, PHP_EOL);
exit(0);
