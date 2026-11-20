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

final class MobDespawnEngineTest extends TestCase{

	private function createMockWorld() : World{
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);
		$world->method("getMinY")->willReturn(-64);
		$world->method("getMaxY")->willReturn(320);
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

	private function createLivingMock(World $world, Vector3 $pos, int $ticksLived = 0) : Living{
		$entity = (new ReflectionClass(Zombie::class))->newInstanceWithoutConstructor();
		(new ReflectionProperty(Entity::class, "closed"))->setValue($entity, false);
		(new ReflectionProperty(Entity::class, "id"))->setValue($entity, Entity::nextRuntimeId());
		(new ReflectionProperty(Entity::class, "location"))->setValue($entity, new Location($pos->x, $pos->y, $pos->z, $world, 0, 0));
		(new ReflectionProperty(Entity::class, "ticksLived"))->setValue($entity, $ticksLived);
		(new ReflectionProperty(Living::class, "armorInventory"))->setValue($entity, new \pocketmine\inventory\ArmorInventory($entity));
		return $entity;
	}

	public function testInstantDespawnBeyond128Blocks() : void{
		$world = $this->createMockWorld();
		$player = $this->createPlayerMock($world, new Vector3(0, 64, 0));
		$world->method("getPlayers")->willReturn([$player]);

		// Mob at distance 130 (> 128 blocks)
		$farMob = $this->createLivingMock($world, new Vector3(130, 64, 0), ticksLived: 100);
		$world->method("getEntities")->willReturn([$farMob]);

		$engine = new MobDespawnEngine($world);
		$despawned = $engine->tick(20);

		self::assertSame(1, $despawned);
		self::assertTrue($farMob->isFlaggedForDespawn(), "Mob beyond 128 blocks must immediately flag for despawn");

		(new ReflectionProperty(Entity::class, "closed"))->setValue($farMob, true);
	}

	public function testDespawnImmunityWithin32Blocks() : void{
		$world = $this->createMockWorld();
		$player = $this->createPlayerMock($world, new Vector3(0, 64, 0));
		$world->method("getPlayers")->willReturn([$player]);

		// Mob at distance 20 (<= 32 blocks)
		$nearMob = $this->createLivingMock($world, new Vector3(20, 64, 0), ticksLived: 1000);
		$world->method("getEntities")->willReturn([$nearMob]);

		$engine = new MobDespawnEngine($world);
		$despawned = $engine->tick(20);

		self::assertSame(0, $despawned);
		self::assertFalse($nearMob->isFlaggedForDespawn(), "Mob within 32 blocks must never despawn");

		(new ReflectionProperty(Entity::class, "closed"))->setValue($nearMob, true);
	}

	public function testPersistentMobImmunity() : void{
		$world = $this->createMockWorld();
		$player = $this->createPlayerMock($world, new Vector3(0, 64, 0));
		$world->method("getPlayers")->willReturn([$player]);

		// Far mob at distance 200 with persistent = true
		$persistentMob = $this->createLivingMock($world, new Vector3(200, 64, 0), ticksLived: 2000);
		$persistentMob->setPersistent(true);

		// Far mob with nametag
		$namedMob = $this->createLivingMock($world, new Vector3(250, 64, 0), ticksLived: 2000);
		$namedMob->setNameTag("Dinnerbone");

		$world->method("getEntities")->willReturn([$persistentMob, $namedMob]);

		$engine = new MobDespawnEngine($world);
		$despawned = $engine->tick(20);

		self::assertSame(0, $despawned);
		self::assertFalse($persistentMob->isFlaggedForDespawn());
		self::assertFalse($namedMob->isFlaggedForDespawn());

		(new ReflectionProperty(Entity::class, "closed"))->setValue($persistentMob, true);
		(new ReflectionProperty(Entity::class, "closed"))->setValue($namedMob, true);
	}

	public function testAgedDespawnBetween32And128Blocks() : void{
		$world = $this->createMockWorld();
		$player = $this->createPlayerMock($world, new Vector3(0, 64, 0));
		$world->method("getPlayers")->willReturn([$player]);

		// Young mob (ticksLived = 200 < 600 ticks / 30s) at distance 50 blocks
		$youngMob = $this->createLivingMock($world, new Vector3(50, 64, 0), ticksLived: 200);
		$world->method("getEntities")->willReturn([$youngMob]);

		$engine = new MobDespawnEngine($world);
		$despawned = $engine->tick(800); // 100% chance if age check passes

		self::assertSame(0, $despawned, "Young mob must not despawn even in 32-128 block zone");
		self::assertFalse($youngMob->isFlaggedForDespawn());

		// Old mob (ticksLived = 700 > 600 ticks) at distance 50 blocks
		$oldMob = $this->createLivingMock($world, new Vector3(50, 64, 0), ticksLived: 700);
		$world2 = $this->createMockWorld();
		$world2->method("getPlayers")->willReturn([$player]);
		$world2->method("getEntities")->willReturn([$oldMob]);

		$engine2 = new MobDespawnEngine($world2);
		$despawned2 = $engine2->tick(800); // 800 interval = 1/1 probability (100%)

		self::assertSame(1, $despawned2);
		self::assertTrue($oldMob->isFlaggedForDespawn(), "Old mob in 32-128 block zone must despawn when chance fires");

		(new ReflectionProperty(Entity::class, "closed"))->setValue($youngMob, true);
		(new ReflectionProperty(Entity::class, "closed"))->setValue($oldMob, true);
	}

	public function testDespawnWithNoPlayersInWorld() : void{
		$world = $this->createMockWorld();
		$world->method("getPlayers")->willReturn([]);

		$mob = $this->createLivingMock($world, new Vector3(0, 64, 0), ticksLived: 50);
		$world->method("getEntities")->willReturn([$mob]);

		$engine = new MobDespawnEngine($world);
		$despawned = $engine->tick(20);

		self::assertSame(1, $despawned);
		self::assertTrue($mob->isFlaggedForDespawn());

		(new ReflectionProperty(Entity::class, "closed"))->setValue($mob, true);
	}
}
