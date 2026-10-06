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
use pocketmine\Server;
use pocketmine\world\format\Chunk;
use pocketmine\world\sound\Sound;
use pocketmine\world\World;
use pocketmine\world\WorldManager;

final class PortalTeleporterTest extends TestCase{

	protected function setUp() : void{
		parent::setUp();
		PortalTeleporter::clearRegisteredPortals();
		PortalTeleporter::setDestinationResolver(null);
	}

	protected function tearDown() : void{
		PortalTeleporter::setDestinationResolver(null);
		parent::tearDown();
	}

	private function createMockWorld(string $name = "world") : World{
		$blocks = [];
		$chunks = [];
		$world = $this->getMockBuilder(World::class)->disableOriginalConstructor()->onlyMethods([
			"getBlockAt",
			"setBlockAt",
			"setBlock",
			"isInWorld",
			"getDisplayName",
			"getFolderName",
			"loadChunk",
			"getMinY",
			"getMaxY",
			"addSound"
		])->getMock();

		$world->method("isInWorld")->willReturn(true);
		$world->method("getDisplayName")->willReturn($name);
		$world->method("getFolderName")->willReturn($name);
		$world->method("getMinY")->willReturn(-64);
		$world->method("getMaxY")->willReturn(320);
		$world->method("loadChunk")->willReturnCallback(static function(int $x, int $z) use (&$chunks) : ?Chunk{
			return $chunks["$x:$z"] ?? null;
		});

		$world->method("getBlockAt")->willReturnCallback(function(int $x, int $y, int $z) use ($world, &$blocks) : Block{
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

		$world->method("setBlockAt")->willReturnCallback(function(int $x, int $y, int $z, Block $block, bool $update = true) use ($world, &$chunks, &$blocks) : void{
			$key = "$x:$y:$z";
			$clone = clone $block;
			$clone->position($world, $x, $y, $z);
			$blocks[$key] = $clone;
			$chunk = $chunks[($x >> 4) . ":" . ($z >> 4)] ??= new Chunk([], true);
			$chunk->setBlockStateId($x & 15, $y, $z & 15, $block->getStateId());
		});

		$world->method("setBlock")->willReturnCallback(function(Vector3 $pos, Block $block, bool $update = true) use ($world) : void{
			$world->setBlockAt($pos->getFloorX(), $pos->getFloorY(), $pos->getFloorZ(), $block, $update);
		});

		$world->method("addSound")->willReturnCallback(function(Vector3 $pos, Sound $sound) : void{
			// NOOP
		});

		return $world;
	}

	private function createMockPlayer(World $world, bool $isCreative = false, ?Server $server = null) : Player{
		$player = $this->getMockBuilder(Player::class)
			->disableOriginalConstructor()
			->onlyMethods(["getWorld", "getPosition", "getServer", "isCreative", "teleport"])
			->getMock();
		$player->method("getWorld")->willReturn($world);
		$player->method("getPosition")->willReturn(new \pocketmine\world\Position(0, 65, 0, $world));
		$player->method("isCreative")->willReturn($isCreative);
		$player->method("teleport")->willReturn(true);
		$server ??= $this->getMockBuilder(Server::class)->disableOriginalConstructor()->onlyMethods(["getTick"])->getMock();
		$server->method("getTick")->willReturn(100);
		$player->method("getServer")->willReturn($server);

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
		PortalTeleporter::handlePlayerInNetherPortal($survivalPlayer, 101);
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

	public function testSurvivalPlayerTeleportsAfter80NetherPortalTicks() : void{
		$world1 = $this->createMockWorld("overworld");
		$world2 = $this->createMockWorld("nether");
		$player = $this->createMockPlayer($world1, false);

		PortalTeleporter::setDestinationResolver(function(Player $p, int $dim) use ($world2) : World{
			return $world2;
		});

		for($i = 1; $i < 80; ++$i){
			$result = PortalTeleporter::handlePlayerInNetherPortal($player, 100 + $i);
			self::assertFalse($result);
			self::assertSame($i, PortalTeleporter::getPlayerWaitTicks($player));
		}

		$result = PortalTeleporter::handlePlayerInNetherPortal($player, 180);
		self::assertTrue($result);
		self::assertSame(0, PortalTeleporter::getPlayerWaitTicks($player));
		self::assertFalse(PortalTeleporter::canTeleport($player, 200));

		PortalTeleporter::setDestinationResolver(null);
	}

	public function testCreativePlayerTeleportsAfter1NetherPortalTick() : void{
		$world1 = $this->createMockWorld("overworld");
		$world2 = $this->createMockWorld("nether");
		$player = $this->createMockPlayer($world1, true);

		PortalTeleporter::setDestinationResolver(function(Player $p, int $dim) use ($world2) : World{
			return $world2;
		});

		$result = PortalTeleporter::handlePlayerInNetherPortal($player, 100);
		self::assertTrue($result);
		self::assertSame(0, PortalTeleporter::getPlayerWaitTicks($player));

		PortalTeleporter::setDestinationResolver(null);
	}

	public function testEndPortalTeleportsImmediately() : void{
		$world1 = $this->createMockWorld("overworld");
		$world2 = $this->createMockWorld("the_end");
		$player = $this->createMockPlayer($world1, false);

		PortalTeleporter::setDestinationResolver(function(Player $p, int $dim) use ($world2) : World{
			return $world2;
		});

		$result = PortalTeleporter::handlePlayerInEndPortal($player, 100);
		self::assertTrue($result);
		self::assertFalse(PortalTeleporter::canTeleport($player, 200));

		PortalTeleporter::setDestinationResolver(null);
	}

	public function testExitResetClearsPortalWaitTicks() : void{
		$world = $this->createMockWorld("overworld");
		$player = $this->createMockPlayer($world, false);

		for($i = 0; $i < 40; ++$i){
			PortalTeleporter::handlePlayerInNetherPortal($player, 100 + $i);
		}
		self::assertSame(40, PortalTeleporter::getPlayerWaitTicks($player));

		// End of tick where player was in portal
		PortalTeleporter::onPlayerUpdate($player);
		self::assertSame(40, PortalTeleporter::getPlayerWaitTicks($player));

		// Next tick: player stepped out (no handlePlayerInNetherPortal called)
		PortalTeleporter::onPlayerUpdate($player);
		self::assertSame(0, PortalTeleporter::getPlayerWaitTicks($player));
	}

	public function testPortalCooldownPreventsPrematureTeleport() : void{
		$world1 = $this->createMockWorld("overworld");
		$world2 = $this->createMockWorld("nether");
		$player = $this->createMockPlayer($world1, true);

		PortalTeleporter::setDestinationResolver(function(Player $p, int $dim) use ($world2) : World{
			return $world2;
		});

		$result = PortalTeleporter::handlePlayerInNetherPortal($player, 100);
		self::assertTrue($result);

		// During cooldown, cannot teleport
		$result2 = PortalTeleporter::handlePlayerInNetherPortal($player, 200);
		self::assertFalse($result2);

		// After cooldown expires
		$result3 = PortalTeleporter::handlePlayerInNetherPortal($player, 401);
		self::assertTrue($result3);

		PortalTeleporter::setDestinationResolver(null);
	}

	public function testDestinationResolutionNetherToOverworld() : void{
		$netherWorld = $this->createMockWorld("nether");
		$player = $this->createMockPlayer($netherWorld, true);

		$resolvedDimension = null;
		PortalTeleporter::setDestinationResolver(function(Player $p, int $dim) use (&$resolvedDimension, $netherWorld) : World{
			$resolvedDimension = $dim;
			return $netherWorld;
		});

		PortalTeleporter::handlePlayerInNetherPortal($player, 100);
		self::assertSame(DimensionIds::OVERWORLD, $resolvedDimension);

		PortalTeleporter::setDestinationResolver(null);
	}

	public function testDestinationResolutionOverworldToNether() : void{
		$overworld = $this->createMockWorld("overworld");
		$player = $this->createMockPlayer($overworld, true);

		$resolvedDimension = null;
		PortalTeleporter::setDestinationResolver(function(Player $p, int $dim) use (&$resolvedDimension, $overworld) : World{
			$resolvedDimension = $dim;
			return $overworld;
		});

		PortalTeleporter::handlePlayerInNetherPortal($player, 100);
		self::assertSame(DimensionIds::NETHER, $resolvedDimension);

		PortalTeleporter::setDestinationResolver(null);
	}

	public function testRepeatedCallbacksInSameTickCountOnce() : void{
		$player = $this->createMockPlayer($this->createMockWorld());
		for($i = 0; $i < 4; ++$i){
			self::assertFalse(PortalTeleporter::handlePlayerInNetherPortal($player, 100));
		}
		self::assertSame(1, PortalTeleporter::getPlayerWaitTicks($player));
		PortalTeleporter::handlePlayerInNetherPortal($player, 101);
		self::assertSame(2, PortalTeleporter::getPlayerWaitTicks($player));
		PortalTeleporter::resetPortalWait($player);
		PortalTeleporter::handlePlayerInNetherPortal($player, 101);
		self::assertSame(1, PortalTeleporter::getPlayerWaitTicks($player));
	}

	public function testMissingDestinationDoesNotTeleportOrModifyOverworld() : void{
		$world = $this->createMockWorld();
		$world->expects(self::never())->method("setBlockAt");
		$manager = $this->getMockBuilder(WorldManager::class)->disableOriginalConstructor()->onlyMethods([
			"isWorldLoaded", "isWorldGenerated", "getDefaultWorld", "getWorlds"
		])->getMock();
		$manager->method("isWorldLoaded")->willReturn(false);
		$manager->method("isWorldGenerated")->willReturn(false);
		$manager->method("getDefaultWorld")->willReturn($world);
		$manager->method("getWorlds")->willReturn([$world]);
		$server = $this->getMockBuilder(Server::class)->disableOriginalConstructor()->onlyMethods(["getTick", "getWorldManager"])->getMock();
		$server->method("getWorldManager")->willReturn($manager);
		$player = $this->createMockPlayer($world, true, $server);
		$player->expects(self::never())->method("teleport");
		self::assertNull(PortalTeleporter::resolveDestinationWorld($player, DimensionIds::NETHER));
		self::assertNull(PortalTeleporter::resolveDestinationWorld($player, DimensionIds::THE_END));
		self::assertFalse(PortalTeleporter::handlePlayerInEndPortal($player, 100));
		self::assertFalse(PortalTeleporter::handlePlayerInNetherPortal($player, 101));
		self::assertTrue(PortalTeleporter::canTeleport($player, 102));
	}

	public function testUncachedIgnitedPortalWithinSearchRadiusIsFound() : void{
		$world = $this->createMockWorld("nether");
		NetherPortalDetector::activate($world, new NetherPortalDetection(Axis::X, 8, 9, 64, 66, 0));
		$found = PortalTeleporter::findNearestPortal($world, new Vector3(0, 64, 0));
		self::assertNotNull($found);
		self::assertSame(8.5, $found->x);
		self::assertSame(64, $found->y);
	}

	public function testPersistedHighPortalOverridesFartherCachedPortal() : void{
		$world = $this->createMockWorld();
		PortalTeleporter::createNetherPortal($world, new Vector3(50, 64, 0));
		NetherPortalDetector::activate($world, new NetherPortalDetection(Axis::X, -9, -8, 200, 202, 0));
		$found = PortalTeleporter::findNearestPortal($world, new Vector3(0, 202, 0));
		self::assertNotNull($found);
		self::assertSame(-7.5, $found->x);
		self::assertSame(200, $found->y);
		PortalTeleporter::clearRegisteredPortals();
		$reloaded = PortalTeleporter::findNearestPortal($world, new Vector3(0, 200, 0));
		self::assertEquals($found, $reloaded);
	}

	public function testOrdinaryWorldNamesDoNotChangeDimension() : void{
		foreach(["weekend", "friends", "shell"] as $name){
			self::assertSame(DimensionIds::OVERWORLD, PortalTeleporter::getDimensionId($this->createMockWorld($name)));
		}
		self::assertSame(DimensionIds::NETHER, PortalTeleporter::getDimensionId($this->createMockWorld("world_nether")));
		self::assertSame(DimensionIds::THE_END, PortalTeleporter::getDimensionId($this->createMockWorld("world_the_end")));
	}

	public function testMissingOverworldDoesNotUseDefaultNether() : void{
		$world = $this->createMockWorld("nether");
		$manager = $this->getMockBuilder(WorldManager::class)->disableOriginalConstructor()->onlyMethods(["getDefaultWorld", "getWorlds"])->getMock();
		$manager->method("getDefaultWorld")->willReturn($world);
		$manager->method("getWorlds")->willReturn([$world]);
		$server = $this->getMockBuilder(Server::class)->disableOriginalConstructor()->onlyMethods(["getTick", "getWorldManager"])->getMock();
		$server->method("getWorldManager")->willReturn($manager);
		$player = $this->createMockPlayer($world, true, $server);
		self::assertNull(PortalTeleporter::resolveDestinationWorld($player, DimensionIds::OVERWORLD));
	}
}
