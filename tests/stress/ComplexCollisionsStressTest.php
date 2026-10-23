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

namespace pocketmine\world\redstone;

require_once __DIR__ . '/StressHarness.php';

use pocketmine\block\VanillaBlocks;
use pocketmine\world\World;

function runMultiSeedLineStress(int $size, int $seedCount, int $budget, int $maxTicks) : array {
	$initialBudget = $budget;
	[$engine, $world, $blocks] = createEnvironment($budget);

	for ($x = 0; $x < $size; ++$x) {
		$world->setBlockAt($x, 63, 0, VanillaBlocks::STONE(), false);
		$world->setBlockAt($x, 64, 0, VanillaBlocks::REDSTONE_WIRE(), false);
	}

	$net = $engine->getWires();
	$harness = new StressHarness($net);

	$step = (int) ($size / $seedCount);
	for ($i = 0; $i < $seedCount; ++$i) {
		$seedX = min($size - 1, $i * $step);
		$b = 0;
		$net->update($world->getBlockAt($seedX, 64, 0), $b);
	}
	$harness->checkInvariants();

	$ticks = 0;
	while ($net->hasDeferred() && $ticks < $maxTicks) {
		++$ticks;
		$engine->tick($ticks);
		$harness->checkInvariants();
	}

	$harness->verifyFinalState();

	$result = "PASS";
	if ($harness->violations !== []) {
		$result = "FAIL: " . implode("; ", array_slice($harness->violations, 0, 3));
	} elseif ($ticks >= $maxTicks && $net->hasDeferred()) {
		$result = "FAIL: Did not converge within $maxTicks ticks";
	}

	return [
		'Workload' => sprintf("Multi-seed line (%d seeds)", $seedCount),
		'Size' => $size,
		'Budget' => $initialBudget,
		'Ticks to settle' => $ticks,
		'Peak continuation count' => $harness->peakContinuations,
		'Peak owner count' => $harness->peakOwners,
		'Result' => $result,
	];
}

function runMultiSeed2DGridStress(int $gridDim, int $seedsPerAxis, int $budget, int $maxTicks, bool $withMutations = false) : array {
	$initialBudget = $budget;
	$size = $gridDim * $gridDim;
	[$engine, $world, $blocks] = createEnvironment($budget);

	for ($x = 0; $x < $gridDim; ++$x) {
		for ($z = 0; $z < $gridDim; ++$z) {
			$world->setBlockAt($x, 63, $z, VanillaBlocks::STONE(), false);
			$world->setBlockAt($x, 64, $z, VanillaBlocks::REDSTONE_WIRE(), false);
		}
	}

	$net = $engine->getWires();
	$harness = new StressHarness($net);

	$step = (int) ($gridDim / $seedsPerAxis);
	$seedCount = 0;
	for ($i = 0; $i < $seedsPerAxis; ++$i) {
		for ($j = 0; $j < $seedsPerAxis; ++$j) {
			$seedX = min($gridDim - 1, (int) ($i * $step + $step / 2));
			$seedZ = min($gridDim - 1, (int) ($j * $step + $step / 2));
			$b = 0;
			$net->update($world->getBlockAt($seedX, 64, $seedZ), $b);
			++$seedCount;
		}
	}
	$harness->checkInvariants();

	$ticks = 0;
	$mutated = false;
	while ($net->hasDeferred() && $ticks < $maxTicks) {
		++$ticks;
		// If withMutations, trigger mid-flight mutations at tick 3
		if ($withMutations && $ticks === 3 && !$mutated) {
			$mutated = true;
			for ($m = 1; $m <= 5; ++$m) {
				$mx = $m * 8;
				$mz = $m * 8;
				$world->setBlockAt($mx, 64, $mz, VanillaBlocks::AIR(), false);
				$net->invalidate(World::blockHash($mx, 64, $mz));
			}
		}
		$engine->tick($ticks);
		$harness->checkInvariants();
	}

	$harness->verifyFinalState();

	$result = "PASS";
	if ($harness->violations !== []) {
		$result = "FAIL: " . implode("; ", array_slice($harness->violations, 0, 3));
	} elseif ($ticks >= $maxTicks && $net->hasDeferred()) {
		$result = "FAIL: Did not converge within $maxTicks ticks";
	}

	$name = $withMutations ? sprintf("2D Multi-seed (%d seeds + 5 cuts)", $seedCount) : sprintf("2D Multi-seed (%d seeds)", $seedCount);
	return [
		'Workload' => $name,
		'Size' => $size,
		'Budget' => $initialBudget,
		'Ticks to settle' => $ticks,
		'Peak continuation count' => $harness->peakContinuations,
		'Peak owner count' => $harness->peakOwners,
		'Result' => $result,
	];
}

echo "[info] Running Multi-seed collision stress tests...\n";

$res1 = runMultiSeedLineStress(2000, 20, 500, 100);
echo sprintf("  %-35s | Size %5d | Budget %4d | Ticks %2d | Peak Cont %2d | Peak Owners %5d | %s\n",
	$res1['Workload'], $res1['Size'], $res1['Budget'], $res1['Ticks to settle'], $res1['Peak continuation count'], $res1['Peak owner count'], $res1['Result']);

$res2 = runMultiSeed2DGridStress(50, 5, 1000, 100, false);
echo sprintf("  %-35s | Size %5d | Budget %4d | Ticks %2d | Peak Cont %2d | Peak Owners %5d | %s\n",
	$res2['Workload'], $res2['Size'], $res2['Budget'], $res2['Ticks to settle'], $res2['Peak continuation count'], $res2['Peak owner count'], $res2['Result']);

$res3 = runMultiSeed2DGridStress(50, 5, 1000, 100, true);
echo sprintf("  %-35s | Size %5d | Budget %4d | Ticks %2d | Peak Cont %2d | Peak Owners %5d | %s\n",
	$res3['Workload'], $res3['Size'], $res3['Budget'], $res3['Ticks to settle'], $res3['Peak continuation count'], $res3['Peak owner count'], $res3['Result']);
