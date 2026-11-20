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

use Logger;
use PHPUnit\Framework\TestCase;
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\Entity;
use pocketmine\entity\Living;
use pocketmine\entity\Location;
use pocketmine\entity\Zombie;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\Position;
use pocketmine\world\World;
use ReflectionClass;
use ReflectionProperty;

final class NaturalSpawnerTest extends TestCase{

	private function createMockWorld() : World{
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);
		$world->method("getMinY")->willReturn(-64);
		$world->method("getMaxY")->willReturn(320);
		$world->method("isChunkLoaded")->willReturn(true);
		$world->method("getDifficulty")->willReturn(World::DIFFICULTY_NORMAL);
		$world->method("getTime")->willReturn(18000); // Midnight
		return $world;
	}

	private function createPlayerMock(World $world, Vector3 $pos) : Player{
		$player = $this->createMock(Player::class);
		$player->method("isSpectator")->willReturn(false);
		$player->method("getPosition")->willReturn(Position::fromObject($pos, $world));
		(new ReflectionProperty(Entity::class, "closed"))->setValue($player, true);
		(new ReflectionProperty(Player::class, "logger"))->setValue($player, $this->createMock(Logger::class));
		return $player;
	}

	public function testPlayerExclusionDistance() : void{
		$world = $this->createMockWorld();
		$player = $this->createPlayerMock($world, new Vector3(0, 64, 0));

		$world->method("getBlockAt")->willReturnCallback(function(int $x, int $y, int $z){
			if($y === 63){
				return VanillaBlocks::STONE();
			}
			return VanillaBlocks::AIR();
		});

		$spawner = new NaturalSpawner($world);

		// Position at distance 15 (< 24 blocks)
		self::assertFalse($spawner->isValidSpawnLocation(15, 64, 0, [$player]), "Must reject coordinates closer than 24 blocks to player");

		// Position at distance 30 (>= 24 blocks)
		self::assertTrue($spawner->isValidSpawnLocation(30, 64, 0, [$player]), "Must accept coordinates 24+ blocks from player");
	}

	public function testPeacefulDifficultyBlocksMonsters() : void{
		$world = $this->createMockWorld();
		$player = $this->createPlayerMock($world, new Vector3(0, 64, 0));
		$world->method("getPlayers")->willReturn([$player]);
		$world->method("getDifficulty")->willReturn(World::DIFFICULTY_PEACEFUL);
		$world->method("getEntities")->willReturn([]);

		$spawner = new NaturalSpawner($world);
		$spawner->tick(20);

		// In peaceful mode, monsters must not spawn
		self::assertSame(0, $spawner->countEntities("minecraft:zombie", "minecraft:skeleton"));
	}

	public function testMonsterBlockLightZeroRequirement() : void{
		$world = $this->createMockWorld();
		$player = $this->createPlayerMock($world, new Vector3(0, 64, 0));
		$world->method("getPlayers")->willReturn([$player]);
		$world->method("getEntities")->willReturn([]);
		$world->method("getHighestBlockAt")->willReturn(63);

		$world->method("getBlockAt")->willReturnCallback(function(int $x, int $y, int $z){
			if($y === 63){
				return VanillaBlocks::STONE();
			}
			return VanillaBlocks::AIR();
		});

		// Torch light = 5 (blocks monsters in Bedrock 1.18+)
		$world->method("getBlockLightAt")->willReturn(5);
		$world->method("getRealBlockSkyLightAt")->willReturn(0);

		$spawner = new NaturalSpawner($world);
		$spawned = $spawner->spawnMonsters();
		self::assertSame(0, $spawned, "Monsters must not spawn with block light > 0");
	}

	public function testAnimalGrassBlockRequirement() : void{
		$world = $this->createMockWorld();
		$player = $this->createPlayerMock($world, new Vector3(0, 64, 0));
		$world->method("getPlayers")->willReturn([$player]);
		$world->method("getEntities")->willReturn([]);
		$world->method("getHighestBlockAt")->willReturn(63);

		// Ground is Sand (not Grass)
		$world->method("getBlockAt")->willReturnCallback(function(int $x, int $y, int $z){
			if($y === 63){
				return VanillaBlocks::SAND();
			}
			return VanillaBlocks::AIR();
		});
		$world->method("getRealBlockSkyLightAt")->willReturn(15);

		$spawner = new NaturalSpawner($world);
		$spawned = $spawner->spawnAnimals();
		self::assertSame(0, $spawned, "Animals must not spawn on non-grass blocks");
	}

	public function testMobCapEnforcement() : void{
		$world = $this->createMockWorld();
		$player = $this->createPlayerMock($world, new Vector3(0, 64, 0));
		$world->method("getPlayers")->willReturn([$player]);

		// Simulate 70 active monsters already in world
		$mobs = [];
		for($i = 0; $i < 70; ++$i){
			$mob = (new ReflectionClass(Zombie::class))->newInstanceWithoutConstructor();
			(new ReflectionProperty(Entity::class, "closed"))->setValue($mob, false);
			$mobs[] = $mob;
		}
		$world->method("getEntities")->willReturn($mobs);

		$spawner = new NaturalSpawner($world);
		$spawned = $spawner->spawnMonsters();
		self::assertSame(0, $spawned, "Spawning must halt when mob cap is reached");

		foreach($mobs as $mob){
			(new ReflectionProperty(Entity::class, "closed"))->setValue($mob, true);
		}
	}

	public function testCandidatePositionSelectionWithinBand() : void{
		$world = $this->createMockWorld();
		$player = $this->createPlayerMock($world, new Vector3(100, 64, 100));
		$world->method("getHighestBlockAt")->willReturn(63);
		$world->method("getBlockAt")->willReturnCallback(function(int $x, int $y, int $z){
			if($y === 63){
				return VanillaBlocks::STONE();
			}
			return VanillaBlocks::AIR();
		});

		$spawner = new NaturalSpawner($world);
		$cand = $spawner->findCandidatePosition($player);

		self::assertNotNull($cand);
		[$cx, $cy, $cz] = $cand;

		$dx = $cx - 100;
		$dz = $cz - 100;
		$dist = sqrt($dx * $dx + $dz * $dz);

		self::assertGreaterThanOrEqual(24.0, $dist, "Candidate distance must be >= 24 blocks");
		self::assertLessThanOrEqual(65.0, $dist, "Candidate distance must be within max radius");
	}
}
