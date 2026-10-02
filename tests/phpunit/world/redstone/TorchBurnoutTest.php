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
use pocketmine\block\RedstoneTorch;
use pocketmine\block\VanillaBlocks;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\world\World;

final class TorchBurnoutTest extends TestCase{
	/** @var array<string, bool> */
	private array $loadedChunks = [];

	public function testFeedbackAnalysisDoesNotDelayTorchExtinguish() : void{
		[$engine, $world] = $this->createEnvironment();
		$this->placeFeedbackTorch($world, 8);
		$engine->tick(20);
		$torch = $world->getBlockAt(8, 64, 8);
		self::assertInstanceOf(RedstoneTorch::class, $torch);
		$torch->onRedstoneScheduledUpdate($engine);
		$after = $world->getBlockAt(8, 64, 8);
		self::assertInstanceOf(RedstoneTorch::class, $after);
		self::assertFalse($after->isLit(), "Pending feedback cannot defer the authoritative extinguish");
		self::assertFalse($engine->isScheduled($after->getPosition()));
	}

	public function testIncompleteFeedbackNearUnloadedChunkDoesNotFreezeTorch() : void{
		[$engine, $world] = $this->createEnvironment();
		$world->setBlockAt(13, 64, 8, VanillaBlocks::STONE(), false);
		$world->setBlockAt(12, 64, 8, VanillaBlocks::REDSTONE(), false);
		$world->setBlockAt(13, 65, 8, VanillaBlocks::REDSTONE_WIRE()->setOutputSignalStrength(15), false);
		$world->setBlockAt(15, 64, 8, VanillaBlocks::REDSTONE_WIRE()->setOutputSignalStrength(15), false);
		$world->setBlockAt(14, 64, 8, VanillaBlocks::REDSTONE_TORCH()->setFacing(Facing::EAST), false);
		$this->loadedChunks["1:0"] = false;
		$engine->request(14, 64, 8);
		$engine->tick(0);
		$engine->tick(1);
		$engine->tick(2);
		$torch = $world->getBlockAt(14, 64, 8);
		self::assertInstanceOf(RedstoneTorch::class, $torch);
		self::assertFalse($torch->isLit());
		self::assertFalse($engine->isScheduled($torch->getPosition()), "Incomplete analysis must not install one-tick retry timers");
	}

	public function testConfirmedFeedbackUsesOriginalExtinguishTick() : void{
		[$engine, $world] = $this->createEnvironment();
		$this->placeFeedbackTorch($world, 8);
		$engine->tick(20);
		$torch = $world->getBlockAt(8, 64, 8);
		self::assertInstanceOf(RedstoneTorch::class, $torch);
		$torch->onRedstoneScheduledUpdate($engine);
		$this->loadedChunks["0:0"] = false;
		for($tick = 21; $tick <= 25; ++$tick){
			$engine->tick($tick);
		}
		$togglesProperty = new \ReflectionProperty(TorchBurnout::class, "toggles");
		self::assertSame([], $togglesProperty->getValue($engine->getTorchBurnout()));
		$this->loadedChunks["0:0"] = true;
		$engine->tick(26);
		$toggles = $togglesProperty->getValue($engine->getTorchBurnout());
		self::assertSame([20], $toggles[World::blockHash(8, 64, 8)] ?? [], "Confirmation time must not replace the original extinguish tick");
	}

	public function testReplacedTorchRejectsCapturedFeedback() : void{
		[$engine, $world] = $this->createEnvironment();
		$this->placeFeedbackTorch($world, 8);
		$engine->tick(20);
		$torch = $world->getBlockAt(8, 64, 8);
		self::assertInstanceOf(RedstoneTorch::class, $torch);
		$torch->onRedstoneScheduledUpdate($engine);
		$world->setBlockAt(8, 64, 8, VanillaBlocks::REDSTONE_TORCH()->setFacing(Facing::EAST), true);
		$engine->tick(21);
		self::assertSame([], (new \ReflectionProperty(TorchBurnout::class, "toggles"))->getValue($engine->getTorchBurnout()));
	}

	public function testNormalRelightPreservesCapturedFeedbackIdentity() : void{
		[$engine, $world] = $this->createEnvironment();
		$this->placeFeedbackTorch($world, 8);
		$engine->tick(20);
		$torch = $world->getBlockAt(8, 64, 8);
		self::assertInstanceOf(RedstoneTorch::class, $torch);
		$torch->onRedstoneScheduledUpdate($engine);
		$world->setBlockAt(7, 65, 8, VanillaBlocks::REDSTONE_WIRE(), false);
		$unlit = $world->getBlockAt(8, 64, 8);
		self::assertInstanceOf(RedstoneTorch::class, $unlit);
		$unlit->onRedstoneScheduledUpdate($engine);
		$engine->tick(21);
		$toggles = (new \ReflectionProperty(TorchBurnout::class, "toggles"))->getValue($engine->getTorchBurnout());
		self::assertSame([20], $toggles[World::blockHash(8, 64, 8)] ?? []);
	}

	public function testExpiredLateFeedbackCannotCauseFreshBurnout() : void{
		[$engine, $world] = $this->createEnvironment();
		$this->placeFeedbackTorch($world, 8);
		$engine->tick(20);
		$torch = $world->getBlockAt(8, 64, 8);
		self::assertInstanceOf(RedstoneTorch::class, $torch);
		$torch->onRedstoneScheduledUpdate($engine);
		$this->loadedChunks["0:0"] = false;
		$engine->tick(99);
		$pos = new Vector3(8, 64, 8);
		for($i = 0; $i < 7; ++$i){
			$engine->getTorchBurnout()->recordToggle($pos, $engine);
		}
		$this->loadedChunks["0:0"] = true;
		$engine->tick(100);
		self::assertFalse($engine->getTorchBurnout()->isBurntOut($pos, 100));
	}

	public function testFeedbackWorkCannotStarveOtherRedstoneWork() : void{
		[$engine, $world] = $this->createEnvironment(4);
		for($i = 0; $i < 30; ++$i){
			$x = 8 + $i * 8;
			$this->placeFeedbackTorch($world, $x);
			$torch = $world->getBlockAt($x, 64, 8);
			self::assertInstanceOf(RedstoneTorch::class, $torch);
			$engine->getTorchBurnout()->checkFeedback($torch, $engine, false);
		}
		$checks = (new \ReflectionProperty(TorchBurnout::class, "feedbackChecks"))->getValue($engine->getTorchBurnout());
		$events = [];
		$receiver = new class(new BlockIdentifier(BlockTypeIds::newId()), "Fair receiver", new BlockTypeInfo(BlockBreakInfo::indestructible()), $events) extends Opaque implements DelayedRedstoneReceiver{
			public function __construct(BlockIdentifier $id, string $name, BlockTypeInfo $info, private array &$events){
				parent::__construct($id, $name, $info);
			}
			public function onRedstoneUpdate(RedstoneEngine $engine) : void{
				$this->events[] = ["receiver", $engine->getCurrentTick(), $engine->getRemainingBudget()];
			}
			public function onRedstoneScheduledUpdate(RedstoneEngine $engine) : void{
				$this->events[] = [$this->position->getFloorX() === 480 ? "parked" : "timer", $engine->getCurrentTick(), $engine->getRemainingBudget()];
			}
		};
		$world->setBlockAt(310, 64, 20, $receiver, false);
		$world->setBlockAt(311, 64, 20, $receiver, false);
		$engine->schedule(new Vector3(310, 64, 20), 1);
		$world->setBlockAt(480, 64, 480, $receiver, false);
		$this->loadedChunks["30:30"] = false;
		$engine->schedule(new Vector3(480, 64, 480), 1);
		$engine->request(311, 64, 20);
		$world->setBlockAt(299, 64, 25, VanillaBlocks::REDSTONE(), false);
		for($x = 300; $x < 303; ++$x){
			$world->setBlockAt($x, 63, 25, VanillaBlocks::STONE(), false);
			$world->setBlockAt($x, 64, 25, VanillaBlocks::REDSTONE_WIRE(), false);
		}
		$engine->request(300, 64, 25);
		for($tick = 1; $tick <= 12; ++$tick){
			if($tick === 2){
				$this->loadedChunks["30:30"] = true;
			}
			$engine->tick($tick);
		}
		self::assertContains("parked", array_column($events, 0));
		self::assertContains("timer", array_column($events, 0));
		self::assertContains("receiver", array_column($events, 0));
		$wire = $world->getBlockAt(302, 64, 25);
		self::assertInstanceOf(\pocketmine\block\RedstoneWire::class, $wire);
		self::assertSame(13, $wire->getOutputSignalStrength());
		$confirmed = 0;
		foreach($checks as $check){
			$confirmed += $check->result === true ? 1 : 0;
		}
		self::assertGreaterThan(0, $confirmed);
		foreach($events as [, , $remaining]){
			self::assertGreaterThanOrEqual(0, $remaining);
			self::assertLessThan(4, $remaining);
		}
	}

	public function testQueuedBreakAndSameStateReplacementRejectsLateFeedback() : void{
		[$engine, $world] = $this->createEnvironment(1000, true);
		$this->placeFeedbackTorch($world, 8);
		$engine->tick(20);
		$torch = $world->getBlockAt(8, 64, 8);
		self::assertInstanceOf(RedstoneTorch::class, $torch);
		$torch->onRedstoneScheduledUpdate($engine);
		$world->setBlockAt(7, 65, 8, VanillaBlocks::REDSTONE_WIRE(), false);
		$torch = $world->getBlockAt(8, 64, 8);
		self::assertInstanceOf(RedstoneTorch::class, $torch);
		$torch->onRedstoneScheduledUpdate($engine);
		$torch = $world->getBlockAt(8, 64, 8);
		self::assertInstanceOf(RedstoneTorch::class, $torch);
		self::assertTrue($torch->isLit());
		$world->setBlockAt(8, 64, 8, VanillaBlocks::AIR(), true);
		$world->setBlockAt(8, 64, 8, VanillaBlocks::REDSTONE_TORCH()->setFacing(Facing::EAST), true);
		// World processes one deduplicated neighbour notification and reads the current block.
		$engine->onNeighbourUpdate($world->getBlockAt(8, 64, 8));
		$engine->getTorchBurnout()->processFeedback($engine, 1000);
		self::assertSame([], (new \ReflectionProperty(TorchBurnout::class, 'toggles'))->getValue($engine->getTorchBurnout()), 'Broken torch must not contribute history to replacement');
	}

	public function testDirtyTopologyCannotStarveUnrelatedCapturedExtinguish() : void{
		[$engine, $world] = $this->createEnvironment(4);
		$this->placeFeedbackTorch($world, 8);
		$this->placeFeedbackTorch($world, 40);
		$burnout = $engine->getTorchBurnout();
		$burnout->recordExtinguish($world->getBlockAt(8, 64, 8), $engine);
		$engine->schedule(new Vector3(8, 64, 8), 1000);
		$burnout->checkFeedback($world->getBlockAt(40, 64, 8), $engine, false);
		$checks = (new \ReflectionProperty(TorchBurnout::class, 'feedbackChecks'))->getValue($burnout);
		$captured = $checks[World::blockHash(8, 64, 8)];
		self::assertSame(0, $captured->extinguishTick);
		for($tick = 1; $tick <= 40; ++$tick){
			$world->setBlockAt(40, 66, 8, $tick % 2 === 1 ? VanillaBlocks::STONE() : VanillaBlocks::AIR(), false);
			$engine->onNeighbourUpdate($world->getBlockAt(40, 66, 8));
			$engine->tick($tick);
		}
		self::assertNotNull($captured->result, 'Unrelated captured extinguish must finish during 40 ticks of watched topology changes');
	}

	private function placeFeedbackTorch(World $world, int $x) : void{
		$world->setBlockAt($x - 1, 64, 8, VanillaBlocks::STONE(), false);
		$world->setBlockAt($x, 65, 8, VanillaBlocks::STONE(), false);
		$world->setBlockAt($x - 1, 65, 8, VanillaBlocks::REDSTONE_WIRE()->setOutputSignalStrength(15), false);
		$world->setBlockAt($x, 64, 8, VanillaBlocks::REDSTONE_TORCH()->setFacing(Facing::EAST), false);
	}

	public function testUnrelatedUpdatesCannotStarveFeedbackTransition() : void{
		[$engine, $world] = $this->createEnvironment(1);
		$world->setBlockAt(7, 64, 8, VanillaBlocks::STONE(), false);
		$world->setBlockAt(8, 65, 8, VanillaBlocks::STONE(), false);
		$world->setBlockAt(7, 65, 8, VanillaBlocks::REDSTONE_WIRE()->setOutputSignalStrength(15), false);
		$world->setBlockAt(8, 64, 8, VanillaBlocks::REDSTONE_TORCH()->setFacing(Facing::EAST), false);
		$engine->request(8, 64, 8);
		$extinguished = false;
		for($tick = 0; $tick < 100; ++$tick){
			$world->setBlockAt(12, 64, 12, $tick % 2 === 0 ? VanillaBlocks::STONE() : VanillaBlocks::REDSTONE(), true);
			$engine->tick($tick);
			$torch = $world->getBlockAt(8, 64, 8);
			self::assertInstanceOf(RedstoneTorch::class, $torch);
			$extinguished = $extinguished || !$torch->isLit();
		}
		$torch = $world->getBlockAt(8, 64, 8);
		self::assertInstanceOf(RedstoneTorch::class, $torch);
		self::assertTrue($extinguished);
	}

	public function testExternalTorchAtChunkBoundaryDoesNotNeedOutputChunk() : void{
		[$engine, $world] = $this->createEnvironment();
		$this->loadedChunks["1:0"] = false;
		$world->setBlockAt(14, 64, 8, VanillaBlocks::STONE(), false);
		$world->setBlockAt(13, 64, 8, VanillaBlocks::REDSTONE(), false);
		$world->setBlockAt(15, 64, 8, VanillaBlocks::REDSTONE_TORCH()->setFacing(Facing::EAST), false);
		$engine->request(15, 64, 8);
		$engine->tick(0);
		$engine->tick(1);
		$engine->tick(2);
		$torch = $world->getBlockAt(15, 64, 8);
		self::assertInstanceOf(RedstoneTorch::class, $torch);
		self::assertFalse($torch->isLit());
	}

	public function testDelayedReturnSourcesDoNotCountAsSelfFeedback() : void{
		[$engine, $world] = $this->createEnvironment();
		$world->setBlockAt(-1, 64, 0, VanillaBlocks::STONE(), false);
		$world->setBlockAt(-1, 65, 0, VanillaBlocks::REDSTONE_WIRE()->setOutputSignalStrength(15), false);
		$world->setBlockAt(0, 64, 0, VanillaBlocks::REDSTONE_TORCH()->setFacing(Facing::EAST), false);
		$torch = $this->getTorch($world);
		foreach([VanillaBlocks::REDSTONE_REPEATER(), VanillaBlocks::REDSTONE_COMPARATOR(), VanillaBlocks::REDSTONE_TORCH()] as $source){
			$world->setBlockAt(0, 65, 0, $source, false);
			self::assertFalse($engine->getTorchBurnout()->checkFeedback($torch, $engine));
		}
	}

	public function testOutputDustDoesNotAddDelayToExternalClock() : void{
		[$engine, $world] = $this->createEnvironment();
		$world->setBlockAt(-1, 64, 0, VanillaBlocks::STONE(), false);
		$world->setBlockAt(-2, 64, 0, VanillaBlocks::REDSTONE(), false);
		$world->setBlockAt(0, 64, 0, VanillaBlocks::REDSTONE_TORCH()->setFacing(Facing::EAST), false);
		$world->setBlockAt(1, 64, 0, VanillaBlocks::REDSTONE_WIRE()->setOutputSignalStrength(15), false);
		$engine->request(0, 64, 0);
		$engine->tick(0);
		$engine->tick(1);
		$engine->tick(2);
		self::assertFalse($this->getTorch($world)->isLit(), "Output dust must not extend the normal two-tick torch delay");
	}

	public function testFeedbackCheckSurvivesChunkParking() : void{
		[$engine, $world] = $this->createEnvironment();
		$world->setBlockAt(7, 64, 8, VanillaBlocks::STONE(), false);
		$world->setBlockAt(8, 65, 8, VanillaBlocks::STONE(), false);
		$world->setBlockAt(7, 65, 8, VanillaBlocks::REDSTONE_WIRE()->setOutputSignalStrength(15), false);
		$world->setBlockAt(8, 64, 8, VanillaBlocks::REDSTONE_TORCH()->setFacing(Facing::EAST), false);
		$torch = $world->getBlockAt(8, 64, 8);
		self::assertInstanceOf(RedstoneTorch::class, $torch);
		self::assertNull($engine->getTorchBurnout()->checkFeedback($torch, $engine));
		$this->loadedChunks["0:0"] = false;
		$engine->tick(1);
		self::assertNull($engine->getTorchBurnout()->checkFeedback($torch, $engine), "An unloaded chunk cannot be interpreted as missing self-feedback");
		$this->loadedChunks["0:0"] = true;
		$engine->tick(2);
		self::assertTrue($engine->getTorchBurnout()->checkFeedback($torch, $engine));
	}

	public function testCompletedFeedbackCannotSurviveTopologyChange() : void{
		[$engine, $world] = $this->createEnvironment();
		$pos = new Vector3(0, 64, 0);
		$world->setBlockAt(-1, 64, 0, VanillaBlocks::STONE(), false);
		$world->setBlockAt(0, 65, 0, VanillaBlocks::STONE(), false);
		$world->setBlockAt(-1, 65, 0, VanillaBlocks::REDSTONE_WIRE()->setOutputSignalStrength(15), false);
		$world->setBlockAt(0, 64, 0, VanillaBlocks::REDSTONE_TORCH()->setFacing(Facing::EAST), false);
		$torch = $this->getTorch($world);
		for($i = 0; $i < 7; ++$i){
			$engine->getTorchBurnout()->recordToggle($pos, $engine);
		}
		self::assertNull($engine->getTorchBurnout()->checkFeedback($torch, $engine, false));
		$engine->tick(1);
		$world->setBlockAt(0, 65, 0, VanillaBlocks::AIR(), true);
		$world->setBlockAt(-2, 64, 0, VanillaBlocks::REDSTONE(), true);
		$torch->onRedstoneScheduledUpdate($engine);
		self::assertFalse($engine->getTorchBurnout()->isBurntOut($pos, 1), "An old return path cannot count an externally caused extinguish");
	}

	public function testCancelledExtinguishReleasesFeedbackResult() : void{
		[$engine, $world] = $this->createEnvironment();
		$world->setBlockAt(-1, 64, 0, VanillaBlocks::STONE(), false);
		$world->setBlockAt(0, 65, 0, VanillaBlocks::STONE(), false);
		$world->setBlockAt(-1, 65, 0, VanillaBlocks::REDSTONE_WIRE()->setOutputSignalStrength(15), false);
		$world->setBlockAt(0, 64, 0, VanillaBlocks::REDSTONE_TORCH()->setFacing(Facing::EAST), false);
		$torch = $this->getTorch($world);
		self::assertNull($engine->getTorchBurnout()->checkFeedback($torch, $engine));
		$engine->tick(1);
		$world->setBlockAt(-1, 65, 0, VanillaBlocks::AIR(), false);
		$torch->onRedstoneScheduledUpdate($engine);
		$checks = new \ReflectionProperty(TorchBurnout::class, "feedbackChecks");
		self::assertSame([], $checks->getValue($engine->getTorchBurnout()), "A cancelled transition must discard its pending verdict");
	}
	public function testExternalSwitchingDoesNotCountAsBurnoutFeedback() : void{
		[$engine, $world] = $this->createEnvironment();
		$pos = new Vector3(0, 64, 0);
		$world->setBlockAt(-1, 64, 0, VanillaBlocks::STONE(), false);
		$world->setBlockAt(0, 64, 0, VanillaBlocks::REDSTONE_TORCH()->setFacing(Facing::EAST), false);
		for($cycle = 0; $cycle < 10; ++$cycle){
			$world->setBlockAt(-2, 64, 0, VanillaBlocks::REDSTONE(), false);
			$engine->request(0, 64, 0);
			for($tick = $cycle * 6; $tick < $cycle * 6 + 3; ++$tick){
				$engine->tick($tick);
			}
			self::assertFalse($this->getTorch($world)->isLit());
			$world->setBlockAt(-2, 64, 0, VanillaBlocks::AIR(), false);
			$engine->request(0, 64, 0);
			for($tick = $cycle * 6 + 3; $tick < $cycle * 6 + 6; ++$tick){
				$engine->tick($tick);
			}
			self::assertTrue($this->getTorch($world)->isLit(), "An external clock must keep switching the torch");
		}
		self::assertFalse($engine->getTorchBurnout()->isBurntOut($pos, 60));
	}

	public function testBurnoutWaitsForUpdateWithoutAutomaticTimer() : void{
		[$engine, $world] = $this->createEnvironment();
		$pos = new Vector3(0, 64, 0);
		$torch = VanillaBlocks::REDSTONE_TORCH()->setLit(false);
		$world->setBlockAt(0, 63, 0, VanillaBlocks::STONE(), false);
		$world->setBlockAt(0, 64, 0, $torch, false);
		for($i = 0; $i < 8; ++$i){
			$engine->getTorchBurnout()->recordToggle($pos, $engine, $torch->getStateId());
		}
		self::assertFalse($engine->isScheduled($pos), "Burnout must not install a Java recovery timer");
		$engine->tick(200);
		self::assertTrue($engine->getTorchBurnout()->isBurntOut($pos, 200));
		self::assertFalse($this->getTorch($world)->isLit());
		$engine->onNeighbourUpdate($world->getBlockAt(0, 64, 0));
		$engine->tick(201);
		$engine->tick(203);
		self::assertTrue($this->getTorch($world)->isLit());
	}

	public function testSelfFeedbackClockBurnsOutAndStaysOff() : void{
		[$engine, $world] = $this->createEnvironment();
		$pos = new Vector3(0, 64, 0);
		$world->setBlockAt(-1, 64, 0, VanillaBlocks::STONE(), false);
		$world->setBlockAt(0, 65, 0, VanillaBlocks::STONE(), false);
		$world->setBlockAt(-1, 65, 0, VanillaBlocks::REDSTONE_WIRE(), false);
		$world->setBlockAt(0, 64, 0, VanillaBlocks::REDSTONE_TORCH()->setFacing(Facing::EAST), false);
		$engine->request(-1, 65, 0);
		$engine->request(0, 64, 0);
		for($tick = 1; $tick <= 240; ++$tick){
			$engine->tick($tick);
		}
		self::assertTrue($engine->getTorchBurnout()->isBurntOut($pos, 240));
		self::assertFalse($this->getTorch($world)->isLit());
		self::assertFalse($engine->isScheduled($pos));
		$world->setBlockAt(0, 65, 0, VanillaBlocks::AIR(), false);
		$engine->onNeighbourUpdate($world->getBlockAt(0, 64, 0));
		$engine->tick(241);
		$engine->tick(243);
		self::assertTrue($this->getTorch($world)->isLit());
	}

	public function testFeedbackCheckRespectsSingleStepBudget() : void{
		[$engine, $world] = $this->createEnvironment(1);
		$world->setBlockAt(-1, 64, 0, VanillaBlocks::STONE(), false);
		$world->setBlockAt(0, 65, 0, VanillaBlocks::STONE(), false);
		$world->setBlockAt(-1, 65, 0, VanillaBlocks::REDSTONE_WIRE()->setOutputSignalStrength(15), false);
		$world->setBlockAt(0, 64, 0, VanillaBlocks::REDSTONE_TORCH()->setFacing(Facing::EAST), false);
		$engine->request(0, 64, 0);
		for($tick = 1; $tick <= 10; ++$tick){
			$engine->tick($tick);
		}
		self::assertFalse($this->getTorch($world)->isLit(), "Feedback work must progress even with a one-step engine budget");
	}

	private function getTorch(World $world) : RedstoneTorch{
		$torch = $world->getBlockAt(0, 64, 0);
		self::assertInstanceOf(RedstoneTorch::class, $torch);
		return $torch;
	}

	/**
	 * @return array{RedstoneEngine, World, array<string, Block>}
	 */
	private function createEnvironment(int $maxUpdatesPerTick = 1000, bool $deferredNotifications = false) : array{
		$blocks = [];

		$world = $this->getMockBuilder(World::class)->disableOriginalConstructor()->onlyMethods([
			"getBlockAt",
			"setBlockAt",
			"isInWorld",
			"isChunkLoaded",
			"notifyNeighbourBlockUpdate"
		])->getMock();

		$world->method("isInWorld")->willReturn(true);
		$world->method("isChunkLoaded")->willReturnCallback(fn(int $x, int $z) : bool => $this->loadedChunks["$x:$z"] ?? true);

		$engine = new RedstoneEngine($world, $maxUpdatesPerTick);

		$world->method("getBlockAt")->willReturnCallback(function(int $x, int $y, int $z) use (&$blocks, $world) : Block{
			$key = "$x:$y:$z";
			if(isset($blocks[$key])){
				return clone $blocks[$key];
			}
			$air = clone VanillaBlocks::AIR();
			$air->position($world, $x, $y, $z);
			return $air;
		});

		$world->method("setBlockAt")->willReturnCallback(function(int $x, int $y, int $z, Block $block, bool $notify = true) use (&$blocks, $world, $engine) : bool{
			$key = "$x:$y:$z";
			$clone = clone $block;
			$clone->position($world, $x, $y, $z);
			$blocks[$key] = $clone;
			$engine->getTorchBurnout()->onBlockChanged($clone);
			if($notify){
				$world->notifyNeighbourBlockUpdate(new Vector3($x, $y, $z));
			}
			return true;
		});

		$world->method("notifyNeighbourBlockUpdate")->willReturnCallback(function(Vector3 $pos) use ($world, $engine, $deferredNotifications) : void{
			if($deferredNotifications){
				return;
			}
			$x = $pos->getFloorX();
			$y = $pos->getFloorY();
			$z = $pos->getFloorZ();
			$engine->onNeighbourUpdate($world->getBlockAt($x, $y, $z));
		});

		return [$engine, $world, $blocks];
	}

	public function testTorchTogglingBelowBurnoutThreshold() : void{
		[$engine, $world] = $this->createEnvironment();
		$pos = new Vector3(0, 64, 0);

		$burnout = $engine->getTorchBurnout();
		$engine->tick(1);

		for($i = 1; $i <= 7; ++$i){
			$burnout->recordToggle($pos, $engine);
		}

		self::assertFalse(
			$burnout->isBurntOut($pos, 1),
			"Torch must not burn out after 7 extinguishes in 60 ticks"
		);
	}

	public function testExactBurnoutThresholdBoundary() : void{
		[$engine, $world] = $this->createEnvironment();
		$pos = new Vector3(0, 64, 0);

		$burnout = $engine->getTorchBurnout();
		$engine->tick(1);

		for($i = 1; $i <= 7; ++$i){
			$burnout->recordToggle($pos, $engine);
		}
		self::assertFalse($burnout->isBurntOut($pos, 1), "7th extinguish must not burn out");

		$burnout->recordToggle($pos, $engine);
		self::assertTrue($burnout->isBurntOut($pos, 1), "8th extinguish within 60 ticks must trigger burnout");
	}

	public function testOneToggleBeyondBoundaryRemainsBurntOut() : void{
		[$engine, $world] = $this->createEnvironment();
		$pos = new Vector3(0, 64, 0);

		$burnout = $engine->getTorchBurnout();
		$engine->tick(1);

		for($i = 1; $i <= 8; ++$i){
			$burnout->recordToggle($pos, $engine);
		}
		self::assertTrue($burnout->isBurntOut($pos, 1));

		$burnout->recordToggle($pos, $engine);
		self::assertTrue($burnout->isBurntOut($pos, 1), "9th toggle must remain burnt out");
	}

	public function testTogglesOutsideRollingWindowExpire() : void{
		[$engine, $world] = $this->createEnvironment();
		$pos = new Vector3(0, 64, 0);

		$burnout = $engine->getTorchBurnout();
		$engine->tick(0);

		for($i = 1; $i <= 7; ++$i){
			$burnout->recordToggle($pos, $engine);
		}
		self::assertFalse($burnout->isBurntOut($pos, 0));

		$engine->tick(61);
		$burnout->recordToggle($pos, $engine);

		self::assertFalse(
			$burnout->isBurntOut($pos, 61),
			"Toggles from tick 0 must expire after 60 ticks and not contribute to burnout at tick 61"
		);
	}

	public function testEarlyUpdateCannotRecoverBurntOutTorch() : void{
		[$engine, $world] = $this->createEnvironment();
		$pos = new Vector3(0, 64, 0);
		$unlit = VanillaBlocks::REDSTONE_TORCH()->setLit(false);
		$world->setBlockAt(0, 63, 0, VanillaBlocks::STONE(), false);
		$world->setBlockAt(0, 64, 0, $unlit, false);
		$engine->tick(10);
		for($i = 0; $i < 8; ++$i){
			$engine->getTorchBurnout()->recordToggle($pos, $engine, $unlit->getStateId());
		}
		$engine->onNeighbourUpdate($world->getBlockAt(0, 64, 0));
		$engine->tick(11);
		self::assertTrue($engine->getTorchBurnout()->isBurntOut($pos, 11));
		self::assertFalse($this->getTorch($world)->isLit());
		self::assertFalse($engine->isScheduled($pos));
	}

	public function testFailedRelightWhenInputStillPowered() : void{
		[$engine, $world] = $this->createEnvironment();
		$pos = new Vector3(0, 64, 0);

		$world->setBlockAt(0, 63, 0, VanillaBlocks::STONE(), false);
		$torch = VanillaBlocks::REDSTONE_TORCH();
		$world->setBlockAt(0, 64, 0, $torch, false);

		$world->setBlockAt(0, 62, 0, VanillaBlocks::REDSTONE(), false);

		$burnout = $engine->getTorchBurnout();
		$engine->tick(10);

		$unlit = $torch->setLit(false);
		for($i = 1; $i <= 7; ++$i){
			$burnout->recordToggle($pos, $engine);
		}
		$burnout->recordToggle($pos, $engine, $unlit->getStateId());
		$world->setBlockAt(0, 64, 0, $unlit, true);

		$engine->tick(70);
		$engine->onNeighbourUpdate($world->getBlockAt(0, 64, 0));
		$engine->tick(71);

		/** @var RedstoneTorch $torchAfter */
		$torchAfter = $world->getBlockAt(0, 64, 0);
		self::assertFalse($torchAfter->isLit(), "Torch must remain unlit when input is still powered");
		self::assertFalse($burnout->isBurntOut($pos, 170), "An update must clear expired burnout without relighting a powered torch");
	}

	public function testBreakAndReplacementClearsBurnoutHistory() : void{
		[$engine, $world] = $this->createEnvironment();
		$pos = new Vector3(0, 64, 0);

		$world->setBlockAt(0, 63, 0, VanillaBlocks::STONE(), false);
		$world->setBlockAt(0, 64, 0, VanillaBlocks::REDSTONE_TORCH(), false);

		$burnout = $engine->getTorchBurnout();
		$engine->tick(10);
		for($i = 1; $i <= 8; ++$i){
			$burnout->recordToggle($pos, $engine);
		}
		self::assertTrue($burnout->isBurntOut($pos, 10));

		$world->setBlockAt(0, 64, 0, VanillaBlocks::AIR(), true);

		self::assertFalse(
			$burnout->isBurntOut($pos, 10),
			"Breaking torch must clear burnout state via onNeighbourUpdate -> forget()"
		);

		$world->setBlockAt(0, 64, 0, VanillaBlocks::REDSTONE_TORCH(), true);

		for($i = 1; $i <= 7; ++$i){
			$burnout->recordToggle($pos, $engine);
		}
		self::assertFalse(
			$burnout->isBurntOut($pos, 10),
			"New torch at same position must have fresh toggle budget"
		);
	}

	public function testIndependentTorchesBurnout() : void{
		[$engine, $world] = $this->createEnvironment();
		$posA = new Vector3(0, 64, 0);
		$posB = new Vector3(10, 64, 0);

		$burnout = $engine->getTorchBurnout();
		$engine->tick(5);

		for($i = 1; $i <= 8; ++$i){
			$burnout->recordToggle($posA, $engine);
		}

		self::assertTrue($burnout->isBurntOut($posA, 5), "Torch A must be burnt out");
		self::assertFalse($burnout->isBurntOut($posB, 5), "Torch B must remain unaffected");

		for($i = 1; $i <= 7; ++$i){
			$burnout->recordToggle($posB, $engine);
		}
		self::assertFalse($burnout->isBurntOut($posB, 5), "Torch B must handle 7 toggles without burning out");
	}

	public function testWorldClearPurgesBurnoutState() : void{
		[$engine, $world] = $this->createEnvironment();
		$posA = new Vector3(0, 64, 0);
		$posB = new Vector3(10, 64, 0);

		$burnout = $engine->getTorchBurnout();
		$engine->tick(5);

		for($i = 1; $i <= 8; ++$i){
			$burnout->recordToggle($posA, $engine);
		}
		for($i = 1; $i <= 4; ++$i){
			$burnout->recordToggle($posB, $engine);
		}

		self::assertTrue($burnout->isBurntOut($posA, 5));

		$engine->clear();

		self::assertFalse($burnout->isBurntOut($posA, 5), "Burnout map must be empty after clear()");
		self::assertFalse($burnout->isBurntOut($posB, 5));
	}

	public function testReplacingBurntOutTorchWithLitTorchStartsFresh() : void{
		[$engine, $world] = $this->createEnvironment();
		$pos = new Vector3(0, 64, 0);

		$world->setBlockAt(0, 63, 0, VanillaBlocks::STONE(), false);
		$litTorch = VanillaBlocks::REDSTONE_TORCH();
		$unlitTorch = $litTorch->setLit(false);
		$world->setBlockAt(0, 64, 0, $unlitTorch, false);

		$burnout = $engine->getTorchBurnout();
		$engine->tick(10);

		for($i = 1; $i <= 7; ++$i){
			$burnout->recordToggle($pos, $engine);
		}
		$burnout->recordToggle($pos, $engine, $unlitTorch->getStateId());
		self::assertTrue($burnout->isBurntOut($pos, 10));

		$newLitTorch = VanillaBlocks::REDSTONE_TORCH();
		$world->setBlockAt(0, 64, 0, $newLitTorch, true);

		self::assertFalse(
			$burnout->isBurntOut($pos, 10),
			"Replacing burnt out torch with lit torch must clear burnout state"
		);

		for($i = 1; $i <= 7; ++$i){
			$burnout->recordToggle($pos, $engine);
		}
		self::assertFalse(
			$burnout->isBurntOut($pos, 10),
			"Replaced torch must have a fresh toggle allowance"
		);
	}
}
