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
use pocketmine\block\BlockTypeIds;
use pocketmine\block\NetherPortal;
use pocketmine\block\VanillaBlocks;
use pocketmine\math\Axis;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\types\DimensionIds;
use pocketmine\player\Player;
use pocketmine\world\sound\Sound;
use pocketmine\world\World;

final class PortalTeleporterTest extends TestCase{

	/** @var array<string, Block> */
	private array $blocks = [];

	protected function setUp() : void{
		parent::setUp();
		PortalTeleporter::clearRegisteredPortals();
	}

	private function createMockWorld(string $name = "world") : World{
		$this->blocks = [];
		$world = $this->getMockBuilder(World::class)->disableOriginalConstructor()->onlyMethods([
			"getBlockAt",
			"setBlockAt",
			"setBlock",
			"isInWorld",
			"getDisplayName",
			"addSound"
		])->getMock();

		$world->method("isInWorld")->willReturn(true);
		$world->method("getDisplayName")->willReturn($name);

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
		});

		$world->method("setBlock")->willReturnCallback(function(Vector3 $pos, Block $block, bool $update = true) use ($world) : void{
			$world->setBlockAt($pos->getFloorX(), $pos->getFloorY(), $pos->getFloorZ(), $block, $update);
		});

		$world->method("addSound")->willReturnCallback(function(Vector3 $pos, Sound $sound) : void{
			// NOOP
		});

		return $world;
	}

	private function createMockPlayer(World $world, bool $isCreative = false) : Player{
		$player = $this->getMockBuilder(Player::class)
			->disableOriginalConstructor()
			->onlyMethods(["getWorld", "getPosition", "isCreative", "teleport"])
			->getMock();
		$player->method("getWorld")->willReturn($world);
		$player->method("getPosition")->willReturn(new \pocketmine\world\Position(0, 65, 0, $world));
		$player->method("isCreative")->willReturn($isCreative);
		$player->method("teleport")->willReturn(true);

		(new \ReflectionProperty(\pocketmine\entity\Entity::class, "closed"))->setValue($player, true);
		(new \ReflectionProperty(Player::class, "logger"))->setValue($player, $this->createMock(\Logger::class));

		return $player;
	}

	public function testCoordinateScalingOverworldToNether() : void{
		$overworldPos = new Vector3(800, 70, -160);
		$netherPos = PortalTeleporter::scaleCoordinates($overworldPos, DimensionIds::OVERWORLD, DimensionIds::NETHER);

		self::assertEqualsWithDelta(100.0, $netherPos->x, 0.001);
		self::assertEqualsWithDelta(70.0, $netherPos->y, 0.001);
		self::assertEqualsWithDelta(-20.0, $netherPos->z, 0.001);

		// Negative coords with floor
		$pos2 = new Vector3(7, 60, -7);
		$scaled2 = PortalTeleporter::scaleCoordinates($pos2, DimensionIds::OVERWORLD, DimensionIds::NETHER);
		self::assertEqualsWithDelta(0.0, $scaled2->x, 0.001);
		self::assertEqualsWithDelta(-1.0, $scaled2->z, 0.001);

		// Clamping
		$highY = new Vector3(0, 200, 0);
		$scaledHigh = PortalTeleporter::scaleCoordinates($highY, DimensionIds::OVERWORLD, DimensionIds::NETHER);
		self::assertEqualsWithDelta(120.0, $scaledHigh->y, 0.001);

		$lowY = new Vector3(0, 10, 0);
		$scaledLow = PortalTeleporter::scaleCoordinates($lowY, DimensionIds::OVERWORLD, DimensionIds::NETHER);
		self::assertEqualsWithDelta(32.0, $scaledLow->y, 0.001);
	}

	public function testCoordinateScalingNetherToOverworld() : void{
		$netherPos = new Vector3(100, 70, -20);
		$overworldPos = PortalTeleporter::scaleCoordinates($netherPos, DimensionIds::NETHER, DimensionIds::OVERWORLD);

		self::assertEqualsWithDelta(800.0, $overworldPos->x, 0.001);
		self::assertEqualsWithDelta(70.0, $overworldPos->y, 0.001);
		self::assertEqualsWithDelta(-160.0, $overworldPos->z, 0.001);
	}

	public function testCoordinateScalingToEnd() : void{
		$anyPos = new Vector3(500, 60, 200);
		$endPos = PortalTeleporter::scaleCoordinates($anyPos, DimensionIds::OVERWORLD, DimensionIds::THE_END);

		self::assertEqualsWithDelta(100.5, $endPos->x, 0.001);
		self::assertEqualsWithDelta(49.0, $endPos->y, 0.001);
		self::assertEqualsWithDelta(0.5, $endPos->z, 0.001);
	}

	public function testCreateNetherPortal() : void{
		$world = $this->createMockWorld("nether");
		$targetPos = new Vector3(10, 64, 20);

		$spawnPos = PortalTeleporter::createNetherPortal($world, $targetPos, Axis::X);

		self::assertEqualsWithDelta(10.5, $spawnPos->x, 0.001);
		self::assertEqualsWithDelta(65.0, $spawnPos->y, 0.001);
		self::assertEqualsWithDelta(20.5, $spawnPos->z, 0.001);

		// Frame check: 4x5 outer, inner 2x3 NetherPortal
		for($dx = 0; $dx <= 1; ++$dx){
			for($dy = 1; $dy <= 3; ++$dy){
				$b = $world->getBlockAt(10 + $dx, 64 + $dy, 20);
				self::assertInstanceOf(NetherPortal::class, $b);
				self::assertSame(Axis::X, $b->getAxis());
			}
		}

		// Obsidian borders
		self::assertSame(BlockTypeIds::OBSIDIAN, $world->getBlockAt(9, 65, 20)->getTypeId());
		self::assertSame(BlockTypeIds::OBSIDIAN, $world->getBlockAt(12, 65, 20)->getTypeId());
		self::assertSame(BlockTypeIds::OBSIDIAN, $world->getBlockAt(10, 64, 20)->getTypeId());
		self::assertSame(BlockTypeIds::OBSIDIAN, $world->getBlockAt(10, 68, 20)->getTypeId());

		// Air clearance
		self::assertSame(BlockTypeIds::AIR, $world->getBlockAt(10, 65, 19)->getTypeId());
		self::assertSame(BlockTypeIds::AIR, $world->getBlockAt(10, 65, 21)->getTypeId());
	}

	public function testFindNearestPortal() : void{
		$world = $this->createMockWorld("overworld");

		// No portals yet
		self::assertNull(PortalTeleporter::findNearestPortal($world, new Vector3(0, 64, 0)));

		// Create portal at (50, 64, 50)
		$p1 = PortalTeleporter::createNetherPortal($world, new Vector3(50, 64, 50));

		// Search from (40, 64, 40) - within 128 blocks
		$found = PortalTeleporter::findNearestPortal($world, new Vector3(40, 64, 40), 128);
		self::assertNotNull($found);
		self::assertEqualsWithDelta($p1->x, $found->x, 0.001);

		// Search from (500, 64, 500) - outside 128 radius
		$notFound = PortalTeleporter::findNearestPortal($world, new Vector3(500, 64, 500), 128);
		self::assertNull($notFound);
	}

	public function testCreateEndPlatform() : void{
		$world = $this->createMockWorld("the_end");
		$spawnPos = PortalTeleporter::createEndPlatform($world);

		self::assertEqualsWithDelta(100.5, $spawnPos->x, 0.001);
		self::assertEqualsWithDelta(49.0, $spawnPos->y, 0.001);
		self::assertEqualsWithDelta(0.5, $spawnPos->z, 0.001);

		// 5x5 obsidian platform at Y=48
		for($x = 98; $x <= 102; ++$x){
			for($z = -2; $z <= 2; ++$z){
				self::assertSame(BlockTypeIds::OBSIDIAN, $world->getBlockAt($x, 48, $z)->getTypeId());
				// Air above
				self::assertSame(BlockTypeIds::AIR, $world->getBlockAt($x, 49, $z)->getTypeId());
				self::assertSame(BlockTypeIds::AIR, $world->getBlockAt($x, 50, $z)->getTypeId());
				self::assertSame(BlockTypeIds::AIR, $world->getBlockAt($x, 51, $z)->getTypeId());
			}
		}
	}

	public function testPortalTimingAndCooldown() : void{
		$world = $this->createMockWorld();
		$survivalPlayer = $this->createMockPlayer($world, false);
		$creativePlayer = $this->createMockPlayer($world, true);

		self::assertSame(PortalTeleporter::SURVIVAL_PORTAL_TICKS, PortalTeleporter::getPortalWaitTicks($survivalPlayer));
		self::assertSame(PortalTeleporter::CREATIVE_PORTAL_TICKS, PortalTeleporter::getPortalWaitTicks($creativePlayer));

		// Increment wait ticks
		self::assertSame(0, PortalTeleporter::getPlayerWaitTicks($survivalPlayer));
		PortalTeleporter::handlePlayerInNetherPortal($survivalPlayer);
		self::assertSame(1, PortalTeleporter::getPlayerWaitTicks($survivalPlayer));
		PortalTeleporter::handlePlayerInNetherPortal($survivalPlayer);
		self::assertSame(2, PortalTeleporter::getPlayerWaitTicks($survivalPlayer));

		PortalTeleporter::resetPortalWait($survivalPlayer);
		self::assertSame(0, PortalTeleporter::getPlayerWaitTicks($survivalPlayer));

		// Cooldown check
		self::assertTrue(PortalTeleporter::canTeleport($survivalPlayer, 100));
		PortalTeleporter::recordTeleport($survivalPlayer, 100);

		// In cooldown: tick 100 + 100 = 200 < 100 + 300
		self::assertFalse(PortalTeleporter::canTeleport($survivalPlayer, 200));

		// Cooldown expired: tick 100 + 300 = 400
		self::assertTrue(PortalTeleporter::canTeleport($survivalPlayer, 400));
	}

	public function testTeleportExecution() : void{
		$world1 = $this->createMockWorld("overworld");
		$world2 = $this->createMockWorld("nether");
		$player = $this->createMockPlayer($world1, false);

		$teleported = PortalTeleporter::teleport($player, $world2, DimensionIds::NETHER, 500);
		self::assertTrue($teleported);

		// Cooldown was set
		self::assertFalse(PortalTeleporter::canTeleport($player, 550));
		self::assertTrue(PortalTeleporter::canTeleport($player, 801));
	}
}
