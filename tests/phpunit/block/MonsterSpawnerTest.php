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
use ReflectionClass;
use ReflectionNamedType;

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
}
