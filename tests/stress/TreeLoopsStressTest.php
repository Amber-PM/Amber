<?php

declare(strict_types=1);

namespace pocketmine\world\redstone;

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../phpunit/world/redstone/WireContinuationBudgetTest.php';
require_once __DIR__ . '/LineStressTest.php';

use pocketmine\block\VanillaBlocks;
use pocketmine\world\World;

function runTreeStress(int $ribsCount, int $ribLength, int $budget, int $maxTicks) : array {
	$initialBudget = $budget;
	[$engine, $world, $blocks] = createEnv($budget);

	$totalWires = 0;
	// Spine along X
	$spineLength = $ribsCount * 2;
	for ($x = 0; $x < $spineLength; ++$x) {
		$world->setBlockAt($x, 63, 0, VanillaBlocks::STONE(), false);
		$world->setBlockAt($x, 64, 0, VanillaBlocks::REDSTONE_WIRE(), false);
		++$totalWires;
	}

	// Ribs along +Z and -Z at every even X
	for ($i = 0; $i < $ribsCount; ++$i) {
		$x = $i * 2;
		// +Z rib
		for ($z = 1; $z <= $ribLength; ++$z) {
			$world->setBlockAt($x, 63, $z, VanillaBlocks::STONE(), false);
			$world->setBlockAt($x, 64, $z, VanillaBlocks::REDSTONE_WIRE(), false);
			++$totalWires;
		}
		// -Z rib
		for ($z = 1; $z <= $ribLength; ++$z) {
			$world->setBlockAt($x, 63, -$z, VanillaBlocks::STONE(), false);
			$world->setBlockAt($x, 64, -$z, VanillaBlocks::REDSTONE_WIRE(), false);
			++$totalWires;
		}
	}

	$net = $engine->getWires();
	$harness = new StressHarness($net);

	$startWire = $world->getBlockAt(0, 64, 0);
	$net->update($startWire, $budget);
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
		'Workload' => sprintf("Tree (spine %d, ribs %dx%d)", $spineLength, $ribsCount * 2, $ribLength),
		'Size' => $totalWires,
		'Budget' => $initialBudget,
		'Ticks to settle' => $ticks,
		'Peak continuation count' => $harness->peakContinuations,
		'Peak owner count' => $harness->peakOwners,
		'Result' => $result,
	];
}

function runDenseLoopsStress(int $gridDim, int $budget, int $maxTicks) : array {
	$initialBudget = $budget;
	[$engine, $world, $blocks] = createEnv($budget);

	$totalWires = 0;
	// Create a dense grid with a lattice of 2x2 cycles
	// A full grid of size gridDim x gridDim has (gridDim-1)^2 small squares/loops!
	// To make it dense with inner cycles and cycles at multiple scales:
	for ($x = 0; $x < $gridDim; ++$x) {
		for ($z = 0; $z < $gridDim; ++$z) {
			// Hollow out every 3rd block to create complex loop topologies
			if ($x % 3 === 1 && $z % 3 === 1) {
				continue;
			}
			$world->setBlockAt($x, 63, $z, VanillaBlocks::STONE(), false);
			$world->setBlockAt($x, 64, $z, VanillaBlocks::REDSTONE_WIRE(), false);
			++$totalWires;
		}
	}

	$net = $engine->getWires();
	$harness = new StressHarness($net);

	$startWire = $world->getBlockAt(0, 64, 0);
	$net->update($startWire, $budget);
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
		'Workload' => sprintf("Dense Loops (%dx%d punctured)", $gridDim, $gridDim),
		'Size' => $totalWires,
		'Budget' => $initialBudget,
		'Ticks to settle' => $ticks,
		'Peak continuation count' => $harness->peakContinuations,
		'Peak owner count' => $harness->peakOwners,
		'Result' => $result,
	];
}

echo "[info] Running Tree & Dense Loops stress tests...\n";
$resTree = runTreeStress(100, 15, 1000, 100);
echo sprintf("  %s | Size %d | Budget %d | Ticks %d | Peak Cont %d | Peak Owners %d | %s\n",
	$resTree['Workload'], $resTree['Size'], $resTree['Budget'], $resTree['Ticks to settle'], $resTree['Peak continuation count'], $resTree['Peak owner count'], $resTree['Result']);

$resLoops = runDenseLoopsStress(45, 1000, 100);
echo sprintf("  %s | Size %d | Budget %d | Ticks %d | Peak Cont %d | Peak Owners %d | %s\n",
	$resLoops['Workload'], $resLoops['Size'], $resLoops['Budget'], $resLoops['Ticks to settle'], $resLoops['Peak continuation count'], $resLoops['Peak owner count'], $resLoops['Result']);
