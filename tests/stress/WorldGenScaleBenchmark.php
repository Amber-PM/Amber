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

namespace pocketmine\tests\stress;

require_once __DIR__ . '/../../tests/bootstrap.php';
require_once __DIR__ . '/../comparative/LevelDbChunkDiff.php';

use pocketmine\tests\comparative\LevelDbChunkDiff;
use pocketmine\world\format\Chunk;
use pocketmine\world\generator\noise\MultiNoise;
use pocketmine\world\generator\noise\SplineCalculator;
use pocketmine\world\generator\normal\ModernOverworld;
use function getopt;
use function hrtime;
use function memory_get_peak_usage;
use function memory_get_usage;
use function mt_rand;
use function printf;
use function round;

$options = getopt("", ["samples::", "workers::"]);
$sampleCount = isset($options["samples"]) ? (int) $options["samples"] : 1000;

printf("========================================================================\n");
printf("   Amber WorldGen 100k Distributed Scale & Stress Benchmark             \n");
printf("========================================================================\n");
printf("[*] Distributed Sample Clusters: %d\n", $sampleCount);
printf("[*] Maximum Coordinate Bound: +/- 100,000 blocks\n");

$seed = 0x2468ACE;
$generator = new ModernOverworld($seed, "");
$multiNoise = new MultiNoise($seed);

$passedCount = 0;
$startTime = hrtime(true);
$startMem = memory_get_usage(true);

for($i = 0; $i < $sampleCount; ++$i){
	// Sample uniformly across [-6250, 6250] chunks (+/- 100,000 blocks)
	$cx = mt_rand(-6250, 6250);
	$cz = mt_rand(-6250, 6250);

	$chunk = new Chunk([], false);
	$generator->generateChunkDirect($chunk, $cx, $cz);

	$sample = $multiNoise->sample(($cx << 4) + 8, 64, ($cz << 4) + 8);
	$expectedHeight = SplineCalculator::computeSurfaceHeight($sample->continentalness, $sample->erosion, $sample->peaksAndValleys);

	$diff = LevelDbChunkDiff::validateChunkInvariants($chunk, $cx, $cz, $expectedHeight);
	if($diff->passed){
		++$passedCount;
	}

	$chunk->collectGarbage();
	unset($chunk);

	if(($i + 1) % 250 === 0 || ($i + 1) === $sampleCount){
		$curElapsed = (hrtime(true) - $startTime) / 1e9;
		printf("  [Progress] Processed %d / %d chunks (%.1f chunks/sec, mem: %.2f MB)\n",
			$i + 1,
			$sampleCount,
			($i + 1) / $curElapsed,
			memory_get_usage(true) / 1024 / 1024
		);
	}
}

$elapsedSec = (hrtime(true) - $startTime) / 1e9;
$chunksPerSec = $sampleCount / $elapsedSec;
$peakMem = memory_get_peak_usage(true) / 1024 / 1024;

printf("\n[+] Benchmark Complete:\n");
printf("    - Total Clusters Tested: %d\n", $sampleCount);
printf("    - Conformance Rate: %d / %d (%.2f%%)\n", $passedCount, $sampleCount, ($passedCount / $sampleCount) * 100);
printf("    - Generation Throughput: %.1f chunks/sec\n", $chunksPerSec);
printf("    - Peak Heap Memory: %.2f MB\n", $peakMem);

if($passedCount === $sampleCount){
	printf("\n[SUCCESS] Scale Benchmark Passed with 100%% Conformance across +/- 100,000 blocks!\n");
	exit(0);
}else{
	printf("\n[FAILURE] Scale Benchmark detected invariant desyncs!\n");
	exit(1);
}
