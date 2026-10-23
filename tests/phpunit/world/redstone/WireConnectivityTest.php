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
use pocketmine\block\utils\SlabType;
use pocketmine\block\VanillaBlocks;
use pocketmine\math\Vector3;
use pocketmine\world\World;
use function max;

final class WireConnectivityTest extends TestCase{

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

		$engine = new RedstoneEngine($world, 100000);

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

	public function testFlatWireLines() : void{
		[$engine, $world] = $this->createEnvironment();

		// Line of 16 wires from x=0 to x=15 on stone blocks
		for($x = 0; $x <= 15; ++$x){
			$world->setBlockAt($x, 63, 0, VanillaBlocks::STONE(), false);
			$world->setBlockAt($x, 64, 0, VanillaBlocks::REDSTONE_WIRE(), false);
		}

		// Power wire 0 with a redstone block underneath
		$world->setBlockAt(0, 63, 0, VanillaBlocks::REDSTONE(), false);
		$engine->getWires()->update($world->getBlockAt(0, 64, 0));

		// Check drop-off from 15 down to 0
		for($x = 0; $x <= 15; ++$x){
			/** @var RedstoneWire $wire */
			$wire = $world->getBlockAt($x, 64, 0);
			self::assertSame(max(0, 15 - $x), $wire->getOutputSignalStrength(), "Signal strength at x=$x");
		}
	}

	public function testUpwardDownwardStepsOnSolidBlocks() : void{
		[$engine, $world] = $this->createEnvironment();

		// Step up: (0, 64, 0) on Stone -> (1, 65, 0) on Stone -> (2, 64, 0) on Stone
		$world->setBlockAt(0, 63, 0, VanillaBlocks::REDSTONE(), false); // power source
		$world->setBlockAt(0, 64, 0, VanillaBlocks::REDSTONE_WIRE(), false);

		$world->setBlockAt(1, 64, 0, VanillaBlocks::STONE(), false);
		$world->setBlockAt(1, 65, 0, VanillaBlocks::REDSTONE_WIRE(), false);

		$world->setBlockAt(2, 63, 0, VanillaBlocks::STONE(), false);
		$world->setBlockAt(2, 64, 0, VanillaBlocks::REDSTONE_WIRE(), false);

		$engine->getWires()->update($world->getBlockAt(0, 64, 0));

		/** @var RedstoneWire $w0 */
		$w0 = $world->getBlockAt(0, 64, 0);
		/** @var RedstoneWire $w1 */
		$w1 = $world->getBlockAt(1, 65, 0);
		/** @var RedstoneWire $w2 */
		$w2 = $world->getBlockAt(2, 64, 0);

		self::assertSame(15, $w0->getOutputSignalStrength());
		self::assertSame(14, $w1->getOutputSignalStrength());
		self::assertSame(13, $w2->getOutputSignalStrength());

		// Place solid conductor overhead at (0, 65, 0): cuts the step up
		$world->setBlockAt(0, 65, 0, VanillaBlocks::STONE(), true);
		$engine->tick(2);

		/** @var RedstoneWire $w1Cut */
		$w1Cut = $world->getBlockAt(1, 65, 0);
		self::assertSame(0, $w1Cut->getOutputSignalStrength(), "Solid block overhead must cut step-up connection");
	}

	public function testGlassBidirectionalTransmission() : void{
		[$engine, $world] = $this->createEnvironment();

		// 1. Step up onto Glass: wire at (0, 64, 0) on stone; wire at (1, 65, 0) on Glass at (1, 64, 0)
		$world->setBlockAt(0, 63, 0, VanillaBlocks::REDSTONE(), false);
		$world->setBlockAt(0, 64, 0, VanillaBlocks::REDSTONE_WIRE(), false);
		$world->setBlockAt(1, 64, 0, VanillaBlocks::GLASS(), false);
		$world->setBlockAt(1, 65, 0, VanillaBlocks::REDSTONE_WIRE(), false);

		$engine->getWires()->update($world->getBlockAt(0, 64, 0));

		/** @var RedstoneWire $glassWireUp */
		$glassWireUp = $world->getBlockAt(1, 65, 0);
		self::assertSame(14, $glassWireUp->getOutputSignalStrength(), "Dust must step up onto glass");

		// 2. Glass overhead does NOT cut connection in Bedrock
		$world->setBlockAt(0, 65, 0, VanillaBlocks::GLASS(), false);
		$engine->getWires()->update($world->getBlockAt(0, 64, 0));

		/** @var RedstoneWire $glassWireAfterOverhead */
		$glassWireAfterOverhead = $world->getBlockAt(1, 65, 0);
		self::assertSame(14, $glassWireAfterOverhead->getOutputSignalStrength(), "Glass overhead must not cut dust connection");

		// 3. Step DOWN from Glass: power wire on Glass at (1, 65, 0), check wire at (0, 64, 0)
		[$engine2, $world2] = $this->createEnvironment();
		$world2->setBlockAt(0, 63, 0, VanillaBlocks::STONE(), false);
		$world2->setBlockAt(0, 64, 0, VanillaBlocks::REDSTONE_WIRE(), false);
		$world2->setBlockAt(1, 64, 0, VanillaBlocks::GLASS(), false);
		$world2->setBlockAt(1, 65, 0, VanillaBlocks::REDSTONE_WIRE(), false);
		$world2->setBlockAt(2, 65, 0, VanillaBlocks::REDSTONE(), false);

		$engine2->getWires()->update($world2->getBlockAt(1, 65, 0));

		/** @var RedstoneWire $glassWire */
		$glassWire = $world2->getBlockAt(1, 65, 0);
		/** @var RedstoneWire $lowerWire */
		$lowerWire = $world2->getBlockAt(0, 64, 0);
		self::assertSame(15, $glassWire->getOutputSignalStrength());
		self::assertSame(14, $lowerWire->getOutputSignalStrength(), "Dust must step down from glass");
	}

	public function testTopSlabDiodeTransmission() : void{
		[$engine, $world] = $this->createEnvironment();

		// 1. Step UP onto Top Slab: wire at (0, 64, 0) on stone; wire at (1, 65, 0) on Top Slab at (1, 64, 0)
		$world->setBlockAt(0, 63, 0, VanillaBlocks::REDSTONE(), false);
		$world->setBlockAt(0, 64, 0, VanillaBlocks::REDSTONE_WIRE(), false);
		$topSlab = VanillaBlocks::STONE_SLAB()->setSlabType(SlabType::TOP);
		$world->setBlockAt(1, 64, 0, $topSlab, false);
		$world->setBlockAt(1, 65, 0, VanillaBlocks::REDSTONE_WIRE(), false);

		$engine->getWires()->update($world->getBlockAt(0, 64, 0));

		/** @var RedstoneWire $slabWireUp */
		$slabWireUp = $world->getBlockAt(1, 65, 0);
		self::assertSame(14, $slabWireUp->getOutputSignalStrength(), "Dust must step up onto top slab");

		// 2. Step DOWN from Top Slab must FAIL (Top slab is a vertical diode: UP yes, DOWN no)
		[$engine2, $world2] = $this->createEnvironment();
		$world2->setBlockAt(0, 63, 0, VanillaBlocks::STONE(), false);
		$world2->setBlockAt(0, 64, 0, VanillaBlocks::REDSTONE_WIRE(), false);
		$world2->setBlockAt(1, 64, 0, clone $topSlab, false);
		$world2->setBlockAt(1, 65, 0, VanillaBlocks::REDSTONE_WIRE(), false);
		$world2->setBlockAt(2, 65, 0, VanillaBlocks::REDSTONE(), false);

		$engine2->getWires()->update($world2->getBlockAt(1, 65, 0));

		/** @var RedstoneWire $slabWire */
		$slabWire = $world2->getBlockAt(1, 65, 0);
		/** @var RedstoneWire $lowerWire */
		$lowerWire = $world2->getBlockAt(0, 64, 0);
		self::assertSame(15, $slabWire->getOutputSignalStrength());
		self::assertSame(0, $lowerWire->getOutputSignalStrength(), "Dust must NOT step down from top slab");

		// 3. Side Slab blocks step-down: wire on stone at (0, 65, 0), wire on stone at (1, 64, 0), with slab at (1, 65, 0)
		[$engine3, $world3] = $this->createEnvironment();
		$world3->setBlockAt(0, 64, 0, VanillaBlocks::STONE(), false);
		$world3->setBlockAt(0, 65, 0, VanillaBlocks::REDSTONE_WIRE(), false);
		$world3->setBlockAt(-1, 65, 0, VanillaBlocks::REDSTONE(), false);

		$world3->setBlockAt(1, 63, 0, VanillaBlocks::STONE(), false);
		$world3->setBlockAt(1, 64, 0, VanillaBlocks::REDSTONE_WIRE(), false);
		$world3->setBlockAt(1, 65, 0, clone $topSlab, false);

		$engine3->getWires()->update($world3->getBlockAt(0, 65, 0));

		/** @var RedstoneWire $lowerWireUnderSlab */
		$lowerWireUnderSlab = $world3->getBlockAt(1, 64, 0);
		self::assertSame(0, $lowerWireUnderSlab->getOutputSignalStrength(), "Side slab must cut downward step connection");
	}

	public function testNetworksCrossing4096Boundary() : void{
		[$engine, $world] = $this->createEnvironment();

		// Construct a continuous wire line of 4200 wires
		$wireCount = 4200;
		for($i = 0; $i < $wireCount; ++$i){
			$world->setBlockAt($i, 63, 0, VanillaBlocks::STONE(), false);
			$world->setBlockAt($i, 64, 0, VanillaBlocks::REDSTONE_WIRE(), false);
		}

		// Place a power source at wire 4090 so power crosses wire 4096 and reaches 4100
		$world->setBlockAt(4090, 63, 0, VanillaBlocks::REDSTONE(), false);

		$engine->getWires()->update($world->getBlockAt(0, 64, 0));
		self::assertTrue($engine->getWires()->hasDeferred(), "Network exceeding 4096 wires must defer continuation");

		$engine->tick(1);
		self::assertFalse($engine->getWires()->hasDeferred(), "Continuation must complete in subsequent tick");

		/** @var RedstoneWire $wireAt4090 */
		$wireAt4090 = $world->getBlockAt(4090, 64, 0);
		self::assertSame(15, $wireAt4090->getOutputSignalStrength());

		/** @var RedstoneWire $wireAt4096 */
		$wireAt4096 = $world->getBlockAt(4096, 64, 0);
		self::assertSame(9, $wireAt4096->getOutputSignalStrength(), "Wire at 4096 boundary must have correct power");

		/** @var RedstoneWire $wireAt4100 */
		$wireAt4100 = $world->getBlockAt(4100, 64, 0);
		self::assertSame(5, $wireAt4100->getOutputSignalStrength(), "Wire past 4096 boundary must not be truncated");
	}

	public function testNoStaleOscillatingStateAcrossTicks() : void{
		[$engine, $world] = $this->createEnvironment();

		$wireCount = 4200;
		for($i = 0; $i < $wireCount; ++$i){
			$world->setBlockAt($i, 63, 0, VanillaBlocks::STONE(), false);
			$world->setBlockAt($i, 64, 0, VanillaBlocks::REDSTONE_WIRE(), false);
		}

		$world->setBlockAt(4090, 63, 0, VanillaBlocks::REDSTONE(), false);

		// Initial update on tick 1, let it settle across tick 1 and tick 2
		$engine->onNeighbourUpdate($world->getBlockAt(4090, 64, 0));
		$engine->tick(1);
		$engine->tick(2);

		$processedSettled = $engine->getProcessedCount();
		self::assertGreaterThan(0, $processedSettled);

		// Subsequent ticks must remain idle without oscillating back and forth across boundary
		$engine->tick(3);
		$engine->tick(4);
		$engine->tick(5);

		self::assertSame($processedSettled, $engine->getProcessedCount(), "Network must settle without endless boundary ping-pong oscillation");
	}

	public function testNetworkMutationDuringDeferralBreakWire() : void{
		[$engine, $world] = $this->createEnvironment();

		$wireCount = 4200;
		for($i = 0; $i < $wireCount; ++$i){
			$world->setBlockAt($i, 63, 0, VanillaBlocks::STONE(), false);
			$world->setBlockAt($i, 64, 0, VanillaBlocks::REDSTONE_WIRE(), false);
		}

		// Place power source at wire 2005 (within 15 blocks of wire 2000)
		$world->setBlockAt(2005, 63, 0, VanillaBlocks::REDSTONE(), false);

		// Start update from wire 0: expands 4096 wires and defers
		$engine->getWires()->update($world->getBlockAt(0, 64, 0));
		self::assertTrue($engine->getWires()->hasDeferred(), "Network exceeding 4096 wires must defer continuation");

		// While deferred: cut the wire line by breaking wire 2000 to AIR
		$world->setBlockAt(2000, 64, 0, VanillaBlocks::AIR(), true);

		// Run engine ticks to process updates and settle
		$engine->tick(1);
		$engine->tick(2);

		/** @var RedstoneWire $wireBeforeCut */
		$wireBeforeCut = $world->getBlockAt(1999, 64, 0);
		self::assertSame(0, $wireBeforeCut->getOutputSignalStrength(), "Wire on unpowered side must not receive power across cut made during defer");

		/** @var RedstoneWire $wireAt2005 */
		$wireAt2005 = $world->getBlockAt(2005, 64, 0);
		self::assertSame(15, $wireAt2005->getOutputSignalStrength(), "Wire on powered side must retain power");
	}

	public function testNetworkMutationDuringDeferralPlaceWire() : void{
		[$engine, $world] = $this->createEnvironment();

		$wireCount = 4200;
		for($i = 0; $i < $wireCount; ++$i){
			$world->setBlockAt($i, 63, 0, VanillaBlocks::STONE(), false);
			$world->setBlockAt($i, 64, 0, VanillaBlocks::REDSTONE_WIRE(), false);
		}

		$world->setBlockAt(2005, 63, 0, VanillaBlocks::REDSTONE(), false);

		$engine->getWires()->update($world->getBlockAt(0, 64, 0));
		self::assertTrue($engine->getWires()->hasDeferred(), "Network exceeding 4096 wires must defer continuation");

		// While deferred: connect a new wire branch at (2005, 64, 1)
		$world->setBlockAt(2005, 63, 1, VanillaBlocks::STONE(), false);
		$world->setBlockAt(2005, 64, 1, VanillaBlocks::REDSTONE_WIRE(), true);

		$engine->tick(1);
		$engine->tick(2);

		/** @var RedstoneWire $newBranch */
		$newBranch = $world->getBlockAt(2005, 64, 1);
		self::assertSame(14, $newBranch->getOutputSignalStrength(), "Newly placed branch must be integrated and powered");
	}

	public function testNetworkMutationDuringDeferralChangeSource() : void{
		[$engine, $world] = $this->createEnvironment();

		$wireCount = 4200;
		for($i = 0; $i < $wireCount; ++$i){
			$world->setBlockAt($i, 63, 0, VanillaBlocks::STONE(), false);
			$world->setBlockAt($i, 64, 0, VanillaBlocks::REDSTONE_WIRE(), false);
		}

		$world->setBlockAt(2005, 63, 0, VanillaBlocks::REDSTONE(), false);

		$engine->getWires()->update($world->getBlockAt(0, 64, 0));
		self::assertTrue($engine->getWires()->hasDeferred(), "Network exceeding 4096 wires must defer continuation");

		// While deferred: remove the power source at (2005, 63, 0)
		$world->setBlockAt(2005, 63, 0, VanillaBlocks::STONE(), true);

		$engine->tick(1);
		$engine->tick(2);

		/** @var RedstoneWire $wireAt2005 */
		$wireAt2005 = $world->getBlockAt(2005, 64, 0);
		self::assertSame(0, $wireAt2005->getOutputSignalStrength(), "Network must depower when source is removed during deferral");
	}

	public function testNetworkMutationDuringDeferralVerticalCut() : void{
		[$engine, $world] = $this->createEnvironment();

		$wireCount = 4200;
		for($i = 0; $i < $wireCount; ++$i){
			$world->setBlockAt($i, 63, 0, VanillaBlocks::STONE(), false);
			$world->setBlockAt($i, 64, 0, VanillaBlocks::REDSTONE_WIRE(), false);
		}

		// Configure wire 2006 to be a step up at (2006, 65, 0)
		$world->setBlockAt(2006, 64, 0, VanillaBlocks::STONE(), false);
		$world->setBlockAt(2006, 65, 0, VanillaBlocks::REDSTONE_WIRE(), false);

		$world->setBlockAt(2005, 63, 0, VanillaBlocks::REDSTONE(), false);

		$engine->getWires()->update($world->getBlockAt(0, 64, 0));
		self::assertTrue($engine->getWires()->hasDeferred(), "Network exceeding 4096 wires must defer continuation");

		// While deferred: cut the step-up by placing solid stone overhead at (2005, 65, 0)
		$world->setBlockAt(2005, 65, 0, VanillaBlocks::STONE(), true);

		$engine->tick(1);
		$engine->tick(2);

		/** @var RedstoneWire $stepUpWire */
		$stepUpWire = $world->getBlockAt(2006, 65, 0);
		self::assertSame(0, $stepUpWire->getOutputSignalStrength(), "Step-up connection cut by solid block during defer must not receive power");
	}
}
