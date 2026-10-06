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

namespace pocketmine\world\portal;

use PHPUnit\Framework\TestCase;
use pocketmine\block\Block;
use pocketmine\block\NetherPortal;
use pocketmine\block\VanillaBlocks;
use pocketmine\item\VanillaItems;
use pocketmine\math\Axis;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\sound\Sound;
use pocketmine\world\World;

final class NetherPortalDetectorTest extends TestCase{

	/** @var array<string, Block> */
	private array $blocks = [];

	private function createMockWorld() : World{
		$this->blocks = [];
		$world = $this->getMockBuilder(World::class)->disableOriginalConstructor()->onlyMethods([
			"getBlockAt",
			"setBlockAt",
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

		$world->method("setBlockAt")->willReturnCallback(function(int $x, int $y, int $z, Block $block) use ($world) : bool{
			$key = "$x:$y:$z";
			$clone = clone $block;
			$clone->position($world, $x, $y, $z);
			$this->blocks[$key] = $clone;
			return true;
		});

		$world->method("addSound")->willReturnCallback(function(Vector3 $pos, Sound $sound) : void{
			// NOOP
		});

		return $world;
	}

	private function buildPortalFrame(
		World $world,
		int $axis,
		int $hMin,
		int $hMax,
		int $yMin,
		int $yMax,
		int $c,
		bool $withCorners = true
	) : void{
		$obsidian = VanillaBlocks::OBSIDIAN();

		// Bottom & Top
		$startH = $withCorners ? $hMin - 1 : $hMin;
		$endH = $withCorners ? $hMax + 1 : $hMax;
		for($h = $startH; $h <= $endH; ++$h){
			$x = $axis === Axis::X ? $h : $c;
			$z = $axis === Axis::X ? $c : $h;
			$world->setBlockAt($x, $yMin - 1, $z, $obsidian);
			$world->setBlockAt($x, $yMax + 1, $z, $obsidian);
		}

		// Sides
		for($y = $yMin; $y <= $yMax; ++$y){
			$leftX = $axis === Axis::X ? $hMin - 1 : $c;
			$leftZ = $axis === Axis::X ? $c : $hMin - 1;
			$rightX = $axis === Axis::X ? $hMax + 1 : $c;
			$rightZ = $axis === Axis::X ? $c : $hMax + 1;

			$world->setBlockAt($leftX, $y, $leftZ, $obsidian);
			$world->setBlockAt($rightX, $y, $rightZ, $obsidian);
		}
	}

	public function testDetectAndActivateStandard2x3AxisX() : void{
		$world = $this->createMockWorld();
		$this->buildPortalFrame($world, Axis::X, 1, 2, 65, 67, 10, true);

		$triggerPos = new Vector3(1, 65, 10);
		$detection = NetherPortalDetector::detect($world, $triggerPos);

		self::assertNotNull($detection);
		self::assertSame(Axis::X, $detection->getAxis());
		self::assertSame(2, $detection->getWidth());
		self::assertSame(3, $detection->getHeight());
		self::assertSame(1, $detection->getHMin());
		self::assertSame(2, $detection->getHMax());
		self::assertSame(65, $detection->getYMin());
		self::assertSame(67, $detection->getYMax());
		self::assertSame(10, $detection->getC());

		$activated = NetherPortalDetector::tryActivate($world, $triggerPos);
		self::assertTrue($activated);

		for($x = 1; $x <= 2; ++$x){
			for($y = 65; $y <= 67; ++$y){
				$block = $world->getBlockAt($x, $y, 10);
				self::assertInstanceOf(NetherPortal::class, $block);
				self::assertSame(Axis::X, $block->getAxis());
			}
		}
	}

	public function testDetectAndActivateStandard2x3AxisZWithoutCorners() : void{
		$world = $this->createMockWorld();
		// Cornerless frame along Axis::Z
		$this->buildPortalFrame($world, Axis::Z, 20, 21, 65, 67, 5, false);

		$triggerPos = new Vector3(5, 65, 20);
		$detection = NetherPortalDetector::detect($world, $triggerPos);

		self::assertNotNull($detection);
		self::assertSame(Axis::Z, $detection->getAxis());
		self::assertSame(2, $detection->getWidth());
		self::assertSame(3, $detection->getHeight());

		$activated = NetherPortalDetector::tryActivate($world, $triggerPos);
		self::assertTrue($activated);

		for($z = 20; $z <= 21; ++$z){
			for($y = 65; $y <= 67; ++$y){
				$block = $world->getBlockAt(5, $y, $z);
				self::assertInstanceOf(NetherPortal::class, $block);
				self::assertSame(Axis::Z, $block->getAxis());
			}
		}
	}

	public function testDetectMaxDimensions21x21() : void{
		$world = $this->createMockWorld();
		$this->buildPortalFrame($world, Axis::X, 0, 20, 65, 85, 0, true);

		$triggerPos = new Vector3(10, 75, 0);
		$detection = NetherPortalDetector::detect($world, $triggerPos);

		self::assertNotNull($detection);
		self::assertSame(21, $detection->getWidth());
		self::assertSame(21, $detection->getHeight());
	}

	public function testWidthBelowMinimumFails() : void{
		$world = $this->createMockWorld();
		// Width 1
		$this->buildPortalFrame($world, Axis::X, 5, 5, 65, 67, 0, true);

		$triggerPos = new Vector3(5, 65, 0);
		$detection = NetherPortalDetector::detect($world, $triggerPos);
		self::assertNull($detection);
	}

	public function testHeightBelowMinimumFails() : void{
		$world = $this->createMockWorld();
		// Height 2
		$this->buildPortalFrame($world, Axis::X, 5, 6, 65, 66, 0, true);

		$triggerPos = new Vector3(5, 65, 0);
		$detection = NetherPortalDetector::detect($world, $triggerPos);
		self::assertNull($detection);
	}

	public function testWidthAboveMaximumFails() : void{
		$world = $this->createMockWorld();
		// Width 22
		$this->buildPortalFrame($world, Axis::X, 0, 21, 65, 67, 0, true);

		$triggerPos = new Vector3(5, 65, 0);
		$detection = NetherPortalDetector::detect($world, $triggerPos);
		self::assertNull($detection);
	}

	public function testBrokenFrameMissingObsidianFails() : void{
		$world = $this->createMockWorld();
		$this->buildPortalFrame($world, Axis::X, 1, 2, 65, 67, 0, true);

		// Remove one side obsidian block
		$world->setBlockAt(0, 66, 0, VanillaBlocks::DIRT());

		$triggerPos = new Vector3(1, 65, 0);
		$detection = NetherPortalDetector::detect($world, $triggerPos);
		self::assertNull($detection);
	}

	public function testInnerObstructionFails() : void{
		$world = $this->createMockWorld();
		$this->buildPortalFrame($world, Axis::X, 1, 2, 65, 67, 0, true);

		// Place obstruction inside
		$world->setBlockAt(1, 66, 0, VanillaBlocks::STONE());

		$triggerPos = new Vector3(1, 65, 0);
		$detection = NetherPortalDetector::detect($world, $triggerPos);
		self::assertNull($detection);
	}

	public function testFlintSteelActivation() : void{
		$world = $this->createMockWorld();
		$this->buildPortalFrame($world, Axis::X, 1, 2, 65, 67, 0, true);

		$player = $this->getMockBuilder(Player::class)
			->disableOriginalConstructor()
			->onlyMethods(["getWorld"])
			->getMock();
		$player->method("getWorld")->willReturn($world);
		(new \ReflectionProperty(\pocketmine\entity\Entity::class, "closed"))->setValue($player, true);
		(new \ReflectionProperty(Player::class, "logger"))->setValue($player, $this->createMock(\Logger::class));

		$flintSteel = VanillaItems::FLINT_AND_STEEL();
		$air = VanillaBlocks::AIR();
		$air->position($world, 1, 65, 0);
		$obsidianFloor = VanillaBlocks::OBSIDIAN();
		$obsidianFloor->position($world, 1, 64, 0);

		$returnedItems = [];
		$res = $flintSteel->onInteractBlock($player, $air, $obsidianFloor, Facing::UP, Vector3::zero(), $returnedItems);
		self::assertSame(\pocketmine\item\ItemUseResult::SUCCESS, $res);

		// The entire frame should now be NetherPortal
		$portal = $world->getBlockAt(1, 65, 0);
		self::assertInstanceOf(NetherPortal::class, $portal);
		$portal2 = $world->getBlockAt(2, 67, 0);
		self::assertInstanceOf(NetherPortal::class, $portal2);
	}

	public function testFireChargeActivation() : void{
		$world = $this->createMockWorld();
		$this->buildPortalFrame($world, Axis::Z, 1, 2, 65, 67, 0, true);

		$player = $this->getMockBuilder(Player::class)
			->disableOriginalConstructor()
			->onlyMethods(["getWorld"])
			->getMock();
		$player->method("getWorld")->willReturn($world);
		(new \ReflectionProperty(\pocketmine\entity\Entity::class, "closed"))->setValue($player, true);
		(new \ReflectionProperty(Player::class, "logger"))->setValue($player, $this->createMock(\Logger::class));

		$fireCharge = VanillaItems::FIRE_CHARGE();
		$air = VanillaBlocks::AIR();
		$air->position($world, 0, 65, 1);
		$obsidianFloor = VanillaBlocks::OBSIDIAN();
		$obsidianFloor->position($world, 0, 64, 1);

		$returnedItems = [];
		$res = $fireCharge->onInteractBlock($player, $air, $obsidianFloor, Facing::UP, Vector3::zero(), $returnedItems);
		self::assertSame(\pocketmine\item\ItemUseResult::SUCCESS, $res);
		self::assertSame(0, $fireCharge->getCount());

		$portal = $world->getBlockAt(0, 65, 1);
		self::assertInstanceOf(NetherPortal::class, $portal);
		self::assertSame(Axis::Z, $portal->getAxis());
	}
}
