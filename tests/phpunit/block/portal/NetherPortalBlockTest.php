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

namespace pocketmine\block\portal;

use PHPUnit\Framework\TestCase;
use pocketmine\block\Block;
use pocketmine\block\NetherPortal;
use pocketmine\block\utils\SupportType;
use pocketmine\block\VanillaBlocks;
use pocketmine\item\VanillaItems;
use pocketmine\math\Axis;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\world\portal\NetherPortalDetector;
use pocketmine\world\sound\Sound;
use pocketmine\world\World;

final class NetherPortalBlockTest extends TestCase{

	/** @var array<string, Block> */
	private array $blocks = [];

	private function createMockWorld() : World{
		$this->blocks = [];
		$world = $this->getMockBuilder(World::class)->disableOriginalConstructor()->onlyMethods([
			"getBlockAt",
			"setBlockAt",
			"setBlock",
			"isInWorld",
			"addSound"
		])->getMock();

		$world->method("isInWorld")->willReturn(true);

		$world->method("getBlockAt")->willReturnCallback(function(int $x, int $y, int $z) use ($world) : Block{
			$key = "$x:$y:$z";
			if(isset($this->blocks[$key])){
				$b = clone $this->blocks[$key];
				$b->position($world, $x, $y, $z);
				return $b;
			}
			$air = clone VanillaBlocks::AIR();
			$air->position($world, $x, $y, $z);
			return $air;
		});

		$world->method("setBlockAt")->willReturnCallback(function(int $x, int $y, int $z, Block $block, bool $update = true) use ($world) : void{
			$key = "$x:$y:$z";
			$clone = clone $block;
			$clone->position($world, $x, $y, $z);
			$this->blocks[$key] = $clone;

			if($update){
				// Notify the 6 neighbours
				foreach(Facing::ALL as $facing){
					$side = (new Vector3($x, $y, $z))->getSide($facing);
					$neighbor = $world->getBlockAt($side->getFloorX(), $side->getFloorY(), $side->getFloorZ());
					$neighbor->onNearbyBlockChange();
				}
			}
		});

		$world->method("setBlock")->willReturnCallback(function(Vector3 $pos, Block $block, bool $update = true) use ($world) : void{
			$world->setBlockAt($pos->getFloorX(), $pos->getFloorY(), $pos->getFloorZ(), $block, $update);
		});

		$world->method("addSound")->willReturnCallback(function(Vector3 $pos, Sound $sound) : void{
			// NOOP
		});

		return $world;
	}

	public function testNetherPortalProperties() : void{
		$portal = VanillaBlocks::NETHER_PORTAL();
		self::assertInstanceOf(NetherPortal::class, $portal);
		self::assertSame(11, $portal->getLightLevel());
		self::assertFalse($portal->isSolid());
		self::assertEmpty($portal->getCollisionBoxes());
		self::assertSame(SupportType::NONE, $portal->getSupportType(Facing::UP));
		self::assertEmpty($portal->getDrops(VanillaItems::DIAMOND_PICKAXE()));
		self::assertSame(Axis::X, $portal->getAxis());

		$portal->setAxis(Axis::Z);
		self::assertSame(Axis::Z, $portal->getAxis());

		$this->expectException(\InvalidArgumentException::class);
		$portal->setAxis(Axis::Y);
	}

	public function testPortalBlockValidity() : void{
		$world = $this->createMockWorld();

		// Center block at (1, 65, 0)
		$portal = VanillaBlocks::NETHER_PORTAL()->setAxis(Axis::X);
		$portal->position($world, 1, 65, 0);
		$world->setBlockAt(1, 65, 0, $portal, false);

		// Missing neighbors -> invalid
		self::assertFalse($portal->isValid());

		// Place obsidian around it (up, down, west, east)
		$obsidian = VanillaBlocks::OBSIDIAN();
		$world->setBlockAt(1, 66, 0, $obsidian, false); // UP
		$world->setBlockAt(1, 64, 0, $obsidian, false); // DOWN
		$world->setBlockAt(0, 65, 0, $obsidian, false); // WEST (X-1)
		$world->setBlockAt(2, 65, 0, $obsidian, false); // EAST (X+1)

		self::assertTrue($portal->isValid());

		// Perpendicular sides (North/South) being air does NOT make it invalid
		self::assertTrue($portal->isValid());

		// Breaking West obsidian makes it invalid
		$world->setBlockAt(0, 65, 0, VanillaBlocks::AIR(), false);
		self::assertFalse($portal->isValid());

		// Replacing with portal of wrong axis (Axis::Z) makes it invalid
		$wrongAxisPortal = VanillaBlocks::NETHER_PORTAL()->setAxis(Axis::Z);
		$world->setBlockAt(0, 65, 0, $wrongAxisPortal, false);
		self::assertFalse($portal->isValid());

		// Replacing with portal of matching axis (Axis::X) makes it valid
		$matchingAxisPortal = VanillaBlocks::NETHER_PORTAL()->setAxis(Axis::X);
		$world->setBlockAt(0, 65, 0, $matchingAxisPortal, false);
		self::assertTrue($portal->isValid());
	}

	public function testPortalCascadingDestruction() : void{
		$world = $this->createMockWorld();

		// Build a 2x3 portal on Axis::X
		$obsidian = VanillaBlocks::OBSIDIAN();
		for($x = 0; $x <= 3; ++$x){
			$world->setBlockAt($x, 64, 0, $obsidian, false);
			$world->setBlockAt($x, 68, 0, $obsidian, false);
		}
		for($y = 65; $y <= 67; ++$y){
			$world->setBlockAt(0, $y, 0, $obsidian, false);
			$world->setBlockAt(3, $y, 0, $obsidian, false);
		}

		$activated = NetherPortalDetector::tryActivate($world, new Vector3(1, 65, 0));
		self::assertTrue($activated);

		// Verify 6 portal blocks exist
		for($x = 1; $x <= 2; ++$x){
			for($y = 65; $y <= 67; ++$y){
				self::assertInstanceOf(NetherPortal::class, $world->getBlockAt($x, $y, 0));
			}
		}

		// Break one obsidian frame block at (0, 65, 0)
		$world->setBlockAt(0, 65, 0, VanillaBlocks::AIR(), true);

		// Assert all 6 portal blocks cascaded to AIR
		for($x = 1; $x <= 2; ++$x){
			for($y = 65; $y <= 67; ++$y){
				$b = $world->getBlockAt($x, $y, 0);
				self::assertSame(VanillaBlocks::AIR()->getTypeId(), $b->getTypeId(), "Portal at ($x, $y, 0) should have cascaded to air");
			}
		}
	}

	public function testTwoPortalsSharingWallOnlyOneBreaks() : void{
		$world = $this->createMockWorld();

		// Portal 1: X from 1 to 2, Y 65..67, Z=0. Left wall X=0, shared wall X=3.
		// Portal 2: X from 4 to 5, Y 65..67, Z=0. Shared wall X=3, right wall X=6.
		$obsidian = VanillaBlocks::OBSIDIAN();

		// Bottom & top for both
		for($x = 0; $x <= 6; ++$x){
			$world->setBlockAt($x, 64, 0, $obsidian, false);
			$world->setBlockAt($x, 68, 0, $obsidian, false);
		}
		// Side walls
		for($y = 65; $y <= 67; ++$y){
			$world->setBlockAt(0, $y, 0, $obsidian, false); // P1 outer wall
			$world->setBlockAt(3, $y, 0, $obsidian, false); // Shared wall
			$world->setBlockAt(6, $y, 0, $obsidian, false); // P2 outer wall
		}

		// Activate both portals
		self::assertTrue(NetherPortalDetector::tryActivate($world, new Vector3(1, 65, 0)));
		self::assertTrue(NetherPortalDetector::tryActivate($world, new Vector3(4, 65, 0)));

		// Verify both active
		self::assertInstanceOf(NetherPortal::class, $world->getBlockAt(1, 65, 0));
		self::assertInstanceOf(NetherPortal::class, $world->getBlockAt(4, 65, 0));

		// Break outer wall of Portal 1 at (0, 65, 0)
		$world->setBlockAt(0, 65, 0, VanillaBlocks::AIR(), true);

		// Portal 1 should be gone
		for($x = 1; $x <= 2; ++$x){
			for($y = 65; $y <= 67; ++$y){
				self::assertSame(VanillaBlocks::AIR()->getTypeId(), $world->getBlockAt($x, $y, 0)->getTypeId());
			}
		}

		// Portal 2 must remain completely intact
		for($x = 4; $x <= 5; ++$x){
			for($y = 65; $y <= 67; ++$y){
				self::assertInstanceOf(NetherPortal::class, $world->getBlockAt($x, $y, 0));
			}
		}
	}
}
