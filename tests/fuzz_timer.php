<?php

/*
 *     _             _                __  __             
 *    / \   _ __ ___ | |__   ___ _ __ |  \/  | __ _ _ __  
 *   / _ \ | '_ ` _ \| '_ \ / _ \ '__|| |\/| |/ _` | '_ \ 
 *  / ___ \| | | | | | |_) |  __/ |   | |  | | (_| | |_) |
 * /_/   \_\_| |_| |_|_.__/ \___|_|   |_|  |_|\__,_| .__/ 
 *                                                 |_|    
 * 
 * AmberMap - High-Performance Bedrock World Map Renderer
 * https://github.com/Amber-PM/AmberMap
 *
 * Copyright (c) 2026 Amber-PM
 * Licensed under Apache-2.0 or MIT
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/phpunit/world/redstone/RedstoneTimerFuzzTest.php';

use pocketmine\world\redstone\RedstoneTimerFuzzTest;

echo "[info] Starting RedstoneEngine Scheduled Work Timer-Fuzzer\n";
echo "[info] Target: Amber-PM/Amber (feature/redstone)\n";

$scenarios = [
	"test10kScheduledUpdatesSameTick" => "Scenario 1: 10k scheduled updates same tick drained strictly bounded under budget",
	"testStaggeredUpdatesAcrossManyTicks" => "Scenario 2: Staggered updates across 100 ticks (10,000 updates) executed exactly on due ticks",
	"testStaggeredUpdatesUnderConstrainedBudgetAccumulateAndDrain" => "Scenario 2b: Staggered updates under constrained budget accumulate and drain strictly within budget",
	"testRepeatedReschedulingSameCoordinateScenarios" => "Scenario 3: Repeated rescheduling same coordinate preserves same-state pending timer, replaces on state change",
	"testCancellationStormsPruneCleanly" => "Scenario 4a: Cancellation storm (5,000 updates cancelled at once) pruned without resurrection",
	"testCancellationStormWithInterleavedSurvivors" => "Scenario 4b: Interleaved cancellation storm (3,000 cancelled, 3,000 survivors) executes only valid updates",
	"testReplacementBeforeDueTickInvalidates" => "Scenario 5: Replacement/break before due tick (notified & silent) prevents execution against wrong state",
	"testUnloadBeforeDueTickAndReloadAfterDueTickMetered" => "Scenario 6 & 7: Unload before due tick parks in unloadedDelayed, reload after due tick awakens metered by budget",
	"testManyUnloadedChunkBucketsRotationAndSimultaneousReload" => "Scenario 8 & 9: 5,000 chunk buckets (~10k timers) rotation bounded; simultaneous reload awakens cleanly",
	"testReplacementWhileParkedInUnloadedChunk" => "Scenario 10: Replacement/cancellation while parked in unloaded chunk correctly handled without leaks",
	"testWorldUnloadMemoryDrainAndZeroLeak" => "Scenario 11: World unload with pending timers completely drains memory with zero residual leak",
];

$passed = 0;
$failed = 0;

foreach($scenarios as $method => $description){
	$test = new RedstoneTimerFuzzTest($method);
	$test->setUp();

	$startTime = microtime(true);
	$startMem = memory_get_usage();

	try{
		$test->$method();
		$elapsed = (microtime(true) - $startTime) * 1000;
		$memDelta = (memory_get_usage() - $startMem) / 1024;
		echo "  ✓ {$description} (" . sprintf("%.2fms", $elapsed) . ")\n";
		$passed++;
	}catch(\Throwable $e){
		echo "  ✗ {$description}\n";
		echo "    [error] " . $e->getMessage() . "\n";
		$failed++;
	}
}

echo "\n[info] Fuzzing complete: {$passed} passed, {$failed} failed\n";

if($failed > 0){
	exit(1);
}
