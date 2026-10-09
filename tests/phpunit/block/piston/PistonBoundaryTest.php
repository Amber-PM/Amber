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

namespace pocketmine\block\piston;

use PHPUnit\Framework\TestCase;
use pocketmine\block\VanillaBlocks;
use pocketmine\math\Facing;
use pocketmine\world\World;
use ReflectionClass;
use SplQueue;
final class PistonBoundaryTest extends TestCase{
	public function testExtensionStopsAtWorldHeightLimit() : void{
		$world = $this->getMockBuilder(World::class)->disableOriginalConstructor()->onlyMethods(["isInWorld"])->getMock();
		$world->method("isInWorld")->willReturnCallback(fn(int $x, int $y, int $z) => $y >= -64 && $y < 320);
		foreach([[319, Facing::UP], [-64, Facing::DOWN]] as [$y, $facing]){
			$piston = VanillaBlocks::PISTON()->setFacing($facing);
			$piston->position($world, 0, $y, 0);
			self::assertFalse($piston->extend());
		}
	}

	public function testBlockAtHeightLimitCannotBePushedOutOfWorld() : void{
		$world = $this->getMockBuilder(World::class)->disableOriginalConstructor()->onlyMethods(["getBlockAt", "isInWorld", "isChunkLoaded"])->getMock();
		$world->method("isInWorld")->willReturnCallback(fn(int $x, int $y, int $z) => $y >= -64 && $y < 320);
		$world->method("isChunkLoaded")->willReturn(true);
		$world->method("getBlockAt")->willReturnCallback(function(int $x, int $y, int $z) use ($world){
			$block = $y === 319 || $y === -64 ? VanillaBlocks::STONE() : VanillaBlocks::AIR();
			$block->position($world, $x, $y, $z);
			return $block;
		});
		foreach([[318, Facing::UP], [-63, Facing::DOWN]] as [$y, $facing]){
			$piston = VanillaBlocks::PISTON()->setFacing($facing);
			$piston->position($world, 0, $y, 0);
			self::assertFalse($piston->extend());
			self::assertSame(VanillaBlocks::STONE()->getTypeId(), $world->getBlock($piston->getPosition()->getSide($facing))->getTypeId());
		}
	}

	public function testStickyPullDoesNotReadUnloadedSource() : void{
		$world = $this->getMockBuilder(World::class)->disableOriginalConstructor()->onlyMethods(["isInWorld"])->getMock();
		$world->method("isInWorld")->willReturn(true);
		$calculator = new PistonStructureCalculator($world, new \pocketmine\math\Vector3(14, 64, 0), Facing::EAST, false);
		self::assertTrue($calculator->calculate());
		self::assertEmpty($calculator->getBlocksToMove());
	}

	public function testSlimeCanMoveHorizontallyAtWorldHeightLimit() : void{
		$world = $this->getMockBuilder(World::class)->disableOriginalConstructor()->onlyMethods(["getBlockAt", "isInWorld", "isChunkLoaded"])->getMock();
		$world->method("isInWorld")->willReturnCallback(fn(int $x, int $y, int $z) => $y >= -64 && $y < 320);
		$world->method("isChunkLoaded")->willReturn(true);
		$world->method("getBlockAt")->willReturnCallback(function(int $x, int $y, int $z) use ($world){
			$block = $x === 1 && $y === 319 && $z === 0 ? VanillaBlocks::SLIME() : VanillaBlocks::AIR();
			$block->position($world, $x, $y, $z);
			return $block;
		});
		$calculator = new PistonStructureCalculator($world, new \pocketmine\math\Vector3(0, 319, 0), Facing::EAST, true);
		self::assertTrue($calculator->calculate());
		self::assertCount(1, $calculator->getBlocksToMove());
	}
	public function testExtensionDoesNotLoadUnavailableTerrain() : void{
		$world = $this->getMockBuilder(World::class)->disableOriginalConstructor()->onlyMethods(["isInWorld", "loadChunk"])->getMock();
		$world->method("isInWorld")->willReturn(true);
		$world->method("loadChunk")->willReturn(null);
		$piston = VanillaBlocks::PISTON()->setFacing(Facing::EAST);
		$piston->position($world, 15, 64, 0);
		self::assertFalse($piston->extend());
	}
	public function testExtendedPistonIsImmovable() : void{
		$piston = VanillaBlocks::PISTON()->setFacing(Facing::NORTH)->setExtended(true);
		self::assertTrue($piston->isExtended());
		self::assertTrue(PistonMoveRules::isImmovable($piston));
	}

	public function testPistonWithUnloadedHeadCannotBeMoved() : void{
		$world = $this->getMockBuilder(World::class)->disableOriginalConstructor()->onlyMethods(["isInWorld"])->getMock();
		$world->method("isInWorld")->willReturn(true);
		$piston = VanillaBlocks::PISTON()->setFacing(Facing::EAST);
		$piston->position($world, 15, 64, 0);
		self::assertTrue(PistonMoveRules::isImmovable($piston));
	}
	public function testSidewaysPushRejectsExtendedPistonBase() : void{
		$world = $this->getMockBuilder(World::class)->disableOriginalConstructor()->onlyMethods(["getBlockAt", "isInWorld", "isChunkLoaded"])->getMock();
		$world->method("isInWorld")->willReturn(true);
		$world->method("isChunkLoaded")->willReturn(true);
		$blocks = ["1:64:0" => VanillaBlocks::PISTON()->setFacing(Facing::NORTH), "1:64:-1" => VanillaBlocks::PISTON_HEAD()->setFacing(Facing::NORTH)];
		$world->method("getBlockAt")->willReturnCallback(function(int $x, int $y, int $z) use ($world, $blocks){
			$block = clone ($blocks["$x:$y:$z"] ?? VanillaBlocks::AIR());
			$block->position($world, $x, $y, $z);
			return $block;
		});
		self::assertTrue($world->getBlockAt(1, 64, 0)->isExtended());
		$calc = new PistonStructureCalculator($world, new \pocketmine\math\Vector3(0, 64, 0), Facing::EAST, true);
		self::assertFalse($calc->calculate());
		self::assertEmpty($calc->getBlocksToMove());
	}
	public function testUnloadedBedrockSurvivesExtension() : void{
		$world = $this->getMockBuilder(World::class)->disableOriginalConstructor()->onlyMethods(["isInWorld", "loadChunk", "getTile", "unlockChunk", "updateAllLight", "getNearbyEntities", "addSound", "getFolderName"])->getMock();
		$world->method("isInWorld")->willReturn(true);
		$world->method("getTile")->willReturn(null);
		$world->method("unlockChunk")->willReturn(true);
		$world->method("getNearbyEntities")->willReturn([]);
		$world->method("getFolderName")->willReturn("review");
		$world->timings = new \pocketmine\world\WorldTimings($world);
		$loaded = new \pocketmine\world\format\Chunk([], true);
		$saved = new \pocketmine\world\format\Chunk([], true);
		$saved->setBlockStateId(0, 64, 0, VanillaBlocks::BEDROCK()->getStateId());
		$piston = VanillaBlocks::PISTON()->setFacing(Facing::EAST);
		$loaded->setBlockStateId(15, 64, 0, $piston->getStateId());
		$chunks = [World::chunkHash(0, 0) => $loaded];
		$ref = new ReflectionClass(World::class);
		$ref->getProperty("chunks")->setValue($world, $chunks);
		$ref->getProperty("blockStateRegistry")->setValue($world, \pocketmine\block\RuntimeBlockStateRegistry::getInstance());
		$ref->getProperty("neighbourBlockUpdateQueue")->setValue($world, new SplQueue());
		$world->method("loadChunk")->willReturnCallback(function(int $x, int $z) use ($world, $ref, &$chunks, $saved){
			$hash = World::chunkHash($x, $z);
			$chunks[$hash] ??= $saved;
			$ref->getProperty("chunks")->setValue($world, $chunks);
			return $chunks[$hash];
		});
		self::assertFalse($world->isChunkLoaded(1, 0));
		$piston->position($world, 15, 64, 0);
		self::assertFalse($piston->extend());
		self::assertFalse($world->isChunkLoaded(1, 0));
		self::assertSame(VanillaBlocks::BEDROCK()->getStateId(), $saved->getBlockStateId(0, 64, 0));
	}
}
