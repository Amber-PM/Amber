<?php

declare(strict_types=1);

namespace pocketmine\world\redstone;

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../phpunit/world/redstone/WireContinuationBudgetTest.php';
require_once __DIR__ . '/LineStressTest.php';

use pocketmine\block\VanillaBlocks;
use pocketmine\world\World;

function runGridStress(int $width, int $height, int $budget, int $maxTicks) : array {
	$initialBudget = $budget;
	$size = $width * $height;
	[$engine, $world, $blocks] = createEnv($budget);
	for ($x = 0; $x < $width; ++$x) {
		for ($z = 0; $z < $height; ++$z) {
			$world->setBlockAt($x, 63, $z, VanillaBlocks::STONE(), false);
			$world->setBlockAt($x, 64, $z, VanillaBlocks::REDSTONE_WIRE(), false);
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
		'Workload' => sprintf("Grid %dx%d", $width, $height),
		'Size' => $size,
		'Budget' => $initialBudget,
		'Ticks to settle' => $ticks,
		'Peak continuation count' => $harness->peakContinuations,
		'Peak owner count' => $harness->peakOwners,
		'Result' => $result,
	];
}

echo "[info] Running Grid stress tests...\n";
foreach ([
	[20, 20, 500, 50],
	[50, 50, 1000, 100],
	[100, 100, 2000, 200]
] as [$w, $h, $budget, $maxTicks]) {
	$t0 = microtime(true);
	$res = runGridStress($w, $h, $budget, $maxTicks);
	$elapsed = round((microtime(true) - $t0) * 1000, 1);
	echo sprintf("  %s | Size %d | Budget %d | Ticks %d | Peak Cont %d | Peak Owners %d | %s (%s ms)\n",
		$res['Workload'], $res['Size'], $res['Budget'], $res['Ticks to settle'], $res['Peak continuation count'], $res['Peak owner count'], $res['Result'], $elapsed);
}
