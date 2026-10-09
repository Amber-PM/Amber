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
use pocketmine\timings\TimingsHandler;
use pocketmine\world\format\io\WritableWorldProvider;
use pocketmine\world\generator\executor\GeneratorExecutor;
use pocketmine\world\World;
use pocketmine\world\WorldTimings;
use function get_class;
use function property_exists;

final class RedstoneLifecycleIntegrationTest extends TestCase{

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

	public function testWorldUnloadClearsAllEngineSubsystems() : void{
		[$engine, $world] = $this->createEnvironment(100);

		$receiver = new class(new BlockIdentifier(BlockTypeIds::newId()), "Clear Receiver", new BlockTypeInfo(BlockBreakInfo::indestructible())) extends Opaque implements DelayedRedstoneReceiver{
			public function onRedstoneUpdate(RedstoneEngine $engine) : void{}
			public function onRedstoneScheduledUpdate(RedstoneEngine $engine) : void{}
		};

		// 1. Scheduled delayed array & indexes
		$schedPos = new Vector3(10, 64, 10);
		$world->setBlockAt(10, 64, 10, $receiver, false);
		$engine->schedule($schedPos, 20);
		self::assertTrue($engine->isScheduled($schedPos));

		// 2. Engine update queue
		$engine->request(20, 64, 20);
		$engine->request(21, 64, 21);
		$queueProp = new \ReflectionProperty(RedstoneEngine::class, "queue");
		self::assertFalse($queueProp->getValue($engine)->isEmpty());

		// 3. Wire continuations
		for($x = 0; $x < 100; ++$x){
			$world->setBlockAt($x, 63, 50, VanillaBlocks::STONE(), false);
			$world->setBlockAt($x, 64, 50, VanillaBlocks::REDSTONE_WIRE(), false);
		}
		$startWire = $world->getBlockAt(0, 64, 50);
		self::assertInstanceOf(RedstoneWire::class, $startWire);
		$budget = 10;
		$engine->getWires()->update($startWire, $budget);
		self::assertTrue($engine->getWires()->hasDeferred());
		self::assertGreaterThan(0, $engine->getWires()->getContinuationCount());

		// 4. Parked chunk events
		$chunkKey = "30:30";
		$parkedPos = new Vector3(480, 64, 480);
		$world->setBlockAt(480, 64, 480, $receiver, false);
		$engine->schedule($parkedPos, 1);
		$this->loadedChunks[$chunkKey] = false;
		$engine->tick(1);
		self::assertGreaterThan(0, $engine->getUnloadedDelayedCount());

		// 5. Burnout states
		$burnoutPos = new Vector3(5, 64, 5);
		for($i = 0; $i < 8; ++$i){
			$engine->getTorchBurnout()->recordToggle($burnoutPos, $engine);
		}
		self::assertTrue($engine->getTorchBurnout()->isBurntOut($burnoutPos, 1));

		// 6. Last powered map
		$engine->powerChanged(new Vector3(2, 64, 2), true);

		$world->onUnload();
		self::assertFalse($world->isLoaded(), "World must report unloaded after onUnload()");
		self::assertNull($world->getRedstoneEngine(), "World redstone reference must be nullified to sever cycle");

		self::assertTrue($queueProp->getValue($engine)->isEmpty(), "SplQueue must be empty after clear()");
		$queuedProp = new \ReflectionProperty(RedstoneEngine::class, "queued");
		self::assertSame([], $queuedProp->getValue($engine));

		$delayedProp = new \ReflectionProperty(RedstoneScheduler::class, "delayed");
		self::assertSame([], $delayedProp->getValue($engine->getScheduler()));
		$delayedIndexProp = new \ReflectionProperty(RedstoneScheduler::class, "delayedIndex");
		self::assertSame([], $delayedIndexProp->getValue($engine->getScheduler()));
		$delayedStateProp = new \ReflectionProperty(RedstoneScheduler::class, "delayedState");
		self::assertSame([], $delayedStateProp->getValue($engine->getScheduler()));
		self::assertFalse($engine->isScheduled($schedPos));

		self::assertFalse($engine->getWires()->hasDeferred(), "Wire network must have no deferred continuations");
		self::assertSame(0, $engine->getWires()->getContinuationCount());
		self::assertNull($engine->getWires()->getContinuationOwner(World::blockHash(0, 64, 50)));

		self::assertSame(0, $engine->getUnloadedDelayedCount());
		$unloadedProp = new \ReflectionProperty(RedstoneScheduler::class, "unloadedDelayed");
		self::assertSame([], $unloadedProp->getValue($engine->getScheduler()));

		self::assertFalse($engine->getTorchBurnout()->isBurntOut($burnoutPos, 1));
		$burnoutRef = new \ReflectionClass(TorchBurnout::class);
		$togglesProp = $burnoutRef->getProperty("toggles");
		$burntOutProp = $burnoutRef->getProperty("burntOut");
		self::assertSame([], $togglesProp->getValue($engine->getTorchBurnout()));
		self::assertSame([], $burntOutProp->getValue($engine->getTorchBurnout()));

		$lastPoweredProp = new \ReflectionProperty(RedstoneEngine::class, "lastPowered");
		self::assertSame([], $lastPoweredProp->getValue($engine));
	}

	public function testChunkUnloadReloadCycleSurvivesAndReParks() : void{
		[$engine, $world] = $this->createEnvironment(1000);

		$executions = 0;
		$receiver = new class(new BlockIdentifier(BlockTypeIds::newId()), "Cycle Receiver", new BlockTypeInfo(BlockBreakInfo::indestructible())) extends Opaque implements DelayedRedstoneReceiver{
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

		$chunkX = 12;
		$chunkZ = 12;
		$chunkKey = "$chunkX:$chunkZ";
		$pos1 = new Vector3(192, 64, 192);

		$world->setBlockAt(192, 64, 192, $receiver, false);
		$engine->schedule($pos1, 1);

		$this->loadedChunks[$chunkKey] = false;
		$engine->tick(1);

		self::assertSame(0, $executions, "Timer must not execute while chunk is unloaded");
		self::assertSame(1, $engine->getUnloadedDelayedCount(), "Timer must be parked");

		$engine->tick(2);
		self::assertSame(0, $executions, "Parked timer must survive unloaded chunk across ticks");
		self::assertSame(1, $engine->getUnloadedDelayedCount());

		$this->loadedChunks[$chunkKey] = true;
		$engine->tick(3);

		self::assertSame(1, $executions, "Parked timer must dispatch once chunk is reloaded");
		self::assertSame(0, $engine->getUnloadedDelayedCount());

		$pos2 = new Vector3(193, 64, 192);
		$world->setBlockAt(193, 64, 192, $receiver, false);
		$engine->schedule($pos2, 2);

		$this->loadedChunks[$chunkKey] = false;
		$engine->tick(4);
		$engine->tick(5);

		self::assertSame(1, $executions, "Second timer must not execute while chunk is unloaded again");
		self::assertSame(1, $engine->getUnloadedDelayedCount(), "Second timer must be re-parked in unloadedDelayed");

		$this->loadedChunks[$chunkKey] = true;
		$engine->tick(6);

		self::assertSame(2, $executions, "Second parked timer must dispatch after second chunk reload");
		self::assertSame(0, $engine->getUnloadedDelayedCount());
	}

	public function testStateBoundTimerInvalidatedAcrossRapidReplacements() : void{
		[$engine, $world] = $this->createEnvironment();
		$pos = new Vector3(0, 64, 0);

		$fired = [];

		$makeReceiver = function(string $name) use (&$fired) : Block{
			return new class(new BlockIdentifier(BlockTypeIds::newId()), $name, new BlockTypeInfo(BlockBreakInfo::indestructible()), $name, $fired) extends Opaque implements DelayedRedstoneReceiver{
				public function __construct(
					BlockIdentifier $id,
					string $name,
					BlockTypeInfo $type,
					private string $label,
					private array &$fired
				){
					parent::__construct($id, $name, $type);
				}
				public function onRedstoneUpdate(RedstoneEngine $engine) : void{}
				public function onRedstoneScheduledUpdate(RedstoneEngine $engine) : void{
					$this->fired[] = $this->label;
				}
			};
		};

		$recA = $makeReceiver("A");
		$recB = $makeReceiver("B");
		$recC = $makeReceiver("C");
		$recD = $makeReceiver("D");

		$world->setBlockAt(0, 64, 0, $recA, false);
		$engine->schedule($pos, 10);

		$engine->tick(1);
		$world->setBlockAt(0, 64, 0, $recB, true);
		$engine->schedule($pos, 5);

		$engine->tick(2);
		$world->setBlockAt(0, 64, 0, $recC, true);
		$engine->schedule($pos, 4);

		$engine->tick(3);
		$world->setBlockAt(0, 64, 0, $recD, true);
		$engine->schedule($pos, 3);

		$engine->tick(4);
		$engine->tick(5);
		self::assertSame([], $fired, "No updates should have fired before due tick 6");

		$engine->tick(6);
		self::assertSame(["D"], $fired, "Only active block (D) must fire; stale schedules A, B, C must be discarded");

		for($t = 7; $t <= 12; ++$t){
			$engine->tick($t);
		}
		self::assertSame(["D"], $fired, "Stale schedule A must never fire even when its due tick arrives");
	}

	public function testWorldTimingsContainsRedstoneTimer() : void{
		$world = $this->createMock(World::class);
		$world->method("getFolderName")->willReturn("TestWorld");
		$timings = new WorldTimings($world);

		self::assertTrue(
			property_exists($timings, "redstone"),
			"WorldTimings must contain public TimingsHandler property \$redstone"
		);
		self::assertInstanceOf(
			TimingsHandler::class,
			$timings->redstone,
			"WorldTimings::\$redstone must be an instance of TimingsHandler"
		);

		$timings->redstone->startTiming();
		$timings->redstone->stopTiming();
	}
}
