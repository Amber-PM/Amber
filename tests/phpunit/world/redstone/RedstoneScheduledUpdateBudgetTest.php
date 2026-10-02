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
			"notifyNeighbourBlockUpdate"
		])->getMock();

		$world->method("isInWorld")->willReturn(true);
		$world->method("isChunkLoaded")->willReturnCallback(function(int $chunkX, int $chunkZ) : bool{
			$key = "$chunkX:$chunkZ";
			return $this->loadedChunks[$key] ?? true;
		});

		$engine = new RedstoneEngine($world, $maxUpdatesPerTick);

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
}
