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

namespace pocketmine\world\spawner;

use PHPUnit\Framework\TestCase;
use pocketmine\block\tile\MonsterSpawner as TileMonsterSpawner;
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\Entity;
use pocketmine\entity\EntityFactory;
use pocketmine\entity\Living;
use pocketmine\entity\Location;
use pocketmine\entity\Zombie;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\Position;
use pocketmine\world\World;
use ReflectionClass;
use ReflectionProperty;

final class MobSpawnerTickerTest extends TestCase{

	private function createMockWorld() : World{
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);
		$world->method("getMinY")->willReturn(-64);
		$world->method("getMaxY")->willReturn(320);
		$world->method("isChunkLoaded")->willReturn(true);
		return $world;
	}

	private function createSpawnerTile(World $world, Vector3 $pos, string $entityId = "minecraft:zombie") : TileMonsterSpawner{
		$tile = (new ReflectionClass(TileMonsterSpawner::class))->newInstanceWithoutConstructor();
		$posProp = new ReflectionProperty(\pocketmine\block\tile\Tile::class, "position");
		$posProp->setValue($tile, Position::fromObject($pos, $world));

		$tile->setEntityId($entityId);
		$tile->setSpawnDelay(200);
		$tile->setMinSpawnDelay(200);
		$tile->setMaxSpawnDelay(800);
		$tile->setRequiredPlayerRange(16);
		$tile->setSpawnRange(4);
		$tile->setSpawnCount(4);
		$tile->setMaxNearbyEntities(6);

		return $tile;
	}

	public function testTileTrackingLifecycle() : void{
		$world = $this->createMockWorld();
		$ticker = new MobSpawnerTicker($world);
		self::assertSame(0, $ticker->getSpawnerCount());

		$tile = $this->createSpawnerTile($world, new Vector3(10, 64, 10));
		$ticker->onTileAdded($tile);
		self::assertSame(1, $ticker->getSpawnerCount());

		// Unload chunk 0, 0
		$ticker->onChunkUnloaded(0, 0);
		self::assertSame(0, $ticker->getSpawnerCount());

		$ticker->onTileAdded($tile);
		self::assertSame(1, $ticker->getSpawnerCount());

		$ticker->onTileRemoved($tile);
		self::assertSame(0, $ticker->getSpawnerCount());
	}

	public function testPlayerProximityActivation() : void{
		$world = $this->createMockWorld();
		$tile = $this->createSpawnerTile($world, new Vector3(0, 64, 0));

		// No players in world
		$world->method("getPlayers")->willReturn([]);
		self::assertFalse($tile->hasNearbyPlayer());

		// Player at distance 20 (outside 16 block range)
		$farPlayer = $this->createMock(Player::class);
		$farPlayer->method("isSpectator")->willReturn(false);
		$farPlayer->method("getPosition")->willReturn(Position::fromObject(new Vector3(20, 64, 0), $world));
		(new ReflectionProperty(Entity::class, "closed"))->setValue($farPlayer, true);
		(new ReflectionProperty(Player::class, "logger"))->setValue($farPlayer, $this->createMock(\Logger::class));

		$world2 = $this->createMockWorld();
		$posProp = new ReflectionProperty(\pocketmine\block\tile\Tile::class, "position");
		$posProp->setValue($tile, Position::fromObject(new Vector3(0, 64, 0), $world2));
		$world2->method("getPlayers")->willReturn([$farPlayer]);
		self::assertFalse($tile->hasNearbyPlayer());

		// Player at distance 10 (inside 16 block range)
		$nearPlayer = $this->createMock(Player::class);
		$nearPlayer->method("isSpectator")->willReturn(false);
		$nearPlayer->method("getPosition")->willReturn(Position::fromObject(new Vector3(10, 64, 0), $world2));
		(new ReflectionProperty(Entity::class, "closed"))->setValue($nearPlayer, true);
		(new ReflectionProperty(Player::class, "logger"))->setValue($nearPlayer, $this->createMock(\Logger::class));

		$world3 = $this->createMockWorld();
		$posProp->setValue($tile, Position::fromObject(new Vector3(0, 64, 0), $world3));
		$world3->method("getPlayers")->willReturn([$nearPlayer]);
		self::assertTrue($tile->hasNearbyPlayer());
	}

	public function testDelayCountdownAndReset() : void{
		$world = $this->createMockWorld();
		$tile = $this->createSpawnerTile($world, new Vector3(0, 64, 0));
		$tile->setSpawnDelay(2);

		self::assertFalse($tile->tickSpawnDelay(1));
		self::assertSame(1, $tile->getSpawnDelay());

		self::assertTrue($tile->tickSpawnDelay(1));
		self::assertSame(0, $tile->getSpawnDelay());

		$tile->resetSpawnDelay();
		self::assertGreaterThanOrEqual(200, $tile->getSpawnDelay());
		self::assertLessThanOrEqual(800, $tile->getSpawnDelay());
	}

	public function testNearbyEntityCapBlocksSpawning() : void{
		$world = $this->createMockWorld();
		$tile = $this->createSpawnerTile($world, new Vector3(0, 64, 0));
		$tile->setMaxNearbyEntities(6);

		// Mock 6 living entities within bounding box
		$mobs = [];
		for($i = 0; $i < 6; ++$i){
			$mob = $this->createMock(Living::class);
			$mob->method("isClosed")->willReturn(false);
			$mobs[] = $mob;
		}

		$world->method("getNearbyEntities")->willReturn($mobs);

		$ticker = new MobSpawnerTicker($world);
		$spawned = $ticker->attemptSpawn($tile);
		foreach($mobs as $mob){
			(new ReflectionProperty(Entity::class, "closed"))->setValue($mob, true);
		}
		self::assertSame(0, $spawned, "Should abort spawning when nearby entity cap is reached");
	}

	public function testLightLevelFilter() : void{
		$world = $this->createMockWorld();
		$tile = $this->createSpawnerTile($world, new Vector3(0, 64, 0));
		$world->method("getNearbyEntities")->willReturn([]);

		// Blocks are valid solid ground and air headroom
		$world->method("getBlockAt")->willReturnCallback(function(int $x, int $y, int $z){
			if($y === 63){
				return VanillaBlocks::STONE();
			}
			return VanillaBlocks::AIR();
		});

		// Block light level is 8 (> 7 limit for monster spawners)
		$world->method("getBlockLightAt")->willReturn(8);

		$ticker = new MobSpawnerTicker($world);
		$spawned = $ticker->attemptSpawn($tile);
		self::assertSame(0, $spawned, "Light level > 7 must inhibit spawner attempt");
	}

	public function testSuccessfulSpawnAttempt() : void{
		$world = $this->createMockWorld();
		$tile = $this->createSpawnerTile($world, new Vector3(0, 64, 0));
		$world->method("getNearbyEntities")->willReturn([]);

		$world->method("getBlockAt")->willReturnCallback(function(int $x, int $y, int $z){
			if($y === 63){
				return VanillaBlocks::STONE();
			}
			return VanillaBlocks::AIR();
		});

		// Dark dungeon light (light = 0)
		$world->method("getBlockLightAt")->willReturn(0);

		$ticker = new MobSpawnerTicker($world);
		$spawned = $ticker->attemptSpawn($tile);
		self::assertGreaterThanOrEqual(1, $spawned, "Valid conditions must spawn at least 1 mob");
	}
}
