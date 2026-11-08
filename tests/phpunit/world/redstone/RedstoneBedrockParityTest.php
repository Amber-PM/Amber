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

require_once __DIR__ . '/../../../support/redstone/NativeRedstoneWorldTrait.php';

use PHPUnit\Framework\TestCase;
use pocketmine\block\Block;
use pocketmine\block\Lever;
use pocketmine\block\RedstoneComparator;
use pocketmine\block\RedstoneRepeater;
use pocketmine\block\RedstoneTorch;
use pocketmine\block\tile\Chest;
use pocketmine\block\tile\PistonArm;
use pocketmine\block\utils\LeverFacing;
use pocketmine\block\VanillaBlocks;
use pocketmine\item\VanillaItems;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\world\World;
use function array_filter;
use function count;
use function in_array;

final class RedstoneBedrockParityTest extends TestCase{
	use NativeRedstoneWorldTrait;

	protected function tearDown() : void{
		$this->closeNativeWorlds();
	}

	private function floor(World $world, int $minX = 0, int $maxX = 31, int $minZ = 0, int $maxZ = 31) : void{
		for($x = $minX; $x <= $maxX; ++$x){
			for($z = $minZ; $z <= $maxZ; ++$z){
				$world->setBlockAt($x, 63, $z, VanillaBlocks::STONE(), false);
			}
		}
	}

	private function comparatorOutput(World $world, int $x, int $z) : int{
		$comparator = $world->getBlockAt($x, 64, $z);
		self::assertInstanceOf(RedstoneComparator::class, $comparator);
		return $comparator->getOutputSignalStrength();
	}

	public function testComparatorIgnoresRedstoneBlockOnItsSide() : void{
		foreach([false, true] as $sideIsWire){
			$world = $this->nativeWorld();
			$this->floor($world, 0, 15, 0, 15);
			$world->setBlockAt(7, 64, 8, VanillaBlocks::REDSTONE());
			$world->setBlockAt(8, 64, 8, VanillaBlocks::REDSTONE_COMPARATOR()->setFacing(Facing::WEST)->setSubtractMode(true));
			if($sideIsWire){
				$world->setBlockAt(8, 64, 6, VanillaBlocks::REDSTONE());
				$world->setBlockAt(8, 64, 7, VanillaBlocks::REDSTONE_WIRE());
			}else{
				$world->setBlockAt(8, 64, 7, VanillaBlocks::REDSTONE());
			}
			$this->nativeTicks($world, 10);
			//only wire and diodes count as side input on bedrock
			self::assertSame($sideIsWire ? 0 : 15, $this->comparatorOutput($world, 8, 8), $sideIsWire ? "wire side" : "redstone block side");
		}
	}

	public function testSelfPoweredTorchBurnsOut() : void{
		$world = $this->nativeWorld();
		$this->floor($world, 0, 15, 0, 15);
		$world->setBlockAt(7, 64, 8, VanillaBlocks::STONE());
		$world->setBlockAt(8, 65, 8, VanillaBlocks::STONE());
		$world->setBlockAt(7, 65, 8, VanillaBlocks::REDSTONE_WIRE());
		$world->setBlockAt(8, 64, 8, VanillaBlocks::REDSTONE_TORCH()->setFacing(Facing::EAST));
		$engine = $this->engineOf($world);
		$offs = 0;
		$lit = true;
		for($tick = 0; $tick < 60 && !$engine->getTorchBurnout()->isBurntOut(new Vector3(8, 64, 8), 0); ++$tick){
			$this->nativeTicks($world);
			$torch = $world->getBlockAt(8, 64, 8);
			self::assertInstanceOf(RedstoneTorch::class, $torch);
			if($lit && !$torch->isLit()){
				++$offs;
			}
			$lit = $torch->isLit();
		}
		self::assertSame(8, $offs);
		self::assertFalse($lit);
	}

	private function setLever(World $world, int $x, int $z, bool $on) : void{
		$lever = $world->getBlockAt($x, 64, $z);
		self::assertInstanceOf(Lever::class, $lever);
		$world->setBlockAt($x, 64, $z, $lever->setActivated($on));
	}

	/**
	 * @param list<int> $leverChanges ticks at which the lever flips
	 * @return list<bool> repeater output per tick
	 */
	private function repeaterTrace(bool $startOn, array $leverChanges, int $ticks, int $delay = 4) : array{
		$world = $this->nativeWorld();
		$this->floor($world, 0, 15, 0, 15);
		$world->setBlockAt(7, 64, 8, VanillaBlocks::LEVER()->setFacing(LeverFacing::UP_AXIS_X)->setActivated($startOn));
		$world->setBlockAt(8, 64, 8, VanillaBlocks::REDSTONE_REPEATER()->setFacing(Facing::WEST)->setDelay($delay));
		$this->nativeTicks($world, 20);
		$on = $startOn;
		$trace = [];
		for($tick = 0; $tick < $ticks; ++$tick){
			if(in_array($tick, $leverChanges, true)){
				$on = !$on;
				$this->setLever($world, 7, 8, $on);
			}
			$this->nativeTicks($world);
			$repeater = $world->getBlockAt(8, 64, 8);
			self::assertInstanceOf(RedstoneRepeater::class, $repeater);
			$trace[] = $repeater->isPowered();
		}
		return $trace;
	}

	public function testRepeaterStretchesShortOnPulseToItsDelay() : void{
		$trace = $this->repeaterTrace(false, [0, 2], 40);
		self::assertSame(8, count(array_filter($trace)), "a 2 tick pulse into a 4 delay repeater lasts 8 ticks");
		self::assertFalse($trace[39]);
	}

	public function testShortestDelayRepeaterDoesNotStretchPulses() : void{
		//stretching at one tick delay is java only
		$trace = $this->repeaterTrace(false, [0, 1], 20, 1);
		self::assertNotContains(true, $trace, "a 1 tick pulse into a 1 delay repeater is not stretched");
	}

	public function testRepeaterIgnoresShortOffPulse() : void{
		$trace = $this->repeaterTrace(true, [0, 2], 40);
		self::assertNotContains(false, $trace, "a 2 tick off pulse never reaches the output");
	}

	/**
	 * comparator at 8 facing west reads 7 and through it 6
	 *
	 * @param \Closure(World) : void $build
	 */
	private function measure(\Closure $build) : int{
		$world = $this->nativeWorld();
		$this->floor($world, 0, 15, 0, 15);
		$build($world);
		$world->setBlockAt(8, 64, 8, VanillaBlocks::REDSTONE_COMPARATOR()->setFacing(Facing::WEST));
		$this->nativeTicks($world, 10);
		return $this->comparatorOutput($world, 8, 8);
	}

	public function testComparatorMeasuresBlocksAndReadsThroughOneBlock() : void{
		self::assertSame(14, $this->measure(fn(World $w) => $w->setBlockAt(7, 64, 8, VanillaBlocks::CAKE())), "cake behind");
		self::assertSame(10, $this->measure(fn(World $w) => $w->setBlockAt(7, 64, 8, VanillaBlocks::CAKE()->setBites(2))), "eaten cake behind");
		self::assertSame(7, $this->measure(fn(World $w) => $w->setBlockAt(7, 64, 8, VanillaBlocks::RESPAWN_ANCHOR()->setCharges(2))), "anchor behind");
		self::assertSame(15, $this->measure(fn(World $w) => $w->setBlockAt(7, 64, 8, VanillaBlocks::END_PORTAL_FRAME()->setEye(true))), "portal frame behind");

		self::assertSame(14, $this->measure(function(World $w) : void{
			$w->setBlockAt(7, 64, 8, VanillaBlocks::STONE());
			$w->setBlockAt(6, 64, 8, VanillaBlocks::CAKE());
		}), "cake through stone");
		self::assertSame(14, $this->measure(function(World $w) : void{
			$w->setBlockAt(7, 64, 8, VanillaBlocks::PISTON()->setFacing(Facing::UP));
			$w->setBlockAt(6, 64, 8, VanillaBlocks::CAKE());
		}), "cake through a piston");
		self::assertSame(3, $this->measure(function(World $w) : void{
			$w->setBlockAt(7, 64, 8, VanillaBlocks::STONE());
			$w->setBlockAt(6, 64, 8, VanillaBlocks::ITEM_FRAME()->setFacing(Facing::WEST)->setFramedItem(VanillaItems::DIAMOND())->setItemRotation(2));
		}), "item frame on the block in between");
		self::assertSame(0, $this->measure(function(World $w) : void{
			$w->setBlockAt(7, 64, 8, VanillaBlocks::STONE());
			$w->setBlockAt(6, 64, 8, VanillaBlocks::ITEM_FRAME()->setFacing(Facing::NORTH)->setFramedItem(VanillaItems::DIAMOND()));
			$w->setBlockAt(6, 64, 9, VanillaBlocks::STONE());
		}), "item frame hung elsewhere");
	}

	public function testComparatorThroughPoweredBlockIgnoresItsPower() : void{
		$output = $this->measure(function(World $w) : void{
			$w->setBlockAt(7, 64, 8, VanillaBlocks::STONE());
			$w->setBlockAt(7, 64, 9, VanillaBlocks::LEVER()->setFacing(LeverFacing::UP_AXIS_X)->setActivated(true));
			$w->setBlockAt(7, 65, 8, VanillaBlocks::REDSTONE_TORCH());
			$w->setBlockAt(7, 63, 8, VanillaBlocks::REDSTONE());
			$w->setBlockAt(6, 64, 8, VanillaBlocks::CHEST());
			$chest = $w->getTileAt(6, 64, 8);
			self::assertInstanceOf(Chest::class, $chest);
			$chest->getInventory()->setItem(0, VanillaItems::DIAMOND());
		});
		//one diamond in a chest gives 1 no matter how the block in between is powered
		self::assertSame(1, $output);
	}

	public function testUpdateQueuedForUnloadedChunkRunsAfterReload() : void{
		$world = $this->nativeWorld();
		$this->floor($world, 16, 31, 0, 15);
		$world->setBlockAt(24, 64, 8, VanillaBlocks::PISTON()->setFacing(Facing::EAST));
		$this->nativeTicks($world, 5);
		$world->setBlockAt(23, 64, 8, VanillaBlocks::REDSTONE(), false);
		$engine = $this->engineOf($world);
		$engine->request(24, 64, 8);
		$this->unloadNativeChunk($world, 1, 0);
		$this->nativeTicks($world, 5);
		self::assertSame(1, $engine->getParkedCount());
		$this->reloadNativeChunk($world, 1, 0);
		$this->nativeTicks($world, 10);
		self::assertSame(0, $engine->getParkedCount());
		$arm = $world->getTileAt(24, 64, 8);
		self::assertInstanceOf(PistonArm::class, $arm);
		self::assertTrue($arm->isExtended(), "the update queued while unloaded still runs");
	}

	/**
	 * rows of the minecraft wiki piston table in the bedrock column
	 *
	 * @return \Generator<string, array{\Closure() : Block, string}>
	 */
	public static function pushProvider() : \Generator{
		yield "lantern moves" => [fn() => VanillaBlocks::LANTERN(), "moves"];
		yield "candle moves" => [fn() => VanillaBlocks::CANDLE(), "moves"];
		yield "bell moves" => [fn() => VanillaBlocks::BELL(), "moves"];
		yield "lectern moves" => [fn() => VanillaBlocks::LECTERN(), "moves"];
		yield "daylight sensor moves" => [fn() => VanillaBlocks::DAYLIGHT_SENSOR(), "moves"];
		yield "chiseled bookshelf moves" => [fn() => VanillaBlocks::CHISELED_BOOKSHELF(), "moves"];
		yield "chest moves" => [fn() => VanillaBlocks::CHEST(), "moves"];
		yield "shulker box breaks" => [fn() => VanillaBlocks::SHULKER_BOX(), "breaks"];
		yield "campfire breaks" => [fn() => VanillaBlocks::CAMPFIRE(), "breaks"];
		yield "jukebox stays" => [fn() => VanillaBlocks::JUKEBOX(), "stays"];
		yield "beacon stays" => [fn() => VanillaBlocks::BEACON(), "stays"];
	}

	/**
	 * @param \Closure() : Block $make
	 * @dataProvider pushProvider
	 */
	public function testPistonMovesBlocksLikeBedrock(\Closure $make, string $expected) : void{
		$world = $this->nativeWorld();
		$this->floor($world, 0, 15, 0, 15);
		$block = $make();
		$world->setBlockAt(8, 64, 8, VanillaBlocks::PISTON()->setFacing(Facing::EAST));
		$world->setBlockAt(9, 64, 8, $block);
		$this->nativeTicks($world, 5);
		$world->setBlockAt(7, 64, 8, VanillaBlocks::REDSTONE());
		$this->nativeTicks($world, 10);
		$arm = $world->getTileAt(8, 64, 8);
		self::assertInstanceOf(PistonArm::class, $arm);
		self::assertFalse($arm->isMoving());
		$at9 = $world->getBlockAt(9, 64, 8);
		$at10 = $world->getBlockAt(10, 64, 8);
		match($expected){
			"moves" => self::assertSame($block->getTypeId(), $at10->getTypeId(), "pushed to the next block"),
			"breaks" => self::assertSame(VanillaBlocks::AIR()->getTypeId(), $at10->getTypeId(), "nothing pushed"),
			"stays" => self::assertSame($block->getTypeId(), $at9->getTypeId(), "left in place"),
		};
		self::assertSame($expected !== "stays", $arm->isExtended());
		if($expected === "moves" && $block->getIdInfo()->getTileClass() !== null){
			self::assertNotNull($world->getTileAt(10, 64, 8), "tile moved with the block");
		}
	}

	public function testPistonStartsTwoTicksAfterPower() : void{
		$world = $this->nativeWorld();
		$this->floor($world, 0, 15, 0, 15);
		$world->setBlockAt(7, 64, 8, VanillaBlocks::LEVER()->setFacing(LeverFacing::UP_AXIS_X));
		$world->setBlockAt(8, 64, 8, VanillaBlocks::PISTON()->setFacing(Facing::EAST));
		$this->nativeTicks($world, 10);
		$this->setLever($world, 7, 8, true);
		//tick one sees the power
		$this->nativeTicks($world, 2);
		$arm = $world->getTileAt(8, 64, 8);
		self::assertInstanceOf(PistonArm::class, $arm);
		self::assertFalse($arm->isMoving());
		self::assertFalse($arm->isExtended());
		$this->nativeTicks($world);
		self::assertTrue($arm->isMoving());
		self::assertTrue($arm->isExtended());
	}

	public function testStickyPistonShortPulseKeepsItsBlock() : void{
		$world = $this->nativeWorld();
		$this->floor($world, 0, 15, 0, 15);
		$world->setBlockAt(7, 64, 8, VanillaBlocks::LEVER()->setFacing(LeverFacing::UP_AXIS_X));
		$world->setBlockAt(8, 64, 8, VanillaBlocks::STICKY_PISTON()->setFacing(Facing::EAST));
		$world->setBlockAt(9, 64, 8, VanillaBlocks::COBBLESTONE());
		$this->nativeTicks($world, 10);
		$this->setLever($world, 7, 8, true);
		$this->nativeTicks($world, 2);
		$this->setLever($world, 7, 8, false);
		$this->nativeTicks($world, 20);
		$arm = $world->getTileAt(8, 64, 8);
		self::assertInstanceOf(PistonArm::class, $arm);
		self::assertFalse($arm->isMoving());
		self::assertFalse($arm->isExtended());
		self::assertSame(VanillaBlocks::COBBLESTONE()->getTypeId(), $world->getBlockAt(9, 64, 8)->getTypeId());
		self::assertSame(VanillaBlocks::AIR()->getTypeId(), $world->getBlockAt(10, 64, 8)->getTypeId());
	}
}
