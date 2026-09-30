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

namespace pocketmine\block;

use PHPUnit\Framework\TestCase;
use pocketmine\block\tile\MonsterSpawner as TileMonsterSpawner;
use pocketmine\entity\Entity;
use pocketmine\item\ItemUseResult;
use pocketmine\item\SpawnEgg;
use pocketmine\item\VanillaItems;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\player\Player;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;

final class MonsterSpawnerTest extends TestCase{

	public function testDefaultProperties() : void{
		self::assertSame(200, TileMonsterSpawner::DEFAULT_MIN_SPAWN_DELAY);
		self::assertSame(800, TileMonsterSpawner::DEFAULT_MAX_SPAWN_DELAY);
		self::assertSame(6, TileMonsterSpawner::DEFAULT_MAX_NEARBY_ENTITIES);
		self::assertSame(16, TileMonsterSpawner::DEFAULT_REQUIRED_PLAYER_RANGE);
		self::assertSame(4, TileMonsterSpawner::DEFAULT_SPAWN_RANGE);
	}

	public function testTileMethodSignatures() : void{
		$refClass = new ReflectionClass(TileMonsterSpawner::class);

		$getEntityId = $refClass->getMethod("getEntityId");
		$ret = $getEntityId->getReturnType();
		self::assertInstanceOf(ReflectionNamedType::class, $ret);
		self::assertSame("string", $ret->getName());
		self::assertSame(0, $getEntityId->getNumberOfParameters());

		$setEntityId = $refClass->getMethod("setEntityId");
		$ret = $setEntityId->getReturnType();
		self::assertInstanceOf(ReflectionNamedType::class, $ret);
		self::assertSame("void", $ret->getName());
		self::assertSame(1, $setEntityId->getNumberOfParameters());
		$param = $setEntityId->getParameters()[0];
		self::assertSame("id", $param->getName());
		$paramType = $param->getType();
		self::assertInstanceOf(ReflectionNamedType::class, $paramType);
		self::assertSame("string", $paramType->getName());

		$onUpdate = $refClass->getMethod("onUpdate");
		$ret = $onUpdate->getReturnType();
		self::assertInstanceOf(ReflectionNamedType::class, $ret);
		self::assertSame("int", $ret->getName());
		self::assertSame(0, $onUpdate->getNumberOfParameters());

		$spawnMobs = $refClass->getMethod("spawnMobs");
		$ret = $spawnMobs->getReturnType();
		self::assertInstanceOf(ReflectionNamedType::class, $ret);
		self::assertSame("void", $ret->getName());
		self::assertSame(0, $spawnMobs->getNumberOfParameters());
	}

	public function testBlockMethodSignatures() : void{
		$refClass = new ReflectionClass(MonsterSpawner::class);

		$onPostPlace = $refClass->getMethod("onPostPlace");
		$ret = $onPostPlace->getReturnType();
		self::assertInstanceOf(ReflectionNamedType::class, $ret);
		self::assertSame("void", $ret->getName());
		self::assertSame(0, $onPostPlace->getNumberOfParameters());

		$onScheduledUpdate = $refClass->getMethod("onScheduledUpdate");
		$ret = $onScheduledUpdate->getReturnType();
		self::assertInstanceOf(ReflectionNamedType::class, $ret);
		self::assertSame("void", $ret->getName());
		self::assertSame(0, $onScheduledUpdate->getNumberOfParameters());

		$onInteract = $refClass->getMethod("onInteract");
		$ret = $onInteract->getReturnType();
		self::assertInstanceOf(ReflectionNamedType::class, $ret);
		self::assertSame("bool", $ret->getName());
		self::assertSame(5, $onInteract->getNumberOfParameters());
	}

	public function testSpawnEggMethodSignatures() : void{
		$refClass = new ReflectionClass(SpawnEgg::class);

		$getSpawnEntityNetworkId = $refClass->getMethod("getSpawnEntityNetworkId");
		$ret = $getSpawnEntityNetworkId->getReturnType();
		self::assertInstanceOf(ReflectionNamedType::class, $ret);
		self::assertSame("string", $ret->getName());
		self::assertSame(0, $getSpawnEntityNetworkId->getNumberOfParameters());
	}

	public function testSpawnEggNetworkIds() : void{
		self::assertSame(EntityIds::ZOMBIE, VanillaItems::ZOMBIE_SPAWN_EGG()->getSpawnEntityNetworkId());
		self::assertSame(EntityIds::SKELETON, VanillaItems::SKELETON_SPAWN_EGG()->getSpawnEntityNetworkId());
		self::assertSame(EntityIds::SPIDER, VanillaItems::SPIDER_SPAWN_EGG()->getSpawnEntityNetworkId());
		self::assertSame(EntityIds::SQUID, VanillaItems::SQUID_SPAWN_EGG()->getSpawnEntityNetworkId());
		self::assertSame(EntityIds::VILLAGER, VanillaItems::VILLAGER_SPAWN_EGG()->getSpawnEntityNetworkId());
	}

	public function testBlockOnInteractReturnsTrueForSpawnEgg() : void{
		$refClass = new ReflectionClass(MonsterSpawner::class);
		/** @var MonsterSpawner $block */
		$block = $refClass->newInstanceWithoutConstructor();

		$returnedItems = [];
		self::assertTrue($block->onInteract(VanillaItems::ZOMBIE_SPAWN_EGG(), Facing::UP, new Vector3(0, 0, 0), null, $returnedItems));
		self::assertTrue($block->onInteract(VanillaItems::SKELETON_SPAWN_EGG(), Facing::UP, new Vector3(0, 0, 0), null, $returnedItems));
		self::assertFalse($block->onInteract(VanillaItems::FEATHER(), Facing::UP, new Vector3(0, 0, 0), null, $returnedItems));
	}

	public function testTileClosedReturnsZero() : void{
		$refClass = new ReflectionClass(TileMonsterSpawner::class);
		/** @var TileMonsterSpawner $tile */
		$tile = $refClass->newInstanceWithoutConstructor();
		$tile->closed = true;

		self::assertSame(0, $tile->onUpdate());
	}

	public function testTileGetSetEntityId() : void{
		$refClass = new ReflectionClass(TileMonsterSpawner::class);
		/** @var TileMonsterSpawner $tile */
		$tile = $refClass->newInstanceWithoutConstructor();

		$tile->setEntityId("minecraft:skeleton");
		self::assertSame("minecraft:skeleton", $tile->getEntityId());

		$tile->setEntityId("minecraft:spider");
		self::assertSame("minecraft:spider", $tile->getEntityId());

		$tile->closed = true;
	}

	public function testTileSetEntityIdViaSpawnEggNetworkId() : void{
		$refClass = new ReflectionClass(TileMonsterSpawner::class);
		/** @var TileMonsterSpawner $tile */
		$tile = $refClass->newInstanceWithoutConstructor();

		$tile->setEntityId(VanillaItems::ZOMBIE_SPAWN_EGG()->getSpawnEntityNetworkId());
		self::assertSame(EntityIds::ZOMBIE, $tile->getEntityId());

		$tile->setEntityId(VanillaItems::SKELETON_SPAWN_EGG()->getSpawnEntityNetworkId());
		self::assertSame(EntityIds::SKELETON, $tile->getEntityId());

		$tile->setEntityId(VanillaItems::SPIDER_SPAWN_EGG()->getSpawnEntityNetworkId());
		self::assertSame(EntityIds::SPIDER, $tile->getEntityId());

		$tile->setEntityId(VanillaItems::SQUID_SPAWN_EGG()->getSpawnEntityNetworkId());
		self::assertSame(EntityIds::SQUID, $tile->getEntityId());

		$tile->setEntityId(VanillaItems::VILLAGER_SPAWN_EGG()->getSpawnEntityNetworkId());
		self::assertSame(EntityIds::VILLAGER, $tile->getEntityId());

		$tile->closed = true;
	}

	public function testTileSetEntityIdResetsSpawnDelay() : void{
		$refClass = new ReflectionClass(TileMonsterSpawner::class);
		/** @var TileMonsterSpawner $tile */
		$tile = $refClass->newInstanceWithoutConstructor();

		$tile->setMinSpawnDelay(300);
		$tile->setMaxSpawnDelay(500);
		$tile->setEntityId("minecraft:skeleton");

		self::assertGreaterThanOrEqual(300, $tile->getSpawnDelay());
		self::assertLessThanOrEqual(500, $tile->getSpawnDelay());

		$tile->closed = true;
	}

	public function testSpawnEggOnInteractBlockReturnsNoneOnSpawner() : void{
		$player = (new ReflectionClass(Player::class))->newInstanceWithoutConstructor();
		(new ReflectionProperty(Entity::class, "closed"))->setValue($player, true);
		(new ReflectionProperty(Player::class, "logger"))->setValue($player, $this->createMock(\Logger::class));
		$replaceBlock = (new ReflectionClass(Block::class))->newInstanceWithoutConstructor();
		$spawnerBlock = (new ReflectionClass(MonsterSpawner::class))->newInstanceWithoutConstructor();
		$returnedItems = [];

		$result = VanillaItems::ZOMBIE_SPAWN_EGG()->onInteractBlock($player, $replaceBlock, $spawnerBlock, Facing::UP, Vector3::zero(), $returnedItems);
		self::assertSame(ItemUseResult::NONE, $result);
	}
}
