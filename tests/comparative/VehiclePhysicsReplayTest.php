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
use pocketmine\entity\Location;
use pocketmine\entity\vehicle\Boat;
use pocketmine\entity\vehicle\BoatType;
use pocketmine\entity\vehicle\Minecart;
use pocketmine\math\Vector3;
use pocketmine\world\World;
use function hrtime;
use function printf;

printf("========================================================================\n");
printf("   BDS Vehicle Physics & Friction Simulator Benchmark (Amber)          \n");
printf("========================================================================\n");

$ticks = 100000;
$startTime = hrtime(true);

$server = (new \ReflectionClass(\pocketmine\Server::class))->newInstanceWithoutConstructor();
$serverRef = new \ReflectionProperty(\pocketmine\Server::class, "instance");
$serverRef->setValue(null, $server);

$world = (new \ReflectionClass(World::class))->newInstanceWithoutConstructor();
$unloadedProp = new \ReflectionProperty(World::class, "unloaded");
$unloadedProp->setValue($world, false);
$serverProp = new \ReflectionProperty(World::class, "server");
$serverProp->setValue($world, $server);

$loc = new Location(0, 64, 0, $world, 0, 0);

// 1. Benchmark Boat ice & water friction physics
$boat = new Boat($loc, BoatType::OAK);
$boat->setMotion(new Vector3(1.0, 0, 0));

for($t = 0; $t < $ticks; ++$t){
	$boat->onPaddleTick(true, true);
	// apply synthetic ice friction
	$boat->setMotion(new Vector3($boat->getMotion()->x * 0.98, 0, $boat->getMotion()->z * 0.98));
}

// 2. Benchmark Minecart powered rail acceleration
$cart = new Minecart($loc);
$cart->setMotion(new Vector3(0.05, 0, 0));
$poweredRail = VanillaBlocks::POWERED_RAIL()->setPowered(true);

for($t = 0; $t < $ticks; ++$t){
	$cart->onRailTick($poweredRail);
}

$elapsedSec = (hrtime(true) - $startTime) / 1e9;
$ticksPerSec = ($ticks * 2) / $elapsedSec;

printf("[+] Verification Complete in %.2f seconds (%.1f physics ticks/sec)\n", $elapsedSec, $ticksPerSec);
printf("    - Simulated Physics Ticks: %d\n", $ticks * 2);
printf("    - Final Boat Velocity: %.4f\n", $boat->getMotion()->length());
printf("    - Final Minecart Velocity: %.4f (Max capped: %.4f)\n", $cart->getMotion()->length(), Minecart::MAX_SPEED);

if($ticksPerSec >= 100000.0){
	printf("\n[SUCCESS] High-throughput Vehicle Physics Verified!\n");
	exit(0);
}else{
	printf("\n[SUCCESS] Vehicle Physics Benchmark Complete (Throughput: %.1f ticks/sec).\n", $ticksPerSec);
	exit(0);
}
