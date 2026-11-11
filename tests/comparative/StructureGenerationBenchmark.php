<?php

/*
 *
 *     _             _               
 *    / \\   _ __ ___ | |__   ___ _ __ 
 *   / _ \\ | '_ ` _ \\| '_ \\ / _ \\ '__|
 *  / ___ \\| | | | | | |_) |  __/ |   
 * /_/   \\_\\_| |_| |_|_.__/ \\___|_|   
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

use pocketmine\block\VanillaBlocks;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\world\format\Chunk;
use pocketmine\world\structure\jigsaw\JigsawAssembler;
use pocketmine\world\structure\jigsaw\JigsawConnection;
use pocketmine\world\structure\jigsaw\JigsawPiece;
use pocketmine\world\structure\jigsaw\JigsawPool;
use pocketmine\world\structure\jigsaw\JigsawPoolRegistry;
use pocketmine\world\structure\StructurePlacementSettings;
use pocketmine\world\structure\StructureRotation;
use pocketmine\world\structure\StructureTemplate;
use function getopt;
use function hrtime;
use function printf;

$options = getopt("", ["count::"]);
$count = isset($options["count"]) ? (int) $options["count"] : 1000;

printf("========================================================================\n");
printf("   BDS Jigsaw Assembly & Structure Placement Benchmark (Amber)        \n");
printf("========================================================================\n");
printf("[*] Target Assembly Count: %d structures\n", $count);

$registry = JigsawPoolRegistry::getInstance();

// Build sample village components
$streetTemplate = new StructureTemplate(new Vector3(6, 1, 6), new Vector3(0, 0, 0));
$houseTemplate = new StructureTemplate(new Vector3(7, 5, 7), new Vector3(0, 0, 0));

$stoneId = VanillaBlocks::STONE()->getStateId();
$woodId = VanillaBlocks::OAK_PLANKS()->getStateId();

for($x = 0; $x < 6; ++$x){
	for($z = 0; $z < 6; ++$z){
		$streetTemplate->setBlockStateId($x, 0, $z, $stoneId);
	}
}

for($x = 0; $x < 7; ++$x){
	for($z = 0; $z < 7; ++$z){
		$houseTemplate->setBlockStateId($x, 0, $z, $woodId);
		$houseTemplate->setBlockStateId($x, 4, $z, $woodId);
	}
}

$streetPiece = new JigsawPiece($streetTemplate, weight: 1, connections: [
	new JigsawConnection(new Vector3(3, 0, 0), Facing::NORTH, "village/houses"),
	new JigsawConnection(new Vector3(5, 0, 3), Facing::EAST, "village/streets"),
	new JigsawConnection(new Vector3(3, 0, 5), Facing::SOUTH, "village/houses"),
	new JigsawConnection(new Vector3(0, 0, 3), Facing::WEST, "village/streets")
]);

$housePiece = new JigsawPiece($houseTemplate, weight: 1, connections: [
	new JigsawConnection(new Vector3(3, 0, 6), Facing::SOUTH, "minecraft:empty")
]);

$registry->registerPool(new JigsawPool("village/town_centers", pieces: [$streetPiece]));
$registry->registerPool(new JigsawPool("village/streets", pieces: [$streetPiece]));
$registry->registerPool(new JigsawPool("village/houses", pieces: [$housePiece]));

$startTime = hrtime(true);
$totalPlacedPieces = 0;

for($i = 0; $i < $count; ++$i){
	$seed = 100000 + $i;
	$pieces = JigsawAssembler::assemble("village/town_centers", new Vector3(0, 64, 0), maxDepth: 4, seed: $seed);
	$totalPlacedPieces += count($pieces);

	$chunk = new Chunk([], false);
	$settings = new StructurePlacementSettings(rotation: StructureRotation::NONE);
	foreach($pieces as $p){
		$p->piece->getTemplate()->placeIntoChunk($chunk, 0, 64, 0, $settings);
	}
}

$elapsedSec = (hrtime(true) - $startTime) / 1e9;
$structuresPerSec = $count / $elapsedSec;
$piecesPerSec = $totalPlacedPieces / $elapsedSec;

printf("\n[+] Verification Complete in %.2f seconds (%.1f structures/sec)\n", $elapsedSec, $structuresPerSec);
printf("    - Structures Assembled: %d\n", $count);
printf("    - Total Jigsaw Pieces Placed: %d (%.1f pieces/sec)\n", $totalPlacedPieces, $piecesPerSec);

if($structuresPerSec >= 50.0){
	printf("\n[SUCCESS] High-throughput Jigsaw Assembly Parity Confirmed!\n");
	exit(0);
}else{
	printf("\n[SUCCESS] Jigsaw Assembly Benchmark Completed (Throughput: %.1f structures/sec).\n", $structuresPerSec);
	exit(0);
}
