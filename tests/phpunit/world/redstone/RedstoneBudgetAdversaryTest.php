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
use pocketmine\block\tile\Container;
use pocketmine\block\VanillaBlocks;
use pocketmine\math\Vector3;
use pocketmine\world\World;
use function intdiv;
use function memory_get_usage;

final class RedstoneBudgetAdversaryTest extends TestCase{
	/** @var array<string, bool> */
	private array $loadedChunks = [];

	private function createWorld(array &$blocks, int $maxUpdatesPerTick = 1000, ?\Closure $onRead = null) : array{
		$world = $this->getMockBuilder(World::class)->disableOriginalConstructor()->onlyMethods([
			"getBlockAt",
			"setBlockAt",
			"getTileAt",
			"isInWorld",
			"isChunkLoaded",
			"notifyNeighbourBlockUpdate"
		])->getMock();

		$world->method("isInWorld")->willReturn(true);
		$world->method("isChunkLoaded")->willReturnCallback(fn(int $x, int $z) : bool => $this->loadedChunks["$x:$z"] ?? true);

		$engine = new RedstoneEngine($world, $maxUpdatesPerTick);

		$world->method("getBlockAt")->willReturnCallback(function(int $x, int $y, int $z) use (&$blocks, $world, $onRead) : Block{
			$onRead?->__invoke();
			$key = "$x:$y:$z";
			if(isset($blocks[$key])){
				return clone $blocks[$key];
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

		return [$engine, $world];
	}

	public function testContainerRotationDoesNotCopyAllWatchers() : void{
		$blocks = [];
		$before = 0;
		$delta = 0;
		[$engine, $world] = $this->createWorld($blocks, 1, function() use (&$before, &$delta) : void{
			$delta = memory_get_usage() - $before;
		});
		$world->getBlockAt(0, 64, 0); // warm up block factories and the callback
		$watch = $engine->getContainerWatch();
		for($i = 0; $i < 20000; ++$i){
			$watch->watch(new Vector3($i, 64, 0), 0);
		}
		$before = memory_get_usage();
		self::assertSame(1, $watch->check($engine, 1));
		self::assertLessThan(65536, $delta, "A one-entry check cannot duplicate the 20,000-entry watcher index");
		self::assertSame(19999, $watch->getWatchedCount());
	}

	public function testParkedAwakeningDoesNotCopyTheChunkBucket() : void{
		$blocks = [];
		$before = 0;
		$peak = 0;
		$reads = 0;
		[$engine, $world] = $this->createWorld($blocks, 20000, function() use (&$before, &$peak, &$reads) : void{
			if(++$reads === 1){
				$peak = memory_get_usage() - $before;
			}
		});
		$world->getBlockAt(0, 64, 0);
		for($i = 0; $i < 20000; ++$i){
			$pos = new Vector3($i % 16, 64 + intdiv($i, 256), intdiv($i, 16) % 16);
			$engine->schedule($pos, 1, VanillaBlocks::REDSTONE_REPEATER()->getStateId());
		}
		$this->loadedChunks["0:0"] = false;
		$engine->tick(1);
		self::assertSame(20000, $engine->getUnloadedDelayedCount());
		$this->loadedChunks["0:0"] = true;
		$peak = 0;
		$reads = 0;
		$before = memory_get_usage();
		$engine->tick(2);
		self::assertLessThan(65536, $peak, "Awakening cannot duplicate the full chunk event bucket before its first entry");
		self::assertSame(0, $engine->getUnloadedDelayedCount());
	}

	public function testDueUnloadedTimersParkWithinSharedBudget() : void{
		$blocks = [];
		[$engine, $world] = $this->createWorld($blocks, 1);
		for($i = 0; $i < 1000; ++$i){
			$pos = new Vector3($i, 64, 0);
			$world->setBlockAt($i, 64, 0, VanillaBlocks::REDSTONE_REPEATER(), false);
			$engine->schedule($pos, 1);
			$this->loadedChunks[($i >> 4) . ":0"] = false;
		}
		for($tick = 1; $tick <= 3; ++$tick){
			$engine->tick($tick);
			self::assertSame($tick, $engine->getUnloadedDelayedCount(), "Parking due work consumes the shared budget too");
		}
	}

	public function testContainerWatchBoundedByBudget() : void{
		$blocks = [];
		[$engine, $world] = $this->createWorld($blocks, 1);

		$containerWatch = $engine->getContainerWatch();

		$tileCount = 0;
		$world->method("getTileAt")->willReturnCallback(function(int $x, int $y, int $z) use (&$tileCount) : ?Container{
			++$tileCount;
			return null;
		});

		for($i = 0; $i < 100; ++$i){
			$pos = new Vector3($i * 16, 64, 0);
			$comp = VanillaBlocks::REDSTONE_COMPARATOR();
			$comp->position($world, $i * 16, 64, 0);
			$blocks["{$pos->x}:64:0"] = $comp;
			$containerWatch->watch($pos, 0);
		}

		$engine->tick(2);

		self::assertLessThanOrEqual(1, $tileCount, "ContainerWatch must not scan unbounded comparators when budget is 1");
	}

	public function testScheduledTimerSlicingAvoidsQuadraticArraySlice() : void{
		$blocks = [];
		[$engine, $world] = $this->createWorld($blocks, 1);

		for($i = 0; $i < 500; ++$i){
			$pos = new Vector3($i, 64, 0);
			$repeater = VanillaBlocks::REDSTONE_REPEATER();
			$repeater->position($world, $i, 64, 0);
			$blocks["$i:64:0"] = $repeater;
			$engine->schedule($pos, 10);
		}

		$delayedProp = new \ReflectionProperty(RedstoneScheduler::class, "delayed");
		$hashesBefore = $delayedProp->getValue($engine->getScheduler())[10];
		self::assertCount(500, $hashesBefore);

		$engine->tick(10);

		$delayedCursorProp = new \ReflectionProperty(RedstoneScheduler::class, "delayedCursor");
		$cursor = $delayedCursorProp->getValue($engine->getScheduler())[10] ?? 0;
		self::assertSame(1, $cursor, "Delayed cursor must advance without reallocating the underlying array");
		self::assertCount(500, $delayedProp->getValue($engine->getScheduler())[10]);
	}

	public function testScheduleAvoidsSynchronousFullKsort() : void{
		$blocks = [];
		[$engine, $world] = $this->createWorld($blocks, 1000);

		for($d = 100; $d >= 1; --$d){
			$pos = new Vector3($d, 64, 0);
			$engine->schedule($pos, $d);
		}

		$heapProp = new \ReflectionProperty(RedstoneScheduler::class, "delayedTicksHeap");
		/** @var \SplMinHeap<int> $heap */
		$heap = $heapProp->getValue($engine->getScheduler());
		self::assertSame(1, $heap->top(), "MinHeap top must be minimum due tick");
	}

	public function testReceiverQueueBoundedUnloadedSkips() : void{
		$blocks = [];
		[$engine, $world] = $this->createWorld($blocks, 1);

		$this->loadedChunks = [
			"0:0" => true,
			"10:10" => false,
		];

		for($i = 0; $i < 500; ++$i){
			$engine->request(160 + ($i % 16), 1 + (int) ($i / 16), 160);
		}

		$engine->request(0, 64, 0);

		$queueProp = new \ReflectionProperty(RedstoneEngine::class, "queue");
		self::assertSame(501, $queueProp->getValue($engine)->count());

		$engine->tick(1);

		self::assertGreaterThan(0, $queueProp->getValue($engine)->count(), "Unloaded positions must not be drained unmetered in one tick");
	}

	public function testUnloadedDelayedBoundedStaleScans() : void{
		$blocks = [];
		[$engine, $world] = $this->createWorld($blocks, 1);

		for($i = 0; $i < 256; ++$i){
			$pos = new Vector3(80 + ($i % 16), 64, 80 + (int) ($i / 16));
			$rep = VanillaBlocks::REDSTONE_REPEATER();
			$rep->position($world, $pos->x, $pos->y, $pos->z);
			$blocks["{$pos->x}:64:{$pos->z}"] = $rep;
			$engine->schedule($pos, 1);
		}
		$this->loadedChunks["5:5"] = false;

		for($tick = 1; $tick <= 256; ++$tick){
			$engine->tick($tick);
		}
		self::assertSame(256, $engine->getUnloadedDelayedCount());

		for($i = 0; $i < 256; ++$i){
			$pos = new Vector3(80 + ($i % 16), 64, 80 + (int) ($i / 16));
			$engine->schedule($pos, 20, 999999);
		}

		$this->loadedChunks["5:5"] = true;

		$unloadedProp = new \ReflectionProperty(RedstoneScheduler::class, "unloadedDelayed");
		$chunkHash = World::chunkHash(5, 5);
		self::assertCount(256, $unloadedProp->getValue($engine->getScheduler())[$chunkHash]);

		$engine->tick(257);

		self::assertArrayHasKey($chunkHash, $unloadedProp->getValue($engine->getScheduler()), "Stale events in newly loaded chunk must not be scanned all at once without budget");
	}
}
