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

function runPhaseMutationStress(int $targetPhase, int $size, int $budget, int $maxTicks) : array {
	$phaseNames = [
		WireNetwork::PHASE_DISCOVER => "PHASE_DISCOVER",
		WireNetwork::PHASE_SOURCES => "PHASE_SOURCES",
		WireNetwork::PHASE_PROPAGATE => "PHASE_PROPAGATE",
		WireNetwork::PHASE_APPLY => "PHASE_APPLY",
		WireNetwork::PHASE_MERGE => "PHASE_MERGE",
		WireNetwork::PHASE_CLEANUP => "PHASE_CLEANUP",
	];
	$phaseName = $phaseNames[$targetPhase] ?? "PHASE_$targetPhase";

	[$engine, $world, $blocks] = createEnvironment($budget);

	// Create a line of wires with power source at x=-1
	$world->setBlockAt(-1, 64, 0, VanillaBlocks::REDSTONE(), false);
	for ($x = 0; $x < $size; ++$x) {
		$world->setBlockAt($x, 63, 0, VanillaBlocks::STONE(), false);
		$world->setBlockAt($x, 64, 0, VanillaBlocks::REDSTONE_WIRE(), false);
	}

	$net = $engine->getWires();
	$harness = new StressHarness($net);

	if ($targetPhase === WireNetwork::PHASE_MERGE) {
		// Set up two colliding continuations to enter PHASE_MERGE
		$b1 = 0; $net->update($world->getBlockAt(0, 64, 0), $b1);
		$b2 = 0; $net->update($world->getBlockAt($size - 1, 64, 0), $b2);
		$harness->checkInvariants();

		$entered = false;
		for ($i = 0; $i < 10000; ++$i) {
			$net->processDeferred(1);
			$harness->checkInvariants();
			foreach ($harness->getContinuations() as $c) {
				if ($c['phase'] === WireNetwork::PHASE_MERGE) {
					$entered = true;
					break 2;
				}
			}
		}
		if (!$entered) {
			return [
				'Workload' => "Mutation in $phaseName",
				'Size' => $size,
				'Budget' => $budget,
				'Ticks to settle' => 0,
				'Peak continuation count' => $harness->peakContinuations,
				'Peak owner count' => $harness->peakOwners,
				'Result' => "FAIL: Did not reach $phaseName",
			];
		}
	} else {
		$b = 0;
		$net->update($world->getBlockAt(0, 64, 0), $b);
		$harness->checkInvariants();

		if ($targetPhase !== WireNetwork::PHASE_DISCOVER) {
			$entered = false;
			for ($i = 0; $i < 10000; ++$i) {
				$net->processDeferred(1);
				$harness->checkInvariants();
				foreach ($harness->getContinuations() as $c) {
					if ($c['phase'] === $targetPhase) {
						$entered = true;
						break 2;
					}
				}
			}
			if (!$entered) {
				return [
					'Workload' => "Mutation in $phaseName",
					'Size' => $size,
					'Budget' => $budget,
					'Ticks to settle' => 0,
					'Peak continuation count' => $harness->peakContinuations,
					'Peak owner count' => $harness->peakOwners,
					'Result' => "FAIL: Did not reach $phaseName",
				];
			}
		}
	}

	// Now apply mutation during target phase!
	// Break wire at middle of line
	$mutateX = (int) ($size / 2);
	$mutateHash = World::blockHash($mutateX, 64, 0);

	// If target phase is PHASE_APPLY, test breaking wire vs keeping wire
	$world->setBlockAt($mutateX, 64, 0, VanillaBlocks::AIR(), false);
	$net->invalidate($mutateHash);
	$harness->checkInvariants();

	// Ticking until convergence
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
		'Workload' => "Mutation in $phaseName",
		'Size' => $size,
		'Budget' => $budget,
		'Ticks to settle' => $ticks,
		'Peak continuation count' => $harness->peakContinuations,
		'Peak owner count' => $harness->peakOwners,
		'Result' => $result,
	];
}

echo "[info] Running Phase Mutation stress tests...\n";
foreach ([
	WireNetwork::PHASE_DISCOVER,
	WireNetwork::PHASE_SOURCES,
	WireNetwork::PHASE_PROPAGATE,
	WireNetwork::PHASE_APPLY,
	WireNetwork::PHASE_MERGE,
	WireNetwork::PHASE_CLEANUP,
] as $phase) {
	$res = runPhaseMutationStress($phase, 300, 500, 100);
	echo sprintf("  %-25s | Size %4d | Budget %4d | Ticks %2d | Peak Cont %2d | Peak Owners %4d | %s\n",
		$res['Workload'], $res['Size'], $res['Budget'], $res['Ticks to settle'], $res['Peak continuation count'], $res['Peak owner count'], $res['Result']);
}
