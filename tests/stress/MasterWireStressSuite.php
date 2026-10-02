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

final class MasterWireStressSuite {
	/** @var list<array{Workload: string, Size: int, Budget: int, 'Ticks to settle': int, 'Peak continuation count': int, 'Peak owner count': int, Result: string}> */
	private array $results = [];

	public function runAll() : void {
		echo "[info] Starting WireNetwork comprehensive stress suite\n";
		$startTime = microtime(true);

		// 1. Lines
		$this->testLine("Line 1k", 1000, 500, 50);
		$this->testLine("Line 5k", 5000, 1000, 100);
		$this->testLine("Line 10k", 10000, 2000, 150);

		// 2. Grids
		$this->testGrid("Grid 20x20", 20, 20, 500, 50);
		$this->testGrid("Grid 50x50", 50, 50, 1000, 100);
		$this->testGrid("Grid 100x100", 100, 100, 2000, 200);

		// 3. Branching Trees
		$this->testTree("Branching tree (1.1k)", 100, 5, 500, 50);
		$this->testTree("Branching tree (4.2k)", 250, 8, 1000, 100);

		// 4. Dense Loops
		$this->testDenseLoops("Dense loops (30x30 punctured)", 30, 500, 50);
		$this->testDenseLoops("Dense loops (50x50 punctured)", 50, 1000, 100);

		// 5. Multiple Disconnected Networks
		$this->testDisconnected("Disconnected lines (10x500)", 10, 500, 1000, 100);
		$this->testDisconnected("Disconnected lines (20x200)", 20, 200, 500, 100);

		// 6. Multi-Seed Concurrent Collisions
		$this->testMultiSeedLine("Multi-seed line (20 seeds)", 2000, 20, 500, 100);
		$this->testMultiSeed2DGrid("2D Multi-seed (25 seeds)", 50, 5, 1000, 100);

		// 7. Networks Connected During Active Continuations
		$this->testConnectedMidFlight("Connected mid-flight (500+500)", 500, 1000, 100);

		// 8. Networks Split During Active Continuations
		$this->testSplitMidFlight("Split mid-flight (1k cut @200)", 1000, 200, 500, 100);

		// 9. Mutation Near Merge Boundaries
		$this->testMergeBoundaryMutation("Merge boundary mutation (400 wires)", 200, 500, 100);

		// 10. Mutation During Specific Phases
		$this->testPhaseMutation(WireNetwork::PHASE_DISCOVER, "PHASE_DISCOVER", 300, 500, 50);
		$this->testPhaseMutation(WireNetwork::PHASE_SOURCES, "PHASE_SOURCES", 300, 500, 50);
		$this->testPhaseMutation(WireNetwork::PHASE_PROPAGATE, "PHASE_PROPAGATE", 300, 500, 50);
		$this->testPhaseMutation(WireNetwork::PHASE_APPLY, "PHASE_APPLY", 300, 500, 50);
		$this->testPhaseMutation(WireNetwork::PHASE_MERGE, "PHASE_MERGE", 300, 500, 50);
		$this->testPhaseMutation(WireNetwork::PHASE_CLEANUP, "PHASE_CLEANUP", 300, 500, 50);

		$totalElapsed = round(microtime(true) - $startTime, 2);
		echo "\n[info] All tests completed in {$totalElapsed}s\n\n";

		$this->printMarkdownTable();
	}

	private function testLine(string $name, int $size, int $budget, int $maxTicks) : void {
		$initialBudget = $budget;
		[$engine, $world, $blocks] = createEnvironment($budget);

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
		$this->recordResult($name, $size, $initialBudget, $ticks, $maxTicks, $harness);
	}

	private function testGrid(string $name, int $width, int $height, int $budget, int $maxTicks) : void {
		$initialBudget = $budget;
		$size = $width * $height;
		[$engine, $world, $blocks] = createEnvironment($budget);

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
		$this->recordResult($name, $size, $initialBudget, $ticks, $maxTicks, $harness);
	}

	private function testTree(string $name, int $ribsCount, int $ribLength, int $budget, int $maxTicks) : void {
		$initialBudget = $budget;
		[$engine, $world, $blocks] = createEnvironment($budget);

		$totalWires = 0;
		$spineLength = $ribsCount * 2;
		for ($x = 0; $x < $spineLength; ++$x) {
			$world->setBlockAt($x, 63, 0, VanillaBlocks::STONE(), false);
			$world->setBlockAt($x, 64, 0, VanillaBlocks::REDSTONE_WIRE(), false);
			++$totalWires;
		}

		for ($i = 0; $i < $ribsCount; ++$i) {
			$x = $i * 2;
			for ($z = 1; $z <= $ribLength; ++$z) {
				$world->setBlockAt($x, 63, $z, VanillaBlocks::STONE(), false);
				$world->setBlockAt($x, 64, $z, VanillaBlocks::REDSTONE_WIRE(), false);
				++$totalWires;
			}
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
		$this->recordResult($name, $totalWires, $initialBudget, $ticks, $maxTicks, $harness);
	}

	private function testDenseLoops(string $name, int $gridDim, int $budget, int $maxTicks) : void {
		$initialBudget = $budget;
		[$engine, $world, $blocks] = createEnvironment($budget);

		$totalWires = 0;
		for ($x = 0; $x < $gridDim; ++$x) {
			for ($z = 0; $z < $gridDim; ++$z) {
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
		$this->recordResult($name, $totalWires, $initialBudget, $ticks, $maxTicks, $harness);
	}

	private function testDisconnected(string $name, int $numNetworks, int $wiresPerNet, int $budget, int $maxTicks) : void {
		$initialBudget = $budget;
		$totalWires = $numNetworks * $wiresPerNet;
		[$engine, $world, $blocks] = createEnvironment($budget);

		$seeds = [];
		for ($netIdx = 0; $netIdx < $numNetworks; ++$netIdx) {
			$z = $netIdx * 4;
			for ($x = 0; $x < $wiresPerNet; ++$x) {
				$world->setBlockAt($x, 63, $z, VanillaBlocks::STONE(), false);
				$world->setBlockAt($x, 64, $z, VanillaBlocks::REDSTONE_WIRE(), false);
			}
			$seeds[] = $world->getBlockAt(0, 64, $z);
		}

		$net = $engine->getWires();
		$harness = new StressHarness($net);

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
		$this->recordResult($name, $totalWires, $initialBudget, $ticks, $maxTicks, $harness);
	}

	private function testMultiSeedLine(string $name, int $size, int $seedCount, int $budget, int $maxTicks) : void {
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
		$this->recordResult($name, $size, $initialBudget, $ticks, $maxTicks, $harness);
	}

	private function testMultiSeed2DGrid(string $name, int $gridDim, int $seedsPerAxis, int $budget, int $maxTicks) : void {
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
		for ($i = 0; $i < $seedsPerAxis; ++$i) {
			for ($j = 0; $j < $seedsPerAxis; ++$j) {
				$seedX = min($gridDim - 1, (int) ($i * $step + $step / 2));
				$seedZ = min($gridDim - 1, (int) ($j * $step + $step / 2));
				$b = 0;
				$net->update($world->getBlockAt($seedX, 64, $seedZ), $b);
			}
		}
		$harness->checkInvariants();

		$ticks = 0;
		while ($net->hasDeferred() && $ticks < $maxTicks) {
			++$ticks;
			$engine->tick($ticks);
			$harness->checkInvariants();
		}

		$harness->verifyFinalState();
		$this->recordResult($name, $size, $initialBudget, $ticks, $maxTicks, $harness);
	}

	private function testConnectedMidFlight(string $name, int $halfSize, int $budget, int $maxTicks) : void {
		$initialBudget = $budget;
		$totalWires = $halfSize * 2 + 1;
		[$engine, $world, $blocks] = createEnvironment($budget);

		for ($x = 0; $x < $halfSize; ++$x) {
			$world->setBlockAt($x, 63, 0, VanillaBlocks::STONE(), false);
			$world->setBlockAt($x, 64, 0, VanillaBlocks::REDSTONE_WIRE(), false);
		}
		$rightStart = $halfSize + 1;
		$rightEnd = $halfSize * 2;
		for ($x = $rightStart; $x <= $rightEnd; ++$x) {
			$world->setBlockAt($x, 63, 0, VanillaBlocks::STONE(), false);
			$world->setBlockAt($x, 64, 0, VanillaBlocks::REDSTONE_WIRE(), false);
		}

		$net = $engine->getWires();
		$harness = new StressHarness($net);

		$b1 = 0;
		$net->update($world->getBlockAt(0, 64, 0), $b1);
		$b2 = 0;
		$net->update($world->getBlockAt($rightEnd, 64, 0), $b2);
		$harness->checkInvariants();

		$engine->tick(1);
		$harness->checkInvariants();

		$bridgeX = $halfSize;
		$world->setBlockAt($bridgeX, 63, 0, VanillaBlocks::STONE(), false);
		$world->setBlockAt($bridgeX, 64, 0, VanillaBlocks::REDSTONE_WIRE(), false);
		$engine->onNeighbourUpdate($world->getBlockAt($bridgeX, 64, 0));
		$harness->checkInvariants();

		$ticks = 1;
		while ($net->hasDeferred() && $ticks < $maxTicks) {
			++$ticks;
			$engine->tick($ticks);
			$harness->checkInvariants();
		}

		$harness->verifyFinalState();
		$this->recordResult($name, $totalWires, $initialBudget, $ticks, $maxTicks, $harness);
	}

	private function testSplitMidFlight(string $name, int $size, int $splitAt, int $budget, int $maxTicks) : void {
		$initialBudget = $budget;
		[$engine, $world, $blocks] = createEnvironment($budget);

		for ($x = 0; $x < $size; ++$x) {
			$world->setBlockAt($x, 63, 0, VanillaBlocks::STONE(), false);
			$world->setBlockAt($x, 64, 0, VanillaBlocks::REDSTONE_WIRE(), false);
		}

		$net = $engine->getWires();
		$harness = new StressHarness($net);

		$b = 0;
		$net->update($world->getBlockAt(0, 64, 0), $b);

		$advanceTicks = 0;
		while ($advanceTicks < 10) {
			++$advanceTicks;
			$engine->tick($advanceTicks);
			$harness->checkInvariants();
			if ($net->getContinuationOwner(World::blockHash($splitAt, 64, 0)) !== null) {
				break;
			}
		}

		$world->setBlockAt($splitAt, 64, 0, VanillaBlocks::AIR(), false);
		$net->invalidate(World::blockHash($splitAt, 64, 0));
		$harness->checkInvariants();

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
		$this->recordResult($name, $size, $initialBudget, $ticks, $maxTicks, $harness);
	}

	private function testMergeBoundaryMutation(string $name, int $halfSize, int $budget, int $maxTicks) : void {
		$initialBudget = $budget;
		$totalWires = $halfSize * 2;
		[$engine, $world, $blocks] = createEnvironment($budget);

		for ($x = 0; $x < $totalWires; ++$x) {
			$world->setBlockAt($x, 63, 0, VanillaBlocks::STONE(), false);
			$world->setBlockAt($x, 64, 0, VanillaBlocks::REDSTONE_WIRE(), false);
		}

		$net = $engine->getWires();
		$harness = new StressHarness($net);

		$b1 = 0;
		$net->update($world->getBlockAt(0, 64, 0), $b1);
		$b2 = 0;
		$net->update($world->getBlockAt($totalWires - 1, 64, 0), $b2);
		$harness->checkInvariants();

		for ($i = 0; $i < 5000; ++$i) {
			$net->processDeferred(1);
			$conts = $harness->getContinuations();
			foreach ($conts as $c) {
				if ($c['phase'] === WireNetwork::PHASE_MERGE) {
					break 2;
				}
			}
		}

		$boundaryX = $halfSize - 1;
		$world->setBlockAt($boundaryX, 64, 0, VanillaBlocks::AIR(), false);
		$net->invalidate(World::blockHash($boundaryX, 64, 0));
		$harness->checkInvariants();

		$world->setBlockAt($halfSize, 63, 1, VanillaBlocks::STONE(), false);
		$world->setBlockAt($halfSize, 64, 1, VanillaBlocks::REDSTONE_WIRE(), false);
		$bBranch = 0;
		$net->update($world->getBlockAt($halfSize, 64, 1), $bBranch);
		$harness->checkInvariants();

		$ticks = 0;
		while ($net->hasDeferred() && $ticks < $maxTicks) {
			++$ticks;
			$engine->tick($ticks);
			$harness->checkInvariants();
		}

		$harness->verifyFinalState();
		$this->recordResult($name, $totalWires + 1, $initialBudget, $ticks, $maxTicks, $harness);
	}

	private function testPhaseMutation(int $targetPhase, string $phaseName, int $size, int $budget, int $maxTicks) : void {
		$initialBudget = $budget;
		[$engine, $world, $blocks] = createEnvironment($budget);

		$world->setBlockAt(-1, 64, 0, VanillaBlocks::REDSTONE(), false);
		for ($x = 0; $x < $size; ++$x) {
			$world->setBlockAt($x, 63, 0, VanillaBlocks::STONE(), false);
			$world->setBlockAt($x, 64, 0, VanillaBlocks::REDSTONE_WIRE(), false);
		}

		$net = $engine->getWires();
		$harness = new StressHarness($net);

		if ($targetPhase === WireNetwork::PHASE_MERGE) {
			$b1 = 0; $net->update($world->getBlockAt(0, 64, 0), $b1);
			$b2 = 0; $net->update($world->getBlockAt($size - 1, 64, 0), $b2);
			for ($i = 0; $i < 10000; ++$i) {
				$net->processDeferred(1);
				foreach ($harness->getContinuations() as $c) {
					if ($c['phase'] === WireNetwork::PHASE_MERGE) {
						break 2;
					}
				}
			}
		} else {
			$b = 0;
			$net->update($world->getBlockAt(0, 64, 0), $b);
			if ($targetPhase !== WireNetwork::PHASE_DISCOVER) {
				for ($i = 0; $i < 10000; ++$i) {
					$net->processDeferred(1);
					foreach ($harness->getContinuations() as $c) {
						if ($c['phase'] === $targetPhase) {
							break 2;
						}
					}
				}
			}
		}

		$mutateX = (int) ($size / 2);
		$mutateHash = World::blockHash($mutateX, 64, 0);
		$world->setBlockAt($mutateX, 64, 0, VanillaBlocks::AIR(), false);
		$net->invalidate($mutateHash);
		$harness->checkInvariants();

		$ticks = 0;
		while ($net->hasDeferred() && $ticks < $maxTicks) {
			++$ticks;
			$engine->tick($ticks);
			$harness->checkInvariants();
		}

		$harness->verifyFinalState();
		$this->recordResult("Mutation in $phaseName", $size, $initialBudget, $ticks, $maxTicks, $harness);
	}

	private function recordResult(string $workload, int $size, int $budget, int $ticks, int $maxTicks, StressHarness $harness) : void {
		$result = "PASS";
		if ($harness->violations !== []) {
			$result = "FAIL: " . implode("; ", array_slice($harness->violations, 0, 2));
		} elseif ($ticks >= $maxTicks && $harness->getContinuations() !== []) {
			$result = "FAIL: Exceeded max ticks bound ($maxTicks)";
		}

		$entry = [
			'Workload' => $workload,
			'Size' => $size,
			'Budget' => $budget,
			'Ticks to settle' => $ticks,
			'Peak continuation count' => $harness->peakContinuations,
			'Peak owner count' => $harness->peakOwners,
			'Result' => $result,
		];
		$this->results[] = $entry;

		$statusMark = $result === "PASS" ? "✓" : "✗";
		echo sprintf("  %s %-36s | Size %5d | Budget %4d | Ticks %2d | Peak Cont %2d | Peak Owners %5d | %s\n",
			$statusMark, $workload, $size, $budget, $ticks, $harness->peakContinuations, $harness->peakOwners, $result);
	}

	private function printMarkdownTable() : void {
		echo "| Workload | Size | Budget | Ticks to settle | Peak continuation count | Peak owner count | Result |\n";
		echo "| :--- | :--- | :--- | :--- | :--- | :--- | :--- |\n";
		foreach ($this->results as $r) {
			echo sprintf("| %s | %s | %s | %d | %d | %d | %s |\n",
				$r['Workload'],
				number_format($r['Size']),
				number_format($r['Budget']),
				$r['Ticks to settle'],
				$r['Peak continuation count'],
				$r['Peak owner count'],
				$r['Result']
			);
		}
	}
}

$suite = new MasterWireStressSuite();
$suite->runAll();
