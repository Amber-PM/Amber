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

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../support/redstone/RedstoneTestEnvironment.php';

use pocketmine\block\VanillaBlocks;
use function array_slice;
use function count;
use function implode;
use function max;
use function microtime;
use function round;
use function sprintf;

class StressHarness {
	private \ReflectionProperty $propContinuations;
	private \ReflectionProperty $propOwner;
	private \ReflectionProperty $propAliases;
	private \ReflectionProperty $propAliasSources;

	public int $peakContinuations = 0;
	public int $peakOwners = 0;
	public array $violations = [];

	public function __construct(private WireNetwork $network) {
		$this->propContinuations = new \ReflectionProperty(WireNetwork::class, 'continuations');
		$this->propOwner = new \ReflectionProperty(WireNetwork::class, 'continuationOwner');
		$this->propAliases = new \ReflectionProperty(WireNetwork::class, 'continuationAliases');
		$this->propAliasSources = new \ReflectionProperty(WireNetwork::class, 'continuationAliasSources');
	}

	public function resolveId(int $id, array $aliases) : int {
		$visited = [];
		while (isset($aliases[$id])) {
			if (isset($visited[$id])) {
				$this->violations[] = "Alias cycle detected at continuation ID $id";
				return $id;
			}
			$visited[$id] = true;
			$id = $aliases[$id];
		}
		return $id;
	}

	public function checkInvariants() : void {
		$continuations = $this->propContinuations->getValue($this->network);
		$owners = $this->propOwner->getValue($this->network);
		$aliases = $this->propAliases->getValue($this->network);

		$curContCount = $this->network->getContinuationCount();
		$rawContCount = count($continuations);
		foreach ($continuations as $c) {
			$rawContCount += count($c->mergingSources ?? []);
		}
		$this->peakContinuations = max($this->peakContinuations, $curContCount, $rawContCount);
		$this->peakOwners = max($this->peakOwners, count($owners));

		// Check duplicate live ownership
		$claimedLiveWires = [];
		foreach ($continuations as $cId => $c) {
			if ($c->phase->value === WireNetwork::PHASE_CLEANUP && ($c->cleanupMode ?? -1) === WireNetwork::CLEANUP_INVALIDATED) {
				continue;
			}
			foreach ($c->wires as $hash => $coords) {
				if (isset($claimedLiveWires[$hash])) {
					$this->violations[] = "Wire hash $hash is claimed by multiple live continuations: {$claimedLiveWires[$hash]} and $cId";
				}
				$claimedLiveWires[$hash] = $cId;
			}
			foreach ($c->mergingSources ?? [] as $srcId => $src) {
				foreach ($src->wires ?? [] as $hash => $coords) {
					if (isset($claimedLiveWires[$hash])) {
						$this->violations[] = "Wire hash $hash is claimed by multiple live continuations (in merge): {$claimedLiveWires[$hash]} and $srcId under $cId";
					}
					$claimedLiveWires[$hash] = $cId;
				}
			}
		}

		// Check owner entries resolution
		foreach ($owners as $hash => $ownerId) {
			$resolvedId = $this->resolveId($ownerId, $aliases);
			if (!isset($continuations[$resolvedId])) {
				$this->violations[] = "Owner entry for hash $hash resolves to nonexistent continuation ID $resolvedId (raw $ownerId)";
			}
		}
	}

	public function verifyFinalState() : void {
		$continuations = $this->propContinuations->getValue($this->network);
		$owners = $this->propOwner->getValue($this->network);
		$aliases = $this->propAliases->getValue($this->network);
		$aliasSources = $this->propAliasSources->getValue($this->network);

		if ($this->network->hasDeferred()) {
			$this->violations[] = "Network reports hasDeferred = true after settlement loop finished";
		}
		if (count($continuations) !== 0) {
			$this->violations[] = "Leaked continuations after settlement: " . count($continuations) . " remaining";
		}
		if (count($owners) !== 0) {
			$this->violations[] = "Leaked continuationOwner entries after settlement: " . count($owners) . " remaining";
		}
		if (count($aliases) !== 0) {
			$this->violations[] = "Leaked continuationAliases after settlement: " . count($aliases) . " remaining";
		}
		if (count($aliasSources) !== 0) {
			$this->violations[] = "Leaked continuationAliasSources after settlement: " . count($aliasSources) . " remaining";
		}
	}
}

function createEnv(int $budget) : array {
	return RedstoneTestEnvironment::create($budget);
}

function runLineStress(int $size, int $budget, int $maxTicks) : array {
	[$engine, $world, $blocks] = createEnv($budget);
	for ($x = 0; $x < $size; ++$x) {
		$world->setBlockAt($x, 63, 0, VanillaBlocks::STONE(), false);
		$world->setBlockAt($x, 64, 0, VanillaBlocks::REDSTONE_WIRE(), false);
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
		'Workload' => "Line",
		'Size' => $size,
		'Budget' => $budget,
		'Ticks to settle' => $ticks,
		'Peak continuation count' => $harness->peakContinuations,
		'Peak owner count' => $harness->peakOwners,
		'Result' => $result,
	];
}

echo "[info] Running Line stress tests...\n";
foreach ([1000 => [500, 50], 5000 => [1000, 100], 10000 => [2000, 100]] as $size => [$budget, $maxTicks]) {
	$t0 = microtime(true);
	$res = runLineStress($size, $budget, $maxTicks);
	$elapsed = round((microtime(true) - $t0) * 1000, 1);
	echo sprintf("  Line %d | Budget %d | Ticks %d | Peak Cont %d | Peak Owners %d | %s (%s ms)\n",
		$res['Size'], $res['Budget'], $res['Ticks to settle'], $res['Peak continuation count'], $res['Peak owner count'], $res['Result'], $elapsed);
}
