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
use function array_slice;
use function implode;
use function sprintf;

function runDisconnectedNetworksStress(int $numNetworks, int $wiresPerNetwork, int $budget, int $maxTicks) : array {
	$initialBudget = $budget;
	$totalWires = $numNetworks * $wiresPerNetwork;
	[$engine, $world, $blocks] = RedstoneTestEnvironment::create($budget);

	$seeds = [];
	for ($netIdx = 0; $netIdx < $numNetworks; ++$netIdx) {
		$z = $netIdx * 4;
		for ($x = 0; $x < $wiresPerNetwork; ++$x) {
			$world->setBlockAt($x, 63, $z, VanillaBlocks::STONE(), false);
			$world->setBlockAt($x, 64, $z, VanillaBlocks::REDSTONE_WIRE(), false);
		}
		$seeds[] = $world->getBlockAt(0, 64, $z);
	}

	$net = $engine->getWires();
	$harness = new StressHarness($net);

	// Seed all disconnected networks with 0 budget so they all start deferred
	foreach ($seeds as $seedWire) {
		$b = 0;
		$net->update($seedWire, $b);
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
		'Workload' => sprintf("Disconnected (%dx%d lines)", $numNetworks, $wiresPerNetwork),
		'Size' => $totalWires,
		'Budget' => $initialBudget,
		'Ticks to settle' => $ticks,
		'Peak continuation count' => $harness->peakContinuations,
		'Peak owner count' => $harness->peakOwners,
		'Result' => $result,
	];
}

function runConnectedMidContinuationsStress(int $halfSize, int $budget, int $maxTicks) : array {
	$initialBudget = $budget;
	$totalWires = $halfSize * 2 + 1;
	[$engine, $world, $blocks] = RedstoneTestEnvironment::create($budget);

	// Left segment: 0 .. halfSize-1
	for ($x = 0; $x < $halfSize; ++$x) {
		$world->setBlockAt($x, 63, 0, VanillaBlocks::STONE(), false);
		$world->setBlockAt($x, 64, 0, VanillaBlocks::REDSTONE_WIRE(), false);
	}
	// Right segment: halfSize+1 .. halfSize*2
	$rightStart = $halfSize + 1;
	$rightEnd = $halfSize * 2;
	for ($x = $rightStart; $x <= $rightEnd; ++$x) {
		$world->setBlockAt($x, 63, 0, VanillaBlocks::STONE(), false);
		$world->setBlockAt($x, 64, 0, VanillaBlocks::REDSTONE_WIRE(), false);
	}

	$net = $engine->getWires();
	$harness = new StressHarness($net);

	// Start Left from x=0
	$b1 = 0;
	$net->update($world->getBlockAt(0, 64, 0), $b1);

	// Start Right from x=rightEnd
	$b2 = 0;
	$net->update($world->getBlockAt($rightEnd, 64, 0), $b2);

	$harness->checkInvariants();

	// Step a couple of ticks while disconnected
	$engine->tick(1);
	$harness->checkInvariants();

	// Now connect the two active networks by placing the bridge wire
	$bridgeX = $halfSize;
	$world->setBlockAt($bridgeX, 63, 0, VanillaBlocks::STONE(), false);
	$world->setBlockAt($bridgeX, 64, 0, VanillaBlocks::REDSTONE_WIRE(), false);
	// Notify the bridge
	$engine->onNeighbourUpdate($world->getBlockAt($bridgeX, 64, 0));
	$harness->checkInvariants();

	$ticks = 1;
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
		'Workload' => sprintf("Connected mid-flight (%d+%d wires)", $halfSize, $halfSize),
		'Size' => $totalWires,
		'Budget' => $initialBudget,
		'Ticks to settle' => $ticks,
		'Peak continuation count' => $harness->peakContinuations,
		'Peak owner count' => $harness->peakOwners,
		'Result' => $result,
	];
}

function runSplitMidContinuationsStress(int $size, int $splitAt, int $budget, int $maxTicks) : array {
	$initialBudget = $budget;
	[$engine, $world, $blocks] = RedstoneTestEnvironment::create($budget);

	for ($x = 0; $x < $size; ++$x) {
		$world->setBlockAt($x, 63, 0, VanillaBlocks::STONE(), false);
		$world->setBlockAt($x, 64, 0, VanillaBlocks::REDSTONE_WIRE(), false);
	}

	$net = $engine->getWires();
	$harness = new StressHarness($net);

	// Start traversal from x=0
	$b = 0;
	$net->update($world->getBlockAt(0, 64, 0), $b);

	// Advance traversal until splitAt is discovered
	$advanceTicks = 0;
	while ($advanceTicks < 10) {
		++$advanceTicks;
		$engine->tick($advanceTicks);
		$harness->checkInvariants();
		if ($net->getContinuationOwner(World::blockHash($splitAt, 64, 0)) !== null) {
			break;
		}
	}

	// Sever the wire at splitAt
	$world->setBlockAt($splitAt, 64, 0, VanillaBlocks::AIR(), false);
	$net->invalidate(World::blockHash($splitAt, 64, 0));
	$harness->checkInvariants();

	// Immediately re-seed both sides
	$b1 = 0;
	$net->update($world->getBlockAt(0, 64, 0), $b1);
	$b2 = 0;
	$net->update($world->getBlockAt($splitAt + 1, 64, 0), $b2);
	$harness->checkInvariants();

	$ticks = $advanceTicks;
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
		'Workload' => sprintf("Split mid-flight (1k wire cut @%d)", $splitAt),
		'Size' => $size,
		'Budget' => $initialBudget,
		'Ticks to settle' => $ticks,
		'Peak continuation count' => $harness->peakContinuations,
		'Peak owner count' => $harness->peakOwners,
		'Result' => $result,
	];
}

function runMergeBoundaryMutationStress(int $halfSize, int $budget, int $maxTicks) : array {
	$initialBudget = $budget;
	$totalWires = $halfSize * 2;
	[$engine, $world, $blocks] = RedstoneTestEnvironment::create($budget);

	for ($x = 0; $x < $totalWires; ++$x) {
		$world->setBlockAt($x, 63, 0, VanillaBlocks::STONE(), false);
		$world->setBlockAt($x, 64, 0, VanillaBlocks::REDSTONE_WIRE(), false);
	}

	$net = $engine->getWires();
	$harness = new StressHarness($net);

	// Start two continuations moving towards each other
	$b1 = 0;
	$net->update($world->getBlockAt(0, 64, 0), $b1);
	$b2 = 0;
	$net->update($world->getBlockAt($totalWires - 1, 64, 0), $b2);
	$harness->checkInvariants();

	// Step deferred until collision / PHASE_MERGE is active
	$mergeDetected = false;
	$ticks = 0;
	for ($i = 0; $i < 5000; ++$i) {
		$net->processDeferred(1);
		$conts = $harness->getContinuations();
		foreach ($conts as $c) {
			if ($c->phase->value === WireNetwork::PHASE_MERGE) {
				$mergeDetected = true;
				break 2;
			}
		}
	}

	// Mutate near the collision boundary (halfSize - 1)
	$boundaryX = $halfSize - 1;
	$world->setBlockAt($boundaryX, 64, 0, VanillaBlocks::AIR(), false);
	$net->invalidate(World::blockHash($boundaryX, 64, 0));
	$harness->checkInvariants();

	// Also add a new wire branch adjacent to boundary
	$world->setBlockAt($halfSize, 63, 1, VanillaBlocks::STONE(), false);
	$world->setBlockAt($halfSize, 64, 1, VanillaBlocks::REDSTONE_WIRE(), false);
	$bBranch = 0;
	$net->update($world->getBlockAt($halfSize, 64, 1), $bBranch);
	$harness->checkInvariants();

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
		'Workload' => sprintf("Merge boundary mutation (%d wires)", $totalWires),
		'Size' => $totalWires + 1,
		'Budget' => $initialBudget,
		'Ticks to settle' => $ticks,
		'Peak continuation count' => $harness->peakContinuations,
		'Peak owner count' => $harness->peakOwners,
		'Result' => $result,
	];
}

echo "[info] Running Multi-Network & Dynamic Boundary stress tests...\n";

$resDisc = runDisconnectedNetworksStress(20, 100, 500, 100);
echo sprintf("  %s | Size %d | Budget %d | Ticks %d | Peak Cont %d | Peak Owners %d | %s\n",
	$resDisc['Workload'], $resDisc['Size'], $resDisc['Budget'], $resDisc['Ticks to settle'], $resDisc['Peak continuation count'], $resDisc['Peak owner count'], $resDisc['Result']);

$resConn = runConnectedMidContinuationsStress(500, 1000, 100);
echo sprintf("  %s | Size %d | Budget %d | Ticks %d | Peak Cont %d | Peak Owners %d | %s\n",
	$resConn['Workload'], $resConn['Size'], $resConn['Budget'], $resConn['Ticks to settle'], $resConn['Peak continuation count'], $resConn['Peak owner count'], $resConn['Result']);

$resSplit = runSplitMidContinuationsStress(1000, 200, 500, 100);
echo sprintf("  %s | Size %d | Budget %d | Ticks %d | Peak Cont %d | Peak Owners %d | %s\n",
	$resSplit['Workload'], $resSplit['Size'], $resSplit['Budget'], $resSplit['Ticks to settle'], $resSplit['Peak continuation count'], $resSplit['Peak owner count'], $resSplit['Result']);

$resMergeMut = runMergeBoundaryMutationStress(200, 500, 100);
echo sprintf("  %s | Size %d | Budget %d | Ticks %d | Peak Cont %d | Peak Owners %d | %s\n",
	$resMergeMut['Workload'], $resMergeMut['Size'], $resMergeMut['Budget'], $resMergeMut['Ticks to settle'], $resMergeMut['Peak continuation count'], $resMergeMut['Peak owner count'], $resMergeMut['Result']);
