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

namespace pocketmine\block;

use PHPUnit\Framework\TestCase;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Facing;
use pocketmine\math\RayTraceResult;
use pocketmine\math\Vector3;
use pocketmine\world\redstone\RedstoneEngine;
use pocketmine\world\World;

final class TargetBlockTest extends TestCase{

	private function createTarget() : Target{
		return new Target(new BlockIdentifier(BlockTypeIds::TARGET), "Target", new BlockTypeInfo(BlockBreakInfo::instant()));
	}

	public function testBullseyeHitEmitsPower15() : void{
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);

		$target = $this->createTarget();
		$target->position($world, 10, 64, 10);

		// Hit directly in center of North face (Z = 10, center X = 10.5, Y = 64.5)
		$hitVector = new Vector3(10.5, 64.5, 10.0);
		$rayTrace = new RayTraceResult(new AxisAlignedBB(10, 64, 10, 11, 65, 11), Facing::NORTH, $hitVector);

		$power = $target->calculateHitPower($rayTrace);
		self::assertSame(15, $power);
	}

	public function testEdgeHitEmitsPower1() : void{
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);

		$target = $this->createTarget();
		$target->position($world, 10, 64, 10);

		// Hit at outer edge of face (X = 10.99, Y = 64.5, Z = 10.0 => dx = ~0.49 -> outer ring)
		$hitVector = new Vector3(10.99, 64.5, 10.0);
		$rayTrace = new RayTraceResult(new AxisAlignedBB(10, 64, 10, 11, 65, 11), Facing::NORTH, $hitVector);

		$power = $target->calculateHitPower($rayTrace);
		self::assertSame(1, $power);
	}

	public function testIntermediateHitRadialScaling() : void{
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);

		$target = $this->createTarget();
		$target->position($world, 10, 64, 10);

		// Hit at halfway radius: dx = 0.25 (dist = 0.25 / 0.5 = 0.5 -> power ~ 8)
		$hitVector = new Vector3(10.75, 64.5, 10.0);
		$rayTrace = new RayTraceResult(new AxisAlignedBB(10, 64, 10, 11, 65, 11), Facing::NORTH, $hitVector);

		$power = $target->calculateHitPower($rayTrace);
		self::assertGreaterThanOrEqual(7, $power);
		self::assertLessThanOrEqual(9, $power);
	}

	public function testPowerOutputToAllFaces() : void{
		/** @var RedstoneEngine $engine */
		$engine = (new \ReflectionClass(RedstoneEngine::class))->newInstanceWithoutConstructor();
		$target = $this->createTarget();
		$target->setPower(12);

		foreach(Facing::ALL as $face){
			self::assertSame(12, $target->getRedstoneOutput($face, false, $engine));
			self::assertSame(12, $target->getRedstoneOutput($face, true, $engine));
		}
	}

	public function testResetPowerOnScheduledUpdate() : void{
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);

		$engine = new RedstoneEngine($world, 1000);
		$target = $this->createTarget();
		$target->position($world, 0, 64, 0);
		$target->setPower(15);

		$target->onRedstoneScheduledUpdate($engine);
		self::assertSame(0, $target->getPower());
	}
}
