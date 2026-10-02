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
use pocketmine\block\RedstoneWire;
use pocketmine\block\VanillaBlocks;
use pocketmine\math\Vector3;
use pocketmine\world\World;

final class WireContinuationBudgetTest extends TestCase{

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
		$world->method("isChunkLoaded")->willReturn(true);

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
			foreach([
				[$x + 1, $y, $z], [$x - 1, $y, $z],
				[$x, $y + 1, $z], [$x, $y - 1, $z],
				[$x, $y, $z + 1], [$x, $y, $z - 1]
			] as [$nx, $ny, $nz]){
				$engine->onNeighbourUpdate($world->getBlockAt($nx, $ny, $nz));
			}
		});

		return [$engine, $world, $blocks];
	}

	public function testSharedBudgetEnforcedAcrossAllWireOperations() : void{
		[$engine, $world] = $this->createEnvironment(1000);

		$wireCount = 3000;
		for($x = 0; $x < $wireCount; ++$x){
			$world->setBlockAt($x, 63, 0, VanillaBlocks::STONE(), false);
			$world->setBlockAt($x, 64, 0, VanillaBlocks::REDSTONE_WIRE(), false);
		}

		$startWire = $world->getBlockAt(0, 64, 0);
		self::assertInstanceOf(RedstoneWire::class, $startWire);

		$budget = 500;
		$engine->getWires()->update($startWire, $budget);

		self::assertSame(0, $budget, "Explicit budget reference must be fully exhausted");
		self::assertTrue($engine->getWires()->hasDeferred(), "Wire network must defer when bounded by budget");

		$consumed = $engine->getWires()->processDeferred(400);
		self::assertSame(400, $consumed, "Deferred processing must consume exactly up to the provided slice");
		self::assertTrue($engine->getWires()->hasDeferred(), "Network must remain deferred until fully traversed");
	}

	public function testMultiSeedSameNetworkDoesNotCreateDuplicateContinuations() : void{
		[$engine, $world] = $this->createEnvironment(500);

		$wireCount = 2000;
		for($x = 0; $x < $wireCount; ++$x){
			$world->setBlockAt($x, 63, 0, VanillaBlocks::STONE(), false);
			$world->setBlockAt($x, 64, 0, VanillaBlocks::REDSTONE_WIRE(), false);
		}

		$wireA = $world->getBlockAt(0, 64, 0);
		$wireB = $world->getBlockAt(100, 64, 0);
		self::assertInstanceOf(RedstoneWire::class, $wireA);
		self::assertInstanceOf(RedstoneWire::class, $wireB);

		$budgetA = 300;
		$engine->getWires()->update($wireA, $budgetA);

		self::assertTrue($engine->getWires()->hasDeferred());
		self::assertSame(1, $engine->getWires()->getContinuationCount(), "First seed creates exactly 1 continuation");

		$ownerA = $engine->getWires()->getContinuationOwner(World::blockHash(100, 64, 0));
		self::assertNotNull($ownerA, "Discovered node must have continuation ownership recorded");

		$budgetB = 300;
		$engine->getWires()->update($wireB, $budgetB);
		self::assertSame(300, $budgetB, "Second seed within owned continuation must not perform duplicate traversal");
		self::assertSame(1, $engine->getWires()->getContinuationCount(), "No duplicate continuation should be created");
	}

	public function testFairnessBetweenDeferredLargeNetworkAndSmallNetwork() : void{
		[$engine, $world] = $this->createEnvironment(1000);

		$largeCount = 5000;
		for($x = 0; $x < $largeCount; ++$x){
			$world->setBlockAt($x, 63, 0, VanillaBlocks::STONE(), false);
			$world->setBlockAt($x, 64, 0, VanillaBlocks::REDSTONE_WIRE(), false);
		}

		$smallCount = 10;
		for($x = 0; $x < $smallCount; ++$x){
			$world->setBlockAt($x, 63, 20, VanillaBlocks::STONE(), false);
			$world->setBlockAt($x, 64, 20, VanillaBlocks::REDSTONE_WIRE(), false);
		}

		$largeWire = $world->getBlockAt(0, 64, 0);
		self::assertInstanceOf(RedstoneWire::class, $largeWire);
		$engine->getWires()->update($largeWire);
		self::assertTrue($engine->getWires()->hasDeferred());

		$world->setBlockAt(0, 63, 20, VanillaBlocks::REDSTONE(), true);

		$engine->tick(1);

		/** @var RedstoneWire $smallEndWire */
		$smallEndWire = $world->getBlockAt(5, 64, 20);
		self::assertSame(10, $smallEndWire->getOutputSignalStrength(), "Small network must settle without starvation during huge network deferral");
		self::assertTrue($engine->getWires()->hasDeferred(), "Large network should still have pending deferred slices");
	}

	public function testContinuationOwnershipReleaseOnSettleAndInvalidation() : void{
		[$engine, $world] = $this->createEnvironment(1000);

		$wireCount = 1500;
		for($x = 0; $x < $wireCount; ++$x){
			$world->setBlockAt($x, 63, 0, VanillaBlocks::STONE(), false);
			$world->setBlockAt($x, 64, 0, VanillaBlocks::REDSTONE_WIRE(), false);
		}

		$wire0 = $world->getBlockAt(0, 64, 0);
		self::assertInstanceOf(RedstoneWire::class, $wire0);

		$budget = 400;
		$engine->getWires()->update($wire0, $budget);
		self::assertTrue($engine->getWires()->hasDeferred());

		$hash0 = World::blockHash(0, 64, 0);
		$owner = $engine->getWires()->getContinuationOwner($hash0);
		self::assertNotNull($owner, "Owned wire must return integer continuation ID");

		$world->setBlockAt(10, 64, 0, VanillaBlocks::AIR(), true);
		self::assertNull($engine->getWires()->getContinuationOwner($hash0), "Ownership must be released upon mutation invalidation");
		self::assertFalse($engine->getWires()->hasDeferred(), "Invalidated continuation must be removed");

		$engine->getWires()->update($wire0);
		while($engine->getWires()->hasDeferred()){
			$engine->tick($engine->getCurrentTick() + 1);
		}

		self::assertNull($engine->getWires()->getContinuationOwner($hash0), "Ownership must be released after network settlement");
	}

	public function testWireUpdateDuringTickWithExhaustedBudgetDefersImmediately() : void{
		[$engine, $world] = $this->createEnvironment(1);

		for($x = 0; $x < 20; ++$x){
			$world->setBlockAt($x, 63, 0, VanillaBlocks::STONE(), false);
			$world->setBlockAt($x, 64, 0, VanillaBlocks::REDSTONE_WIRE(), false);
		}

		$wire0 = $world->getBlockAt(0, 64, 0);
		self::assertInstanceOf(RedstoneWire::class, $wire0);

		$engine->onNeighbourUpdate($wire0);
		$engine->tick(1);

		self::assertTrue($engine->getWires()->hasDeferred(), "Network must be deferred when engine budget is exhausted");
		$farWire = $world->getBlockAt(15, 64, 0);
		self::assertInstanceOf(RedstoneWire::class, $farWire);
		self::assertSame(0, $farWire->getOutputSignalStrength(), "Far wire must not have settled in tick 1 due to zero budget");
	}

	public function testSettlePhaseRespectsBudgetAndSlicesWork() : void{
		[$engine, $world] = $this->createEnvironment(50);

		$wireCount = 500;
		for($x = 0; $x < $wireCount; ++$x){
			$world->setBlockAt($x, 63, 0, VanillaBlocks::STONE(), false);
			$world->setBlockAt($x, 64, 0, VanillaBlocks::REDSTONE_WIRE(), false);
		}

		$startWire = $world->getBlockAt(0, 64, 0);
		self::assertInstanceOf(RedstoneWire::class, $startWire);

		$budget = 50;
		$engine->getWires()->update($startWire, $budget);

		self::assertSame(0, $budget, "Initial budget reference of 50 must be fully consumed by the first slice");
		self::assertTrue($engine->getWires()->hasDeferred(), "Network must not settle in a single step under budget constraint");
		self::assertGreaterThan(0, $engine->getWires()->getContinuationCount(), "Continuation must be registered in WireNetwork");

		$startHash = World::blockHash(0, 64, 0);
		self::assertNotNull($engine->getWires()->getContinuationOwner($startHash), "Continuation ownership must be active");

		$ticks = 0;
		$maxTicks = 100;
		while($engine->getWires()->hasDeferred() && $ticks < $maxTicks){
			++$ticks;
			$engine->tick($ticks);
		}

		self::assertFalse($engine->getWires()->hasDeferred(), "Wire network must eventually settle fully");
		self::assertGreaterThan(20, $ticks, "Settle phases must slice work across multiple ticks under budget constraint rather than settling in 1 burst");
		self::assertNull($engine->getWires()->getContinuationOwner($startHash), "Continuation ownership must be released upon completion");
	}

	public function testSettlePhaseCannotEvadeBudget() : void{
		[$engine, $world] = $this->createEnvironment(10);

		$wireCount = 200;
		for($x = 0; $x < $wireCount; ++$x){
			$world->setBlockAt($x, 63, 0, VanillaBlocks::STONE(), false);
			$world->setBlockAt($x, 64, 0, VanillaBlocks::REDSTONE_WIRE(), false);
		}

		$startWire = $world->getBlockAt(0, 64, 0);
		self::assertInstanceOf(RedstoneWire::class, $startWire);

		$budget = $wireCount - 1;
		$engine->getWires()->update($startWire, $budget);
		self::assertTrue($engine->getWires()->hasDeferred());

		$engine->tick(1);
		self::assertTrue($engine->getWires()->hasDeferred(), "Network must remain deferred across settle phases when remaining budget is small");
	}

	public function testContinuationCollisionDoesNotPerformUnboundedSynchronousMerge() : void{
		[$engine, $world] = $this->createEnvironment(100000);

		for($x = 0; $x < 5000; ++$x){
			$world->setBlockAt($x, 63, 0, VanillaBlocks::STONE(), false);
			$world->setBlockAt($x, 64, 0, VanillaBlocks::REDSTONE_WIRE(), false);
		}

		$wireA = $world->getBlockAt(0, 64, 0);
		$wireB = $world->getBlockAt(4999, 64, 0);
		self::assertInstanceOf(RedstoneWire::class, $wireA);
		self::assertInstanceOf(RedstoneWire::class, $wireB);

		$budgetA = 2499;
		$engine->getWires()->update($wireA, $budgetA);

		$budgetB = 2499;
		$engine->getWires()->update($wireB, $budgetB);

		$wires = $engine->getWires();
		self::assertSame(2, $wires->getContinuationCount(), "Precondition: 2 independent continuations must exist");

		$farBHash = World::blockHash(4999, 64, 0);
		$boundaryAHash = World::blockHash(2499, 64, 0);
		$boundaryBHash = World::blockHash(2500, 64, 0);

		$ownerA = $wires->getContinuationOwner($boundaryAHash);
		$ownerB = $wires->getContinuationOwner($boundaryBHash);

		self::assertNotNull($ownerA);
		self::assertNotNull($ownerB);
		self::assertNotSame($ownerA, $ownerB, "Precondition: boundary wires must belong to distinct continuations");
		self::assertSame($ownerB, $wires->getContinuationOwner($farBHash), "Precondition: far wire belongs to continuation B");

		$consumed = $wires->processDeferred(1);
		self::assertLessThanOrEqual(1, $consumed, "Consumed steps must strictly respect the provided budget of 1");

		self::assertSame(
			$ownerB,
			$wires->getContinuationOwner($farBHash),
			"A merge of 2500 wires must not synchronously rewrite far wire ownership outside the budget"
		);

		self::assertGreaterThan(
			1,
			$wires->getContinuationCount(),
			"Continuation B must not be completely dissolved in a single budget step"
		);
	}

	public function testContinuationInvalidationFreesAllOwnedNodesAcrossMerges() : void{
		[$engine, $world] = $this->createEnvironment(100000);

		for($x = 0; $x < 200; ++$x){
			$world->setBlockAt($x, 63, 0, VanillaBlocks::STONE(), false);
			$world->setBlockAt($x, 64, 0, VanillaBlocks::REDSTONE_WIRE(), false);
		}

		$wireA = $world->getBlockAt(0, 64, 0);
		$wireB = $world->getBlockAt(199, 64, 0);
		self::assertInstanceOf(RedstoneWire::class, $wireA);
		self::assertInstanceOf(RedstoneWire::class, $wireB);

		$budgetA = 99;
		$engine->getWires()->update($wireA, $budgetA);

		$budgetB = 99;
		$engine->getWires()->update($wireB, $budgetB);

		$wires = $engine->getWires();
		self::assertSame(2, $wires->getContinuationCount());

		// Step 1 to trigger collision and merge
		$wires->processDeferred(1);

		// Now break a wire to trigger invalidation
		$breakHash = World::blockHash(50, 64, 0);
		$wires->invalidate($breakHash);

		// Every single wire must be freed from continuationOwner
		for($x = 0; $x < 200; ++$x){
			$h = World::blockHash($x, 64, 0);
			self::assertNull(
				$wires->getContinuationOwner($h),
				"Wire at x=$x must have its continuation ownership released after invalidation"
			);
		}
		self::assertSame(0, $wires->getContinuationCount());
	}
}
