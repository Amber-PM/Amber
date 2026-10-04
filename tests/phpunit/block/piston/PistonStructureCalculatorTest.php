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

namespace pocketmine\block\piston;

use PHPUnit\Framework\TestCase;
use pocketmine\block\Block;
use pocketmine\block\BlockBreakInfo;
use pocketmine\block\BlockIdentifier;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\BlockTypeInfo;
use pocketmine\block\VanillaBlocks;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\world\World;

final class PistonStructureCalculatorTest extends TestCase{

	/**
	 * @return array{World, array<string, Block>}
	 */
	private function createTestWorld() : array{
		/** @var array<string, Block> $blocks */
		$blocks = [];
		$world = $this->getMockBuilder(World::class)
			->disableOriginalConstructor()
			->onlyMethods(["getBlockAt", "setBlockAt", "isInWorld", "isChunkLoaded"])
			->getMock();

		$world->method("isInWorld")->willReturn(true);
		$world->method("isChunkLoaded")->willReturn(true);

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

		$world->method("setBlockAt")->willReturnCallback(function(int $x, int $y, int $z, Block $block) use (&$blocks, $world) : bool{
			$key = "$x:$y:$z";
			$clone = clone $block;
			$clone->position($world, $x, $y, $z);
			$blocks[$key] = $clone;
			return true;
		});

		return [$world, $blocks];
	}

	private function setBlock(World $world, Vector3 $pos, Block $block) : void{
		$world->setBlockAt($pos->getFloorX(), $pos->getFloorY(), $pos->getFloorZ(), $block);
	}

	public function testPushSingleBlock() : void{
		[$world] = $this->createTestWorld();
		$pistonPos = new Vector3(0, 64, 0);
		$this->setBlock($world, new Vector3(1, 64, 0), VanillaBlocks::STONE());

		$calc = new PistonStructureCalculator($world, $pistonPos, Facing::EAST, extending: true);
		self::assertTrue($calc->calculate());

		$toMove = $calc->getBlocksToMove();
		self::assertCount(1, $toMove);
		self::assertTrue($toMove[0]->equals(new Vector3(1, 64, 0)));
		self::assertEmpty($calc->getBlocksToDestroy());
	}

	public function testPushUpTo12Blocks() : void{
		[$world] = $this->createTestWorld();
		$pistonPos = new Vector3(0, 64, 0);

		// Place 12 stone blocks from X=1 to X=12
		for($x = 1; $x <= 12; ++$x){
			$this->setBlock($world, new Vector3($x, 64, 0), VanillaBlocks::STONE());
		}

		$calc = new PistonStructureCalculator($world, $pistonPos, Facing::EAST, extending: true);
		self::assertTrue($calc->calculate());

		$toMove = $calc->getBlocksToMove();
		self::assertCount(12, $toMove);

		// Verify reverse topological order: X=12 must move first, X=1 moves last
		self::assertTrue($toMove[0]->equals(new Vector3(12, 64, 0)));
		self::assertTrue($toMove[11]->equals(new Vector3(1, 64, 0)));
	}

	public function testPushExceeding12BlocksFails() : void{
		[$world] = $this->createTestWorld();
		$pistonPos = new Vector3(0, 64, 0);

		// Place 13 stone blocks from X=1 to X=13
		for($x = 1; $x <= 13; ++$x){
			$this->setBlock($world, new Vector3($x, 64, 0), VanillaBlocks::STONE());
		}

		$calc = new PistonStructureCalculator($world, $pistonPos, Facing::EAST, extending: true);
		self::assertFalse($calc->calculate());
		self::assertEmpty($calc->getBlocksToMove());
	}

	public function testPushBlockedByImmovable() : void{
		[$world] = $this->createTestWorld();
		$pistonPos = new Vector3(0, 64, 0);
		$this->setBlock($world, new Vector3(1, 64, 0), VanillaBlocks::STONE());
		$this->setBlock($world, new Vector3(2, 64, 0), VanillaBlocks::OBSIDIAN());

		$calc = new PistonStructureCalculator($world, $pistonPos, Facing::EAST, extending: true);
		self::assertFalse($calc->calculate());
	}

	public function testPushBreaksBreakableBlock() : void{
		[$world] = $this->createTestWorld();
		$pistonPos = new Vector3(0, 64, 0);
		$this->setBlock($world, new Vector3(1, 64, 0), VanillaBlocks::STONE());
		$this->setBlock($world, new Vector3(2, 64, 0), VanillaBlocks::TORCH());

		$calc = new PistonStructureCalculator($world, $pistonPos, Facing::EAST, extending: true);
		self::assertTrue($calc->calculate());

		$toMove = $calc->getBlocksToMove();
		self::assertCount(1, $toMove);
		self::assertTrue($toMove[0]->equals(new Vector3(1, 64, 0)));

		$toDestroy = $calc->getBlocksToDestroy();
		self::assertCount(1, $toDestroy);
		self::assertTrue($toDestroy[0]->equals(new Vector3(2, 64, 0)));
	}

	public function testPushSlimeClusterBranch() : void{
		[$world] = $this->createTestWorld();
		$pistonPos = new Vector3(0, 64, 0);

		$this->setBlock($world, new Vector3(1, 64, 0), VanillaBlocks::SLIME());
		$this->setBlock($world, new Vector3(1, 65, 0), VanillaBlocks::STONE()); // attached above
		$this->setBlock($world, new Vector3(1, 64, 1), VanillaBlocks::OAK_PLANKS()); // attached side

		$calc = new PistonStructureCalculator($world, $pistonPos, Facing::EAST, extending: true);
		self::assertTrue($calc->calculate());

		$toMove = $calc->getBlocksToMove();
		self::assertCount(3, $toMove);
	}

	public function testPushSlimeHoneyNonAdhesion() : void{
		[$world] = $this->createTestWorld();
		$pistonPos = new Vector3(0, 64, 0);

		$honey = new class(new BlockIdentifier(BlockTypeIds::HONEY_BLOCK), "Honey Block", new BlockTypeInfo(BlockBreakInfo::instant())) extends \pocketmine\block\Transparent{};

		$this->setBlock($world, new Vector3(1, 64, 0), VanillaBlocks::SLIME());
		$this->setBlock($world, new Vector3(1, 65, 0), $honey); // Honey adjacent to Slime

		$calc = new PistonStructureCalculator($world, $pistonPos, Facing::EAST, extending: true);
		self::assertTrue($calc->calculate());

		$toMove = $calc->getBlocksToMove();
		// Honey does not stick to Slime, so only Slime moves!
		self::assertCount(1, $toMove);
		self::assertTrue($toMove[0]->equals(new Vector3(1, 64, 0)));
	}

	public function testStickyRetractPullsAttachedBlock() : void{
		[$world] = $this->createTestWorld();
		$pistonPos = new Vector3(0, 64, 0);
		// Head is at (1, 64, 0), attached stone is at (2, 64, 0)
		$this->setBlock($world, new Vector3(2, 64, 0), VanillaBlocks::STONE());

		$calc = new PistonStructureCalculator($world, $pistonPos, Facing::EAST, extending: false);
		self::assertTrue($calc->calculate());

		$toMove = $calc->getBlocksToMove();
		self::assertCount(1, $toMove);
		self::assertTrue($toMove[0]->equals(new Vector3(2, 64, 0)));
	}

	public function testPushAirDirectlyInFront() : void{
		[$world] = $this->createTestWorld();
		$pistonPos = new Vector3(0, 64, 0);

		$calc = new PistonStructureCalculator($world, $pistonPos, Facing::EAST, extending: true);
		self::assertTrue($calc->calculate());
		self::assertEmpty($calc->getBlocksToMove());
		self::assertEmpty($calc->getBlocksToDestroy());
	}

	public function testPushBreakableDirectlyInFront() : void{
		[$world] = $this->createTestWorld();
		$pistonPos = new Vector3(0, 64, 0);
		$this->setBlock($world, new Vector3(1, 64, 0), VanillaBlocks::TORCH());

		$calc = new PistonStructureCalculator($world, $pistonPos, Facing::EAST, extending: true);
		self::assertTrue($calc->calculate());
		self::assertEmpty($calc->getBlocksToMove());
		self::assertCount(1, $calc->getBlocksToDestroy());
		self::assertTrue($calc->getBlocksToDestroy()[0]->equals(new Vector3(1, 64, 0)));
	}

	public function testStickyRetractPullingSlimeCluster() : void{
		[$world] = $this->createTestWorld();
		$pistonPos = new Vector3(0, 64, 0);
		// Attached Slime at (2, 64, 0) with attached Stone at (2, 65, 0)
		$this->setBlock($world, new Vector3(2, 64, 0), VanillaBlocks::SLIME());
		$this->setBlock($world, new Vector3(2, 65, 0), VanillaBlocks::STONE());

		$calc = new PistonStructureCalculator($world, $pistonPos, Facing::EAST, extending: false);
		self::assertTrue($calc->calculate());

		$toMove = $calc->getBlocksToMove();
		self::assertCount(2, $toMove);
	}

	public function testStickyRetractOverloadedClusterLeavesBlocks() : void{
		[$world] = $this->createTestWorld();
		$pistonPos = new Vector3(0, 64, 0);

		// Place 13 attached slime blocks (cluster > 12)
		for($y = 0; $y <= 12; ++$y){
			$this->setBlock($world, new Vector3(2, 64 + $y, 0), VanillaBlocks::SLIME());
		}

		$calc = new PistonStructureCalculator($world, $pistonPos, Facing::EAST, extending: false);
		self::assertTrue($calc->calculate());
		// Overloaded: sticky piston retracts without pulling
		self::assertEmpty($calc->getBlocksToMove());
	}
}
