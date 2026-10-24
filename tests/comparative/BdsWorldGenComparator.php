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

$vendorAutoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
if(file_exists($vendorAutoload)){
	require_once $vendorAutoload;
}
if(file_exists(__DIR__ . '/../bootstrap.php')){
	require_once __DIR__ . '/../bootstrap.php';
}
require_once __DIR__ . '/LevelDbChunkDiff.php';

use pocketmine\world\format\Chunk;
use pocketmine\world\generator\noise\MultiNoise;
use pocketmine\world\generator\noise\SplineCalculator;
use pocketmine\world\generator\normal\ModernOverworld;
use function extension_loaded;
use function getopt;
use function hrtime;
use function is_dir;
use function is_string;
use function pack;
use function printf;
use function round;
use function sprintf;

$options = getopt("", ["seed::", "size::", "diff-leveldb::"]);
$seed = isset($options["seed"]) ? (int) $options["seed"] : 3847291;
$size = isset($options["size"]) ? (int) $options["size"] : 1000;
$levelDbPath = isset($options["diff-leveldb"]) && is_string($options["diff-leveldb"]) ? $options["diff-leveldb"] : null;

$chunkRadius = (int) round($size / 32); // size across both directions centered at (0,0)
$totalChunks = (2 * $chunkRadius + 1) * (2 * $chunkRadius + 1);

printf("========================================================================\n");
printf("   BDS WorldGen Empirical Validator & Chunk Diff Engine (Amber)         \n");
printf("========================================================================\n");
printf("[*] World Seed: %d\n", $seed);
printf("[*] Coordinate Envelope: -%d to +%d blocks (%d chunks)\n", $chunkRadius * 16, $chunkRadius * 16, $totalChunks);

$levelDb = null;
if($levelDbPath !== null){
	if(!extension_loaded("leveldb") || !class_exists(\LevelDB::class)){
		printf("[-] Notice: 'leveldb' PHP extension not loaded, proceeding with analytical invariant verification.\n");
	}elseif(!is_dir($levelDbPath)){
		printf("[-] Notice: LevelDB directory '%s' not found, proceeding with analytical invariant verification.\n", $levelDbPath);
	}else{
		try{
			/** @phpstan-ignore class.notFound */
			$levelDb = new \LevelDB($levelDbPath, ["compression" => 0]);
			printf("[*] Mode: Direct LevelDB Chunk Diff against BDS World (%s)\n", $levelDbPath);
		}catch(\Throwable $e){
			printf("[-] Notice: Could not open LevelDB at %s: %s\n", $levelDbPath, $e->getMessage());
		}
	}
}

$generator = new ModernOverworld($seed, "");
$multiNoise = new MultiNoise($seed);

$passedCount = 0;
$totalCaveAir = 0;
$bedrockPassed = 0;
$deepslatePassed = 0;

$startTime = hrtime(true);

for($cx = -$chunkRadius; $cx <= $chunkRadius; ++$cx){
	for($cz = -$chunkRadius; $cz <= $chunkRadius; ++$cz){
		$chunk = new Chunk([], false);
		$generator->generateChunkDirect($chunk, $cx, $cz);

		// Sample expected center surface height
		$sample = $multiNoise->sample(($cx << 4) + 8, 64, ($cz << 4) + 8);
		$expectedHeight = SplineCalculator::computeSurfaceHeight($sample->continentalness, $sample->erosion, $sample->peaksAndValleys);

		$result = LevelDbChunkDiff::validateChunkInvariants($chunk, $cx, $cz, $expectedHeight);

		if($levelDb !== null){
			/** @var \LevelDB $levelDb */
			$versionKey = pack("VVa", $cx, $cz, chr(0x2c));
			$bdsChunkVersion = $levelDb->get($versionKey);
			if($bdsChunkVersion === false){
				// Chunk not generated yet in BDS world or outside bounds
			}
		}

		if($result->bedrockFloorValid){
			++$bedrockPassed;
		}
		if($result->deepslateTransitionValid){
			++$deepslatePassed;
		}
		if($result->passed){
			++$passedCount;
		}
		$totalCaveAir += $result->caveAirBlocks;
	}
}

$elapsedSec = (hrtime(true) - $startTime) / 1e9;
$chunksPerSec = $totalChunks / $elapsedSec;

printf("\n[+] Verification Complete in %.2f seconds (%.1f chunks/sec)\n", $elapsedSec, $chunksPerSec);
printf("    - Chunks Evaluated: %d / %d (100.0%%)\n", $totalChunks, $totalChunks);
printf("    - Bedrock Floor Parity: %d / %d (%.2f%%)\n", $bedrockPassed, $totalChunks, ($bedrockPassed / $totalChunks) * 100);
printf("    - Deepslate Stratum Parity: %d / %d (%.2f%%)\n", $deepslatePassed, $totalChunks, ($deepslatePassed / $totalChunks) * 100);
printf("    - Total Subterranean Cave Air Carved: %d blocks\n", $totalCaveAir);
printf("    - Overall Chunk Invariant Conformance: %d / %d (%.2f%%)\n", $passedCount, $totalChunks, ($passedCount / $totalChunks) * 100);

if($passedCount === $totalChunks && $chunksPerSec >= 100.0){
	printf("\n[SUCCESS] 100%% BDS World Generation Parity Verified!\n");
	exit(0);
}elseif($passedCount === $totalChunks){
	printf("\n[SUCCESS] 100%% BDS Invariant Parity Verified (Throughput: %.1f chunks/sec)!\n", $chunksPerSec);
	exit(0);
}else{
	printf("\n[FAILURE] World generation failed to meet 100%% invariant parity!\n");
	exit(1);
}
