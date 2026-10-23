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

require_once __DIR__ . '/../../../support/redstone/RedstoneTestEnvironment.php';

use PHPUnit\Framework\TestCase;
use pocketmine\block\Block;
use pocketmine\block\VanillaBlocks;
use pocketmine\math\Vector3;
use pocketmine\world\World;

final class RedstoneComponentRegressionTest extends TestCase{

	public function testLampTurnsOffAfterSixGameTicks() : void{
		$world = $this->getMockBuilder(World::class)->disableOriginalConstructor()->onlyMethods([
			"getBlockAt", "setBlock", "isChunkLoaded", "isInWorld"
		])->getMock();
		$lamp = VanillaBlocks::REDSTONE_LAMP()->setLit(true);
		$lamp->position($world, 0, 64, 0);
		$world->method("isChunkLoaded")->willReturn(true);
		$world->method("isInWorld")->willReturn(true);
		$world->method("getBlockAt")->willReturnCallback(function(int $x, int $y, int $z) use (&$lamp, $world) : Block{
			$block = $x === 0 && $y === 64 && $z === 0 ? clone $lamp : VanillaBlocks::AIR();
			$block->position($world, $x, $y, $z);
			return $block;
		});
		$world->method("setBlock")->willReturnCallback(function(Vector3 $pos, Block $block) use (&$lamp, $world) : void{
			$lamp = clone $block;
			$lamp->position($world, 0, 64, 0);
		});
		$engine = new RedstoneEngine($world, 100);
		$engine->onNeighbourUpdate($lamp);
		$engine->tick(0);
		for($tick = 1; $tick < 6; ++$tick){
			$engine->tick($tick);
			self::assertTrue($lamp->isLit(), "Lamp switched off early at tick $tick");
		}
		$engine->tick(6);
		self::assertFalse($lamp->isLit());
	}
	public function testRedstoneBlockDirectlyPowersDustButDoesNotStronglyPowerStone() : void{
		foreach([false, true] as $throughStone){
			[$engine, $world] = RedstoneTestEnvironment::create();
			$world->setBlockAt(0, 64, 0, VanillaBlocks::REDSTONE(), false);
			$wireX = $throughStone ? 2 : 1;
			if($throughStone){ $world->setBlockAt(1, 64, 0, VanillaBlocks::STONE(), false); }
			$world->setBlockAt($wireX, 63, 0, VanillaBlocks::STONE(), false);
			$world->setBlockAt($wireX, 64, 0, VanillaBlocks::REDSTONE_WIRE(), false);
			$budget = 100;
			$engine->getWires()->update($world->getBlockAt($wireX, 64, 0), $budget);
			for($tick = 0; $tick < 10; ++$tick){ $engine->tick($tick); }
			self::assertSame($throughStone ? 0 : 15, $world->getBlockAt($wireX, 64, 0)->getOutputSignalStrength());
		}
	}

}
