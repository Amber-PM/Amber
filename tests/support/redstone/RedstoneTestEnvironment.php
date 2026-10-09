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

use pocketmine\block\Block;
use pocketmine\block\VanillaBlocks;
use pocketmine\math\Vector3;
use pocketmine\world\World;

final class RedstoneTestEnvironment{

	public static function create(int $maxUpdatesPerTick = 1000, ?\Closure $chunksLoaded = null) : array{
		$blocks = [];
		$chunksLoaded ??= static fn() : bool => true;
		$world = (new \PHPUnit\Framework\MockObject\Generator\Generator())->testDouble(
			World::class, true, [
				"getBlockAt",
				"setBlockAt",
				"isInWorld",
				"isChunkLoaded",
				"notifyNeighbourBlockUpdate"
			], callOriginalConstructor: false
		);

		$world->method("isInWorld")->willReturn(true);
		$world->method("isChunkLoaded")->willReturnCallback($chunksLoaded);

		$engine = new RedstoneEngine($world, $maxUpdatesPerTick);

		$world->method("getBlockAt")->willReturnCallback(function(int $x, int $y, int $z) use (&$blocks, $world, $chunksLoaded) : Block{
			$key = "$x:$y:$z";
			if($chunksLoaded() && isset($blocks[$key])){
				return $blocks[$key];
			}
			$air = clone VanillaBlocks::AIR();
			$air->position($world, $x, $y, $z);
			return $air;
		});

		$world->method("setBlockAt")->willReturnCallback(function(int $x, int $y, int $z, Block $block, bool $notify = true) use (&$blocks, $world) : bool{
			$key = "$x:$y:$z";
			$clone = clone $block;
			$clone->position($world, $x, $y, $z);
			$blocks[$key] = $clone;
			if($notify){
				$world->notifyNeighbourBlockUpdate(new Vector3($x, $y, $z));
			}
			return true;
		});

		$world->method("notifyNeighbourBlockUpdate")->willReturnCallback(function(Vector3 $pos) use ($world, $engine) : void{
			$x = $pos->getFloorX();
			$y = $pos->getFloorY();
			$z = $pos->getFloorZ();
			$engine->onNeighbourUpdate($world->getBlockAt($x, $y, $z));
			foreach([
				[$x + 1, $y, $z], [$x - 1, $y, $z],
				[$x, $y + 1, $z], [$x, $y - 1, $z],
				[$x, $y, $z + 1], [$x, $y, $z - 1]
			] as [$nx, $ny, $nz]){
				$engine->onNeighbourUpdate($world->getBlockAt($nx, $ny, $nz));
			}
		});

		return [$engine, $world, $blocks];
	}
}
