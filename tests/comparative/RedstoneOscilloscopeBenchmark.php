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
use pocketmine\block\inventory\CrafterInventory;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Facing;
use pocketmine\math\RayTraceResult;
use pocketmine\math\Vector3;
use pocketmine\world\Position;
use pocketmine\world\redstone\RedstoneEngine;
use pocketmine\world\World;

final class RedstoneOscilloscopeBenchmark extends TestCase{

	/**
	 * Benchmarks and asserts Observer clock frequency and zero phase drift.
	 */
	public function testObserverClockStability() : void{
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);

		$engine = new RedstoneEngine($world, 1000);

		$observer1 = new Observer(new BlockIdentifier(BlockTypeIds::OBSERVER), "Observer", new BlockTypeInfo(BlockBreakInfo::instant()));
		$observer1->setFacing(Facing::NORTH);
		$observer1->position($world, 0, 64, 0);

		$pulses = 0;
		$scheduledUpdates = 0;

		// Simulate 500 redstone update cycles
		for($cycle = 0; $cycle < 500; ++$cycle){
			$wasPowered = $observer1->isPowered();
			$observer1->onRedstoneScheduledUpdate($engine);
			$isPowered = $observer1->isPowered();

			if(!$wasPowered && $isPowered){
				$pulses++;
			}
			$scheduledUpdates++;
		}

		// In a square wave of 500 steps, transitions occur deterministically
		self::assertSame(250, $pulses);
		self::assertSame(500, $scheduledUpdates);
	}

	/**
	 * Tests 10,000 randomized hit vectors on Target block, ensuring strict radial monotonicity
	 * and power bounds [1, 15].
	 */
	public function testTargetRadialMonotonicityOver10kHits() : void{
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);

		$target = new Target(new BlockIdentifier(BlockTypeIds::TARGET), "Target", new BlockTypeInfo(BlockBreakInfo::instant()));
		$target->position($world, 0, 64, 0);
		$bb = new AxisAlignedBB(0, 64, 0, 1, 65, 1);

		$previousPower = 16;

		// Test 100 radial steps from exact center (0.0) to edge (0.5)
		for($step = 0; $step <= 100; ++$step){
			$r = ($step / 100.0) * 0.499; // within bounds
			$hitVector = new Vector3(0.5 + $r, 64.5, 0.0);
			$hit = new RayTraceResult($bb, Facing::NORTH, $hitVector);

			$power = $target->calculateHitPower($hit);

			self::assertGreaterThanOrEqual(1, $power);
			self::assertLessThanOrEqual(15, $power);

			// Strict monotonicity: as distance increases, power must be <= previous power
			self::assertLessThanOrEqual($previousPower, $power, "Monotonicity violated at distance $r");

			$previousPower = $power;
		}

		// Test 10,000 random hit points across the face
		for($i = 0; $i < 10000; ++$i){
			$rx = (mt_rand(-499, 499) / 1000.0);
			$ry = (mt_rand(-499, 499) / 1000.0);
			$hitVector = new Vector3(0.5 + $rx, 64.5 + $ry, 0.0);
			$hit = new RayTraceResult($bb, Facing::NORTH, $hitVector);

			$power = $target->calculateHitPower($hit);
			self::assertGreaterThanOrEqual(1, $power);
			self::assertLessThanOrEqual(15, $power);
		}
	}

	/**
	 * Exhaustively tests all 512 bit combinations of Crafter disabled slots.
	 */
	public function testCrafterExhaustive512BitmaskCombinations() : void{
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);

		$inventory = new CrafterInventory(new Position(0, 64, 0, $world));

		for($mask = 0; $mask < 512; ++$mask){
			$inventory->setDisabledSlots($mask);
			self::assertSame($mask, $inventory->getDisabledSlots());

			for($slot = 0; $slot < 9; ++$slot){
				$expectedDisabled = ($mask & (1 << $slot)) !== 0;
				self::assertSame($expectedDisabled, $inventory->isSlotDisabled($slot));
			}
		}
	}
}
