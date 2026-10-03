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
use pocketmine\block\RedstoneWire;
use pocketmine\block\utils\DelayedRedstoneReceiver;
use pocketmine\block\VanillaBlocks;
use pocketmine\math\Vector3;
use pocketmine\world\format\io\WritableWorldProvider;
use pocketmine\world\generator\executor\GeneratorExecutor;
use pocketmine\world\World;

final class RedstoneScheduledUpdateBudgetTest extends TestCase{

	/** @var array<string, bool> */
	private array $loadedChunks = [];

	protected function setUp() : void{
		parent::setUp();
		$this->loadedChunks = [];
	}

	/**
	 * @return array{RedstoneEngine, World, array<string, Block>}
	 */
	private function createEnvironment(int $maxUpdatesPerTick = 1000) : array{
		$blocks = [];

		$world = $this->getMockBuilder(World::class)->disableOriginalConstructor()->onlyMethods([
			"getBlockAt",
			"setBlockAt",
			"isInWorld",
			"isChunkLoaded",
			"notifyNeighbourBlockUpdate",
			"save",
			"unloadChunk"
		])->getMock();

		$world->method("isInWorld")->willReturn(true);
		$world->method("isChunkLoaded")->willReturnCallback(function(int $chunkX, int $chunkZ) : bool{
			$key = "$chunkX:$chunkZ";
			return $this->loadedChunks[$key] ?? true;
		});
		$world->method("save")->willReturn(true);
		$world->method("unloadChunk")->willReturn(true);

		$engine = new RedstoneEngine($world, $maxUpdatesPerTick);

		$ref = new \ReflectionClass(World::class);
		$genExec = $this->createMock(GeneratorExecutor::class);
		$ref->getProperty("generatorExecutor")->setValue($world, $genExec);

		$provider = $this->createMock(WritableWorldProvider::class);
		$ref->getProperty("provider")->setValue($world, $provider);

		$ref->getProperty("redstone")->setValue($world, $engine);

		$world->method("getBlockAt")->willReturnCallback(function(int $x, int $y, int $z) use (&$blocks, $world) : Block{
			$key = "$x:$y:$z";
			if(isset($blocks[$key])){
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
		});

		return [$engine, $world, $blocks];
	}

	public function testDueScheduledUpdateBurstIsBounded() : void{
		[$engine, $world] = $this->createEnvironment(100);

		$executions = 0;
		$receiverClass = new class(new BlockIdentifier(BlockTypeIds::newId()), "Burst Receiver", new BlockTypeInfo(BlockBreakInfo::indestructible())) extends Opaque implements DelayedRedstoneReceiver{
			public static ?\Closure $handler = null;
			public function onRedstoneUpdate(RedstoneEngine $engine) : void{}
			public function onRedstoneScheduledUpdate(RedstoneEngine $engine) : void{
				if(self::$handler !== null){
					(self::$handler)();
				}
			}
		};
		$className = get_class($receiverClass);
		$className::$handler = function() use (&$executions) : void{
			++$executions;
		};

		$totalCount = 250;
		for($i = 0; $i < $totalCount; ++$i){
			$pos = new Vector3($i, 64, 0);
			$world->setBlockAt($i, 64, 0, $receiverClass, false);
			$engine->schedule($pos, 1);
		}

		$engine->tick(1);
		self::assertSame(100, $executions, "Tick 1 must only process up to the maxUpdatesPerTick budget (100)");

		$engine->tick(2);
		self::assertSame(200, $executions, "Tick 2 must process the next 100 updates without dropping");

		$engine->tick(3);
		self::assertSame(250, $executions, "Tick 3 must process remaining 50 updates");

		$engine->tick(4);
		self::assertSame(250, $executions, "No further updates should fire once queue has drained");
	}

	public function testUnloadedChunkScheduledUpdateDeferredUntilLoad() : void{
		[$engine, $world] = $this->createEnvironment(1000);

		$fired = 0;
		$receiver = new class(new BlockIdentifier(BlockTypeIds::newId()), "Chunk Receiver", new BlockTypeInfo(BlockBreakInfo::indestructible())) extends Opaque implements DelayedRedstoneReceiver{
			public static ?\Closure $handler = null;
			public function onRedstoneUpdate(RedstoneEngine $engine) : void{}
			public function onRedstoneScheduledUpdate(RedstoneEngine $engine) : void{
				if(self::$handler !== null){
					(self::$handler)();
				}
			}
		};
		$className = get_class($receiver);
		$className::$handler = function() use (&$fired) : void{
			++$fired;
		};

		// Position in chunk (10, 10): x = 160, z = 160
		$pos = new Vector3(160, 64, 160);
		$world->setBlockAt(160, 64, 160, $receiver, false);
		$engine->schedule($pos, 1);

		// Unload chunk (10, 10)
		$this->loadedChunks["10:10"] = false;

		$engine->tick(1);
		self::assertSame(0, $fired, "Update must not fire while chunk is unloaded");
		self::assertSame(1, $engine->getUnloadedDelayedCount(), "Event must be parked in unloadedDelayed collection");
		self::assertTrue($engine->isScheduled($pos), "Position must remain marked as scheduled");

		// Chunk remains unloaded at tick 2
		$engine->tick(2);
		self::assertSame(0, $fired);
		self::assertSame(1, $engine->getUnloadedDelayedCount());

		// Chunk reloads before tick 3
		$this->loadedChunks["10:10"] = true;
		$engine->tick(3);

		self::assertSame(1, $fired, "Parked update must execute once chunk is loaded");
		self::assertSame(0, $engine->getUnloadedDelayedCount(), "Parked collection must be drained");
	}

	public function testUnloadedChunkScheduledUpdateCancelledBeforeLoad() : void{
		[$engine, $world] = $this->createEnvironment(1000);

		$fired = 0;
		$receiver = new class(new BlockIdentifier(BlockTypeIds::newId()), "Cancel Receiver", new BlockTypeInfo(BlockBreakInfo::indestructible())) extends Opaque implements DelayedRedstoneReceiver{
			public static ?\Closure $handler = null;
			public function onRedstoneUpdate(RedstoneEngine $engine) : void{}
			public function onRedstoneScheduledUpdate(RedstoneEngine $engine) : void{
				if(self::$handler !== null){
					(self::$handler)();
				}
			}
		};
		$className = get_class($receiver);
		$className::$handler = function() use (&$fired) : void{
			++$fired;
		};

		$pos = new Vector3(160, 64, 160);
		$world->setBlockAt(160, 64, 160, $receiver, false);
		$engine->schedule($pos, 1);

		$this->loadedChunks["10:10"] = false;
		$engine->tick(1);
		self::assertSame(1, $engine->getUnloadedDelayedCount());

		$engine->cancelSchedule($pos);

		$this->loadedChunks["10:10"] = true;
		$engine->tick(2);

		self::assertSame(0, $fired, "Cancelled schedule must not fire upon chunk reload");
		self::assertSame(0, $engine->getUnloadedDelayedCount(), "Tombstoned parked event must be pruned");
	}

	public function testParkedEventsAwakeningIsMeteredUnderBudget() : void{
		$tickBudget = 50;
		[$engine, $world] = $this->createEnvironment($tickBudget);

		$executions = 0;
		$receiver = new class(new BlockIdentifier(BlockTypeIds::newId()), "Parked Metered Receiver", new BlockTypeInfo(BlockBreakInfo::indestructible())) extends Opaque implements DelayedRedstoneReceiver{
			public static ?\Closure $handler = null;
			public function onRedstoneUpdate(RedstoneEngine $engine) : void{}
			public function onRedstoneScheduledUpdate(RedstoneEngine $engine) : void{
				if(self::$handler !== null){
					(self::$handler)();
				}
			}
		};
		$className = get_class($receiver);
		$className::$handler = function() use (&$executions) : void{
			++$executions;
		};

		$chunkX = 10;
		$chunkZ = 10;
		$totalEvents = 200;

		for($i = 0; $i < $totalEvents; ++$i){
			$x = ($chunkX << 4) + ($i % 16);
			$z = ($chunkZ << 4) + intdiv($i, 16);
			$world->setBlockAt($x, 64, $z, $receiver, false);
			$engine->schedule(new Vector3($x, 64, $z), 1);
		}

		$this->loadedChunks["$chunkX:$chunkZ"] = false;
		for($tick = 1; $tick <= 4; ++$tick){
			$engine->tick($tick);
			self::assertSame($tick * $tickBudget, $engine->getUnloadedDelayedCount(), "Parking progresses within budget");
		}

		self::assertSame(0, $executions, "No events should fire while chunk is unloaded");
		self::assertSame(200, $engine->getUnloadedDelayedCount(), "All 200 events must be parked in unloadedDelayed collection");

		$this->loadedChunks["$chunkX:$chunkZ"] = true;

		$engine->tick(5);
		self::assertSame(50, $executions, "Tick 1 after load must execute only up to tick budget (50 updates)");
		self::assertSame(150, $engine->getUnloadedDelayedCount(), "Remaining 150 events must remain parked when awakening is metered under budget");

		$engine->tick(6);
		self::assertSame(100, $executions, "Tick 2 after load must execute next 50 updates");
		self::assertSame(100, $engine->getUnloadedDelayedCount());

		$engine->tick(7);
		self::assertSame(150, $executions, "Tick 3 after load must execute next 50 updates");
		self::assertSame(50, $engine->getUnloadedDelayedCount());

		$engine->tick(8);
		self::assertSame(200, $executions, "Tick 4 after load must execute final 50 updates");
		self::assertSame(0, $engine->getUnloadedDelayedCount(), "Parked events collection must be completely drained");

		$engine->tick(9);
		self::assertSame(200, $executions, "No further updates should fire once drained");
	}

	public function testParkedEventCancellationIsO1() : void{
		[$engine, $world] = $this->createEnvironment(1000);

		$receiver = new class(new BlockIdentifier(BlockTypeIds::newId()), "O1 Cancel Receiver", new BlockTypeInfo(BlockBreakInfo::indestructible())) extends Opaque implements DelayedRedstoneReceiver{
			public function onRedstoneUpdate(RedstoneEngine $engine) : void{}
			public function onRedstoneScheduledUpdate(RedstoneEngine $engine) : void{}
		};

		$chunkX = 10;
		$chunkZ = 10;
		$chunkKey = "$chunkX:$chunkZ";
		$chunkHash = World::chunkHash($chunkX, $chunkZ);

		$pos = new Vector3(160, 64, 160);
		$blockHash = World::blockHash(160, 64, 160);
		$world->setBlockAt(160, 64, 160, $receiver, false);
		$engine->schedule($pos, 1);

		$this->loadedChunks[$chunkKey] = false;
		$engine->tick(1);

		self::assertSame(1, $engine->getUnloadedDelayedCount(), "Event must be parked in unloaded collection");
		self::assertTrue($engine->isScheduled($pos), "Position must remain marked as scheduled");

		$prop = new \ReflectionProperty(RedstoneEngine::class, "unloadedDelayed");
		/** @var array<int, mixed> $parkedBefore */
		$parkedBefore = $prop->getValue($engine);
		self::assertArrayHasKey($chunkHash, $parkedBefore, "Chunk entry must exist in unloadedDelayed");
		self::assertArrayHasKey(
			$blockHash,
			$parkedBefore[$chunkHash],
			"Parked chunk events must be stored in an associative map keyed by block hash for O(1) pruning"
		);

		$engine->cancelSchedule($pos);

		self::assertSame(0, $engine->getUnloadedDelayedCount(), "Parked event count must drop to 0 immediately upon cancellation");
		self::assertFalse($engine->isScheduled($pos), "Position must not be scheduled after cancellation");

		/** @var array<int, mixed> $parkedAfter */
		$parkedAfter = $prop->getValue($engine);
		self::assertArrayNotHasKey($chunkHash, $parkedAfter, "Chunk entry must be immediately pruned from map in O(1)");
	}

	public function testWorldClearLifecycle() : void{
		[$engine, $world] = $this->createEnvironment(100);

		$receiver = new class(new BlockIdentifier(BlockTypeIds::newId()), "Clear Receiver", new BlockTypeInfo(BlockBreakInfo::indestructible())) extends Opaque implements DelayedRedstoneReceiver{
			public function onRedstoneUpdate(RedstoneEngine $engine) : void{}
			public function onRedstoneScheduledUpdate(RedstoneEngine $engine) : void{}
		};

		$schedPos = new Vector3(10, 64, 10);
		$world->setBlockAt(10, 64, 10, $receiver, false);
		$engine->schedule($schedPos, 20);
		self::assertTrue($engine->isScheduled($schedPos));

		$engine->request(20, 64, 20);
		$engine->request(21, 64, 21);

		$queueProp = new \ReflectionProperty(RedstoneEngine::class, "queue");
		self::assertFalse($queueProp->getValue($engine)->isEmpty(), "Engine queue must have entries");

		for($x = 0; $x < 100; ++$x){
			$world->setBlockAt($x, 63, 50, VanillaBlocks::STONE(), false);
			$world->setBlockAt($x, 64, 50, VanillaBlocks::REDSTONE_WIRE(), false);
		}
		$startWire = $world->getBlockAt(0, 64, 50);
		self::assertInstanceOf(RedstoneWire::class, $startWire);
		$budget = 10;
		$engine->getWires()->update($startWire, $budget);
		self::assertTrue($engine->getWires()->hasDeferred(), "Wire continuations must exist before clear");
		self::assertGreaterThan(0, $engine->getWires()->getContinuationCount());

		$chunkKey = "30:30";
		$this->loadedChunks[$chunkKey] = false;
		$parkedPos = new Vector3(480, 64, 480);
		$world->setBlockAt(480, 64, 480, $receiver, false);
		$engine->schedule($parkedPos, 1);
		$engine->tick(1);
		self::assertGreaterThan(0, $engine->getUnloadedDelayedCount(), "Parked chunk events must exist before clear");

		$engine->powerChanged(new Vector3(5, 64, 5), true);

		$world->onUnload();
		self::assertFalse($world->isLoaded(), "World must report unloaded after onUnload()");

		self::assertTrue($queueProp->getValue($engine)->isEmpty(), "SplQueue must be empty after clear()");
		$queuedProp = new \ReflectionProperty(RedstoneEngine::class, "queued");
		self::assertSame([], $queuedProp->getValue($engine), "Queued map must be empty after clear()");

		$delayedProp = new \ReflectionProperty(RedstoneEngine::class, "delayed");
		self::assertSame([], $delayedProp->getValue($engine), "Delayed array must be empty after clear()");
		$delayedIndexProp = new \ReflectionProperty(RedstoneEngine::class, "delayedIndex");
		self::assertSame([], $delayedIndexProp->getValue($engine), "Delayed index must be empty after clear()");
		$delayedStateProp = new \ReflectionProperty(RedstoneEngine::class, "delayedState");
		self::assertSame([], $delayedStateProp->getValue($engine), "Delayed state must be empty after clear()");
		self::assertFalse($engine->isScheduled($schedPos), "Scheduled position must no longer be scheduled");

		self::assertFalse($engine->getWires()->hasDeferred(), "WireNetwork must have no deferred continuations after clear()");
		self::assertSame(0, $engine->getWires()->getContinuationCount(), "WireNetwork continuation count must be 0 after clear()");
		self::assertNull($engine->getWires()->getContinuationOwner(World::blockHash(0, 64, 50)), "Continuation owner map must be empty after clear()");

		self::assertSame(0, $engine->getUnloadedDelayedCount(), "Unloaded delayed count must be 0 after clear()");
		$unloadedProp = new \ReflectionProperty(RedstoneEngine::class, "unloadedDelayed");
		self::assertSame([], $unloadedProp->getValue($engine), "Unloaded delayed collection must be empty after clear()");

		$lastPoweredProp = new \ReflectionProperty(RedstoneEngine::class, "lastPowered");
		self::assertSame([], $lastPoweredProp->getValue($engine), "Last powered map must be empty after clear()");
	}

	public function testParkedUnloadedChunksScanningIsBoundedByBudget() : void{
		$isChunkLoadedCalls = 0;
		$checkedChunkCoordinates = [];

		$world = $this->getMockBuilder(World::class)
			->disableOriginalConstructor()
			->onlyMethods(["isChunkLoaded", "isInWorld"])
			->getMock();

		$world->method("isInWorld")->willReturn(true);
		$world->method("isChunkLoaded")->willReturnCallback(function(int $chunkX, int $chunkZ) use (&$isChunkLoadedCalls, &$checkedChunkCoordinates) : bool{
			++$isChunkLoadedCalls;
			$checkedChunkCoordinates[] = [$chunkX, $chunkZ];
			return false;
		});

		$budget = 100;
		$engine = new RedstoneEngine($world, $budget);

		$unloadedProp = new \ReflectionProperty(RedstoneEngine::class, "unloadedDelayed");
		$countProp = new \ReflectionProperty(RedstoneEngine::class, "unloadedDelayedCount");

		$buckets = [];
		for($i = 0; $i < 5000; ++$i){
			$chunkX = $i;
			$chunkZ = 0;
			$chunkHash = World::chunkHash($chunkX, $chunkZ);
			$blockHash = World::blockHash($chunkX << 4, 64, $chunkZ << 4);
			$buckets[$chunkHash] = [$blockHash => 1];
		}
		$unloadedProp->setValue($engine, $buckets);
		$countProp->setValue($engine, 5000);

		self::assertSame(5000, $engine->getUnloadedDelayedCount());
		self::assertSame(5000, count($unloadedProp->getValue($engine)));

		$isChunkLoadedCalls = 0;
		$engine->tick(1);

		self::assertLessThanOrEqual(
			100,
			$isChunkLoadedCalls,
			"isChunkLoaded() must be bounded by min(count(unloadedDelayed), max(32, budget)) = 100"
		);
		self::assertSame(100, $isChunkLoadedCalls);
		self::assertSame(5000, $engine->getUnloadedDelayedCount());

		$isChunkLoadedCalls = 0;
		$engine->tick(2);

		self::assertLessThanOrEqual(100, $isChunkLoadedCalls);
		self::assertSame(100, $isChunkLoadedCalls);
		self::assertSame(5000, $engine->getUnloadedDelayedCount());

		self::assertSame(0, $checkedChunkCoordinates[0][0]);
		self::assertSame(99, $checkedChunkCoordinates[99][0]);
		self::assertSame(100, $checkedChunkCoordinates[100][0]);
		self::assertSame(199, $checkedChunkCoordinates[199][0]);
	}
}
