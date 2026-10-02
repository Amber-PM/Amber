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

use PHPUnit\Framework\TestCase;
use pocketmine\block\Block;
use pocketmine\block\BlockBreakInfo;
use pocketmine\block\BlockIdentifier;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\BlockTypeInfo;
use pocketmine\block\Opaque;
use pocketmine\block\utils\DelayedRedstoneReceiver;
use pocketmine\block\VanillaBlocks;
use pocketmine\math\Vector3;
use pocketmine\world\World;

final class RedstoneScheduleTest extends TestCase{

	/**
	 * @return array{RedstoneEngine, World, array<string, Block>}
	 */
	private function createEnvironment() : array{
		$blocks = [];
		$world = $this->getMockBuilder(World::class)->disableOriginalConstructor()->onlyMethods([
			"getBlockAt",
			"setBlockAt",
			"isInWorld",
			"isChunkLoaded",
			"notifyNeighbourBlockUpdate"
		])->getMock();

		$world->method("isInWorld")->willReturn(true);
		$world->method("isChunkLoaded")->willReturn(true);

		$engine = new RedstoneEngine($world, 1000);

		$world->method("getBlockAt")->willReturnCallback(function(int $x, int $y, int $z) use (&$blocks, $world) : Block{
			$key = "$x:$y:$z";
			$block = isset($blocks[$key]) ? clone $blocks[$key] : VanillaBlocks::AIR();
			$block->position($world, $x, $y, $z);
			return $block;
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
		});

		return [$engine, $world, $blocks];
	}

	public function testRemoveBlockInvalidatesStaleSchedule() : void{
		[$engine, $world] = $this->createEnvironment();
		$pos = new Vector3(0, 64, 0);

		$receiver = new class(new BlockIdentifier(BlockTypeIds::newId()), "Test Delayed", new BlockTypeInfo(BlockBreakInfo::indestructible())) extends Opaque implements DelayedRedstoneReceiver{
			public static int $scheduledUpdates = 0;

			public function onRedstoneUpdate(RedstoneEngine $engine) : void{}

			public function onRedstoneScheduledUpdate(RedstoneEngine $engine) : void{
				++self::$scheduledUpdates;
			}
		};

		$class = get_class($receiver);
		$class::$scheduledUpdates = 0;

		$world->setBlockAt(0, 64, 0, $receiver, false);
		$engine->schedule($pos, 4);

		// Remove the block at tick 1 (replace with Air)
		$engine->tick(1);
		$world->setBlockAt(0, 64, 0, VanillaBlocks::AIR(), true); // triggers notifyNeighbourBlockUpdate

		// Advance to tick 4 when original schedule would have fired
		$engine->tick(2);
		$engine->tick(3);
		$engine->tick(4);

		self::assertSame(0, $class::$scheduledUpdates, "Old scheduled update must not fire after block removal");
	}

	public function testReplaceBlockInvalidatesStaleScheduleAndAcceptsNewSchedule() : void{
		[$engine, $world] = $this->createEnvironment();
		$pos = new Vector3(0, 64, 0);

		$receiverA = new class(new BlockIdentifier(BlockTypeIds::newId()), "Receiver A", new BlockTypeInfo(BlockBreakInfo::indestructible())) extends Opaque implements DelayedRedstoneReceiver{
			public static int $firedA = 0;
			public function onRedstoneUpdate(RedstoneEngine $engine) : void{}
			public function onRedstoneScheduledUpdate(RedstoneEngine $engine) : void{
				++self::$firedA;
			}
		};

		$receiverB = new class(new BlockIdentifier(BlockTypeIds::newId()), "Receiver B", new BlockTypeInfo(BlockBreakInfo::indestructible())) extends Opaque implements DelayedRedstoneReceiver{
			public static int $firedB = 0;
			public function onRedstoneUpdate(RedstoneEngine $engine) : void{}
			public function onRedstoneScheduledUpdate(RedstoneEngine $engine) : void{
				++self::$firedB;
			}
		};

		$classA = get_class($receiverA);
		$classB = get_class($receiverB);
		$classA::$firedA = 0;
		$classB::$firedB = 0;

		// Place Receiver A and schedule delay 6
		$world->setBlockAt(0, 64, 0, $receiverA, false);
		$engine->schedule($pos, 6);

		// At tick 1, replace Receiver A with Receiver B and schedule delay 2
		$engine->tick(1);
		$world->setBlockAt(0, 64, 0, $receiverB, true);
		$engine->schedule($pos, 2);

		// At tick 3 (1 + 2), Receiver B should fire
		$engine->tick(2);
		$engine->tick(3);
		self::assertSame(1, $classB::$firedB, "Receiver B's scheduled update must fire at its scheduled tick");
		self::assertSame(0, $classA::$firedA, "Receiver A's stale scheduled update must not fire");

		// Advance past tick 7 (1 + 6): old schedule must not fire again
		$engine->tick(4);
		$engine->tick(5);
		$engine->tick(6);
		$engine->tick(7);
		self::assertSame(1, $classB::$firedB, "Receiver B must not receive a duplicate stale update from Receiver A");
	}

	public function testRemoveAndReplaceWithSameTypeCanReschedule() : void{
		[$engine, $world] = $this->createEnvironment();
		$pos = new Vector3(0, 64, 0);

		$receiver = new class(new BlockIdentifier(BlockTypeIds::newId()), "Test Receiver", new BlockTypeInfo(BlockBreakInfo::indestructible())) extends Opaque implements DelayedRedstoneReceiver{
			public static int $fired = 0;
			public function onRedstoneUpdate(RedstoneEngine $engine) : void{}
			public function onRedstoneScheduledUpdate(RedstoneEngine $engine) : void{
				++self::$fired;
			}
		};

		$class = get_class($receiver);
		$class::$fired = 0;

		// Place receiver 1 and schedule delay 8
		$world->setBlockAt(0, 64, 0, $receiver, false);
		$engine->schedule($pos, 8);

		// Remove at tick 1
		$engine->tick(1);
		$world->setBlockAt(0, 64, 0, VanillaBlocks::AIR(), true);

		// Place new receiver of same type at tick 2 and reschedule with delay 2
		$engine->tick(2);
		$world->setBlockAt(0, 64, 0, $receiver, true);
		$engine->schedule($pos, 2);

		// At tick 4 (2 + 2), new schedule should fire
		$engine->tick(3);
		$engine->tick(4);
		self::assertSame(1, $class::$fired, "New receiver must fire at its own scheduled tick");

		// Advance past tick 8 (when old schedule was due)
		for($t = 5; $t <= 9; ++$t){
			$engine->tick($t);
		}
		self::assertSame(1, $class::$fired, "Stale schedule must not fire duplicate update");
	}

	public function testRescheduleKeepsExistingScheduleForSameState() : void{
		[$engine, $world] = $this->createEnvironment();
		$pos = new Vector3(0, 64, 0);

		$receiver = new class(new BlockIdentifier(BlockTypeIds::newId()), "Test Receiver", new BlockTypeInfo(BlockBreakInfo::indestructible())) extends Opaque implements DelayedRedstoneReceiver{
			public static int $firedTick = -1;
			public function onRedstoneUpdate(RedstoneEngine $engine) : void{}
			public function onRedstoneScheduledUpdate(RedstoneEngine $engine) : void{
				self::$firedTick = $engine->getCurrentTick();
			}
		};

		$class = get_class($receiver);
		$class::$firedTick = -1;

		$world->setBlockAt(0, 64, 0, $receiver, false);
		$engine->tick(1);
		// Schedule delay 4 at tick 1 (due tick 5)
		$engine->schedule($pos, 4);

		// Reschedule at tick 2 with same state (due tick 6 if rescheduled, or 5 if kept)
		$engine->tick(2);
		$engine->schedule($pos, 4);

		$engine->tick(3);
		$engine->tick(4);
		$engine->tick(5);
		self::assertSame(5, $class::$firedTick, "Existing schedule must be kept for identical state, firing at tick 5");
	}
}
