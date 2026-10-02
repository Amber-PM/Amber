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
use pocketmine\block\RedstoneTorch;
use pocketmine\block\VanillaBlocks;
use pocketmine\math\Vector3;
use pocketmine\world\World;

final class TorchBurnoutTest extends TestCase{

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

		$world->method("notifyNeighbourBlockUpdate")->willReturnCallback(function(Vector3 $pos) use ($world, $engine) : void{
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

	public function testBurnoutDurationAndRecoveryTiming() : void{
		[$engine, $world] = $this->createEnvironment();
		$pos = new Vector3(0, 64, 0);

		$world->setBlockAt(0, 63, 0, VanillaBlocks::STONE(), false);
		$torch = VanillaBlocks::REDSTONE_TORCH();
		$world->setBlockAt(0, 64, 0, $torch, false);

		$burnout = $engine->getTorchBurnout();
		$engine->tick(10);

		for($i = 1; $i <= 7; ++$i){
			$burnout->recordToggle($pos, $engine);
		}
		self::assertFalse($burnout->isBurntOut($pos, 10));

		/** @var RedstoneTorch $torchBlock */
		$torchBlock = $world->getBlockAt(0, 64, 0);
		self::assertTrue($torchBlock->isLit());

		$unlit = $torchBlock->setLit(false);
		$burnout->recordToggle($pos, $engine, $unlit->getStateId());
		self::assertTrue($burnout->isBurntOut($pos, 10), "8th toggle must trigger burnout");
		self::assertTrue($engine->isScheduled($pos), "Burnout must schedule unburnout timer");

		$world->setBlockAt(0, 64, 0, $unlit, true);

		for($t = 11; $t <= 169; ++$t){
			$engine->tick($t);
			self::assertTrue($burnout->isBurntOut($pos, $t), "Torch must remain burnt out during duration");
			/** @var RedstoneTorch $currentTorch */
			$currentTorch = $world->getBlockAt(0, 64, 0);
			self::assertFalse($currentTorch->isLit(), "Torch must remain unlit during burnout");
		}

		$engine->tick(170);

		self::assertFalse($burnout->isBurntOut($pos, 170), "Burnout must end once 160 ticks elapse");
		/** @var RedstoneTorch $recoveredTorch */
		$recoveredTorch = $world->getBlockAt(0, 64, 0);
		self::assertTrue($recoveredTorch->isLit(), "Scheduled unburnout timer must restore torch when input is unpowered");
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

		for($t = 11; $t <= 170; ++$t){
			$engine->tick($t);
		}

		/** @var RedstoneTorch $torchAfter */
		$torchAfter = $world->getBlockAt(0, 64, 0);
		self::assertFalse($torchAfter->isLit(), "Torch must remain unlit when input is still powered");
		self::assertFalse($burnout->isBurntOut($pos, 170), "Burnout status must be cleared without re-burnout");
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

