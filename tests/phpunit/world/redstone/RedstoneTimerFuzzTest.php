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
use function gc_collect_cycles;
use function gc_mem_caches;
use function intdiv;

final class RedstoneTimerFuzzTest extends TestCase{

	/** @var array<string, bool> */
	private array $loadedChunks = [];

	public function setUp() : void{
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
				$b = clone $blocks[$key];
				$b->position($world, $x, $y, $z);
				return $b;
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

	private function makeFuzzReceiver(?\Closure $onUpdate = null, int $customStateId = 0) : Block{
		return new class(
			new BlockIdentifier(BlockTypeIds::newId()),
			"Fuzz Receiver",
			new BlockTypeInfo(BlockBreakInfo::indestructible()),
			$onUpdate,
			$customStateId
		) extends Opaque implements DelayedRedstoneReceiver{
			public function __construct(
				BlockIdentifier $id,
				string $name,
				BlockTypeInfo $type,
				private ?\Closure $onUpdate,
				private int $customStateId
			){
				parent::__construct($id, $name, $type);
			}

			public function getStateId() : int{
				return $this->customStateId !== 0 ? $this->customStateId : parent::getStateId();
			}

			public function onRedstoneUpdate(RedstoneEngine $engine) : void{}

			public function onRedstoneScheduledUpdate(RedstoneEngine $engine) : void{
				if($this->onUpdate !== null){
					($this->onUpdate)($this, $engine);
				}
			}
		};
	}

	public function test10kScheduledUpdatesSameTick() : void{
		$budget = 1000;
		[$engine, $world] = $this->createEnvironment($budget);

		$total = 10000;
		$executedByCoord = [];
		$tickCounts = [];

		$handler = function(Block $b, RedstoneEngine $eng) use (&$executedByCoord, &$tickCounts) : void{
			$pos = $b->getPosition();
			$key = $pos->x . ":" . $pos->y . ":" . $pos->z;
			$executedByCoord[$key] = ($executedByCoord[$key] ?? 0) + 1;
			$tick = $eng->getCurrentTick();
			$tickCounts[$tick] = ($tickCounts[$tick] ?? 0) + 1;
		};

		$receiver = $this->makeFuzzReceiver($handler);

		for($i = 0; $i < $total; ++$i){
			$x = $i % 100;
			$z = intdiv($i, 100);
			$pos = new Vector3($x, 64, $z);
			$world->setBlockAt($x, 64, $z, $receiver, false);
			$engine->schedule($pos, 2);
		}

		$engine->tick(1);
		self::assertCount(0, $executedByCoord);

		for($t = 2; $t <= 11; ++$t){
			$engine->tick($t);
			self::assertSame($budget, $tickCounts[$t] ?? 0);
		}

		$engine->tick(12);
		self::assertArrayNotHasKey(12, $tickCounts);
		self::assertCount($total, $executedByCoord);

		foreach($executedByCoord as $coord => $count){
			self::assertSame(1, $count, "Coordinate $coord fired multiple times");
		}

		$delayedProp = new \ReflectionProperty(RedstoneScheduler::class, "delayed");
		self::assertSame([], $delayedProp->getValue($engine->getScheduler()));
		$delayedIndexProp = new \ReflectionProperty(RedstoneScheduler::class, "delayedIndex");
		self::assertSame([], $delayedIndexProp->getValue($engine->getScheduler()));
	}

	public function testStaggeredUpdatesAcrossManyTicks() : void{
		$budget = 100;
		[$engine, $world] = $this->createEnvironment($budget);

		$totalTicks = 100;
		$perTick = 100;
		$firedPerTick = [];

		$handler = function(Block $b, RedstoneEngine $eng) use (&$firedPerTick) : void{
			$tick = $eng->getCurrentTick();
			$firedPerTick[$tick] = ($firedPerTick[$tick] ?? 0) + 1;
		};

		$receiver = $this->makeFuzzReceiver($handler);

		for($t = 1; $t <= $totalTicks; ++$t){
			for($i = 0; $i < $perTick; ++$i){
				$x = $i;
				$z = $t;
				$pos = new Vector3($x, 64, $z);
				$world->setBlockAt($x, 64, $z, $receiver, false);
				$engine->schedule($pos, $t);
			}
		}

		for($t = 1; $t <= $totalTicks; ++$t){
			$engine->tick($t);
			self::assertSame($perTick, $firedPerTick[$t] ?? 0);
		}

		$engine->tick($totalTicks + 1);
		self::assertArrayNotHasKey($totalTicks + 1, $firedPerTick);

		$delayedProp = new \ReflectionProperty(RedstoneScheduler::class, "delayed");
		self::assertSame([], $delayedProp->getValue($engine->getScheduler()));
	}

	public function testStaggeredUpdatesUnderConstrainedBudgetAccumulateAndDrain() : void{
		$budget = 50;
		[$engine, $world] = $this->createEnvironment($budget);

		$firedPerTick = [];
		$handler = function(Block $b, RedstoneEngine $eng) use (&$firedPerTick) : void{
			$tick = $eng->getCurrentTick();
			$firedPerTick[$tick] = ($firedPerTick[$tick] ?? 0) + 1;
		};

		$receiver = $this->makeFuzzReceiver($handler);
		$totalTicks = 20;
		$perTick = 100;

		for($t = 1; $t <= $totalTicks; ++$t){
			for($i = 0; $i < $perTick; ++$i){
				$x = $i;
				$z = $t;
				$pos = new Vector3($x, 64, $z);
				$world->setBlockAt($x, 64, $z, $receiver, false);
				$engine->schedule($pos, $t);
			}
		}

		$totalFired = 0;
		for($t = 1; $t <= 40; ++$t){
			$engine->tick($t);
			$fired = $firedPerTick[$t] ?? 0;
			self::assertLessThanOrEqual($budget, $fired);
			$totalFired += $fired;
		}

		self::assertSame($totalTicks * $perTick, $totalFired);
	}

	public function testRepeatedReschedulingSameCoordinateScenarios() : void{
		[$engine, $world] = $this->createEnvironment(100);
		$pos = new Vector3(10, 64, 10);
		$fired = [];

		$handler = function(Block $b, RedstoneEngine $eng) use (&$fired) : void{
			$fired[] = [
				"tick" => $eng->getCurrentTick(),
				"state" => $b->getStateId()
			];
		};

		$recA = $this->makeFuzzReceiver($handler, 1001);
		$world->setBlockAt(10, 64, 10, $recA, false);

		$engine->tick(1);
		$engine->schedule($pos, 5);

		$engine->tick(2);
		$engine->schedule($pos, 5);

		$engine->tick(3);
		$engine->schedule($pos, 1);

		for($t = 4; $t <= 8; ++$t){
			$engine->tick($t);
		}

		self::assertCount(1, $fired);
		self::assertSame(6, $fired[0]["tick"]);
		self::assertSame(1001, $fired[0]["state"]);

		$fired = [];
		$engine->tick(10);
		$engine->schedule($pos, 8);

		$engine->tick(12);
		$recB = $this->makeFuzzReceiver($handler, 2002);
		$world->setBlockAt(10, 64, 10, $recB, true);
		$engine->schedule($pos, 2);

		for($t = 13; $t <= 20; ++$t){
			$engine->tick($t);
		}

		self::assertCount(1, $fired);
		self::assertSame(14, $fired[0]["tick"]);
		self::assertSame(2002, $fired[0]["state"]);
	}

	public function testCancellationStormsPruneCleanly() : void{
		$budget = 1000;
		[$engine, $world] = $this->createEnvironment($budget);

		$fired = 0;
		$handler = function() use (&$fired) : void{
			++$fired;
		};
		$receiver = $this->makeFuzzReceiver($handler);

		$stormCount = 5000;
		$positions = [];
		for($i = 0; $i < $stormCount; ++$i){
			$x = $i % 100;
			$z = intdiv($i, 100);
			$pos = new Vector3($x, 64, $z);
			$positions[] = $pos;
			$world->setBlockAt($x, 64, $z, $receiver, false);
			$engine->schedule($pos, 4);
		}

		$engine->tick(1);
		$engine->tick(2);

		for($i = 0; $i < $stormCount; ++$i){
			$engine->cancelSchedule($positions[$i]);
		}

		for($t = 3; $t <= 10; ++$t){
			$engine->tick($t);
		}

		self::assertSame(0, $fired);
	}

	public function testCancellationStormWithInterleavedSurvivors() : void{
		$budget = 500;
		[$engine, $world] = $this->createEnvironment($budget);

		$survivorFired = [];
		$cancelledFired = [];

		$total = 6000;
		for($i = 0; $i < $total; ++$i){
			$isCancelled = ($i % 2 === 0);
			$handler = function(Block $b) use ($isCancelled, $i, &$survivorFired, &$cancelledFired) : void{
				if($isCancelled){
					$cancelledFired[] = $i;
				}else{
					$survivorFired[] = $i;
				}
			};
			$rec = $this->makeFuzzReceiver($handler);
			$x = $i % 100;
			$z = intdiv($i, 100);
			$world->setBlockAt($x, 64, $z, $rec, false);
			$engine->schedule(new Vector3($x, 64, $z), 2);
		}

		for($i = 0; $i < $total; $i += 2){
			$engine->cancelSchedule(new Vector3($i % 100, 64, intdiv($i, 100)));
		}

		for($t = 1; $t <= 15; ++$t){
			$engine->tick($t);
		}

		self::assertCount(0, $cancelledFired);
		self::assertCount(3000, $survivorFired);
	}

	public function testReplacementBeforeDueTickInvalidates() : void{
		[$engine, $world] = $this->createEnvironment(100);
		$pos = new Vector3(5, 64, 5);

		$fired = 0;
		$handler = function() use (&$fired) : void{
			++$fired;
		};

		$rec = $this->makeFuzzReceiver($handler, 5001);
		$world->setBlockAt(5, 64, 5, $rec, false);
		$engine->schedule($pos, 4);

		$engine->tick(1);
		$world->setBlockAt(5, 64, 5, VanillaBlocks::AIR(), true);

		for($t = 2; $t <= 6; ++$t){
			$engine->tick($t);
		}
		self::assertSame(0, $fired);

		$world->setBlockAt(5, 64, 5, $rec, false);
		$engine->schedule($pos, 3);
		$engine->tick(7);
		$world->setBlockAt(5, 64, 5, VanillaBlocks::AIR(), false);

		for($t = 8; $t <= 12; ++$t){
			$engine->tick($t);
		}
		self::assertSame(0, $fired);

		$world->setBlockAt(5, 64, 5, $rec, false);
		$engine->schedule($pos, 3);
		$engine->tick(13);
		$differentState = $this->makeFuzzReceiver($handler, 9999);
		$world->setBlockAt(5, 64, 5, $differentState, false);

		for($t = 14; $t <= 18; ++$t){
			$engine->tick($t);
		}
		self::assertSame(0, $fired);
	}

	public function testUnloadBeforeDueTickAndReloadAfterDueTickMetered() : void{
		$budget = 100;
		[$engine, $world] = $this->createEnvironment($budget);

		$firedCount = 0;
		$handler = function() use (&$firedCount) : void{
			++$firedCount;
		};
		$rec = $this->makeFuzzReceiver($handler);

		$total = 300;
		$chunkX = 8;
		$chunkZ = 8;
		$chunkKey = "$chunkX:$chunkZ";

		for($i = 0; $i < $total; ++$i){
			$x = ($chunkX << 4) + ($i % 16);
			$z = ($chunkZ << 4) + (intdiv($i, 16) % 16);
			$y = 64 + intdiv($i, 256);
			$pos = new Vector3($x, $y, $z);
			$world->setBlockAt($x, $y, $z, $rec, false);
			$engine->schedule($pos, 5);
		}

		$this->loadedChunks[$chunkKey] = false;

		for($t = 1; $t <= 8; ++$t){
			$engine->tick($t);
		}

		self::assertSame(0, $firedCount);
		self::assertSame($total, $engine->getUnloadedDelayedCount());

		$this->loadedChunks[$chunkKey] = true;

		$engine->tick(9);
		self::assertSame(100, $firedCount);
		self::assertSame(200, $engine->getUnloadedDelayedCount());

		$engine->tick(10);
		self::assertSame(200, $firedCount);
		self::assertSame(100, $engine->getUnloadedDelayedCount());

		$engine->tick(11);
		self::assertSame(300, $firedCount);
		self::assertSame(0, $engine->getUnloadedDelayedCount());

		$engine->tick(12);
		self::assertSame(300, $firedCount);
	}

	public function testManyUnloadedChunkBucketsRotationAndSimultaneousReload() : void{
		$budget = 100;
		[$engine, $world] = $this->createEnvironment($budget);

		$fired = 0;
		$handler = function() use (&$fired) : void{
			++$fired;
		};
		$rec = $this->makeFuzzReceiver($handler);

		$numChunks = 5000;
		$timersPerChunk = 2;
		$totalTimers = $numChunks * $timersPerChunk;

		$unloadedProp = new \ReflectionProperty(RedstoneScheduler::class, "unloadedDelayed");
		$countProp = new \ReflectionProperty(RedstoneScheduler::class, "unloadedDelayedCount");
		$delayedIndexProp = new \ReflectionProperty(RedstoneScheduler::class, "delayedIndex");

		$buckets = [];
		$index = [];
		for($c = 0; $c < $numChunks; ++$c){
			$cx = $c;
			$cz = 0;
			$cHash = World::chunkHash($cx, $cz);
			$this->loadedChunks["$cx:$cz"] = false;
			$chunkEvents = [];
			for($t = 0; $t < $timersPerChunk; ++$t){
				$bHash = World::blockHash(($cx << 4) + $t, 64, ($cz << 4) + $t);
				$chunkEvents[$bHash] = 1;
				$index[$bHash] = 1;
			}
			$buckets[$cHash] = $chunkEvents;
		}

		$unloadedProp->setValue($engine->getScheduler(), $buckets);
		$countProp->setValue($engine->getScheduler(), $totalTimers);
		$delayedIndexProp->setValue($engine->getScheduler(), $index);

		for($t = 1; $t <= 5; ++$t){
			$engine->tick($t);
			self::assertSame(0, $fired);
			self::assertSame($totalTimers, $engine->getUnloadedDelayedCount());
		}

		$reloadedCount = 20;
		$expectedToFire = $reloadedCount * $timersPerChunk;

		for($c = 0; $c < $reloadedCount; ++$c){
			$cx = $c;
			$cz = 0;
			$this->loadedChunks["$cx:$cz"] = true;
			for($t = 0; $t < $timersPerChunk; ++$t){
				$x = ($cx << 4) + $t;
				$z = ($cz << 4) + $t;
				$world->setBlockAt($x, 64, $z, $rec, false);
			}
		}

		for($tick = 6; $tick < 506 && $engine->getUnloadedDelayedCount() > ($totalTimers - $expectedToFire); ++$tick){
			$engine->tick($tick);
		}

		self::assertSame($expectedToFire, $fired);
		self::assertSame($totalTimers - $expectedToFire, $engine->getUnloadedDelayedCount());
	}

	public function testReplacementWhileParkedInUnloadedChunk() : void{
		[$engine, $world] = $this->createEnvironment(100);

		$fired = 0;
		$handler = function() use (&$fired) : void{
			++$fired;
		};
		$rec = $this->makeFuzzReceiver($handler, 777);

		$chunkX = 15;
		$chunkZ = 15;
		$chunkKey = "$chunkX:$chunkZ";
		$pos1 = new Vector3(($chunkX << 4) + 1, 64, ($chunkZ << 4) + 1);
		$pos2 = new Vector3(($chunkX << 4) + 2, 64, ($chunkZ << 4) + 2);

		$world->setBlockAt($pos1->x, $pos1->y, $pos1->z, $rec, false);
		$world->setBlockAt($pos2->x, $pos2->y, $pos2->z, $rec, false);

		$engine->schedule($pos1, 1);
		$engine->schedule($pos2, 1);

		$this->loadedChunks[$chunkKey] = false;
		$engine->tick(1);

		self::assertSame(2, $engine->getUnloadedDelayedCount());
		self::assertTrue($engine->isScheduled($pos1));
		self::assertTrue($engine->isScheduled($pos2));

		$engine->cancelSchedule($pos1);
		self::assertSame(1, $engine->getUnloadedDelayedCount());
		self::assertFalse($engine->isScheduled($pos1));
		self::assertTrue($engine->isScheduled($pos2));

		$this->loadedChunks[$chunkKey] = true;
		$engine->tick(2);

		self::assertSame(1, $fired);
		self::assertSame(0, $engine->getUnloadedDelayedCount());
	}

	public function testWorldUnloadMemoryDrainAndZeroLeak() : void{
		[$engine, $world] = $this->createEnvironment(500);

		$rec = $this->makeFuzzReceiver(null, 444);

		for($i = 0; $i < 2000; ++$i){
			$pos = new Vector3($i % 50, 64, intdiv($i, 50));
			$world->setBlockAt($pos->x, $pos->y, $pos->z, $rec, false);
			$engine->schedule($pos, 100);
			$engine->request($pos->x, $pos->y, $pos->z);
		}

		$unloadedChunkKey = "50:50";
		for($i = 0; $i < 500; ++$i){
			$pos = new Vector3((50 << 4) + ($i % 16), 64 + intdiv($i, 256), (50 << 4) + (intdiv($i, 16) % 16));
			$world->setBlockAt($pos->x, $pos->y, $pos->z, $rec, false);
			$engine->schedule($pos, 1);
		}
		$this->loadedChunks[$unloadedChunkKey] = false;
		$engine->tick(1);
		self::assertGreaterThan(0, $engine->getUnloadedDelayedCount());
		self::assertGreaterThan(0, $engine->getProcessedCount());
		for($tick = 2; $tick <= 4; ++$tick){
			$engine->tick($tick);
		}
		self::assertSame(500, $engine->getUnloadedDelayedCount());

		for($x = 0; $x < 50; ++$x){
			$world->setBlockAt($x, 63, 0, VanillaBlocks::STONE(), false);
			$world->setBlockAt($x, 64, 0, VanillaBlocks::REDSTONE_WIRE(), false);
		}
		$wire = $world->getBlockAt(0, 64, 0);
		if($wire instanceof RedstoneWire){
			$wireBudget = 5;
			$engine->getWires()->update($wire, $wireBudget);
		}

		$world->onUnload();

		gc_mem_caches();
		gc_collect_cycles();

		$queueProp = new \ReflectionProperty(RedstoneEngine::class, "queue");
		self::assertTrue($queueProp->getValue($engine)->isEmpty());

		$queuedProp = new \ReflectionProperty(RedstoneEngine::class, "queued");
		self::assertSame([], $queuedProp->getValue($engine));

		$delayedProp = new \ReflectionProperty(RedstoneScheduler::class, "delayed");
		self::assertSame([], $delayedProp->getValue($engine->getScheduler()));

		$delayedIndexProp = new \ReflectionProperty(RedstoneScheduler::class, "delayedIndex");
		self::assertSame([], $delayedIndexProp->getValue($engine->getScheduler()));

		$delayedStateProp = new \ReflectionProperty(RedstoneScheduler::class, "delayedState");
		self::assertSame([], $delayedStateProp->getValue($engine->getScheduler()));

		$unloadedProp = new \ReflectionProperty(RedstoneScheduler::class, "unloadedDelayed");
		self::assertSame([], $unloadedProp->getValue($engine->getScheduler()));
		self::assertSame(0, $engine->getUnloadedDelayedCount());

		self::assertFalse($engine->getWires()->hasDeferred());
		self::assertSame(0, $engine->getWires()->getContinuationCount());
	}
}
