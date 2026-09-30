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

namespace pocketmine\event\block;

use PHPUnit\Framework\TestCase;
use pocketmine\block\tile\MonsterSpawner as TileMonsterSpawner;
use pocketmine\entity\Entity;
use pocketmine\event\Cancellable;
use pocketmine\world\Position;
use pocketmine\YmlServerProperties;
use ReflectionClass;
use ReflectionNamedType;

final class SpawnerSpawnEventTest extends TestCase{

	public function testClassExistsAndImplementsCancellable() : void{
		self::assertTrue(is_subclass_of(SpawnerSpawnEvent::class, Cancellable::class));
		self::assertTrue(is_subclass_of(SpawnerSpawnEvent::class, BlockEvent::class));
	}

	public function testConstructorAndGetterSignatures() : void{
		$refClass = new ReflectionClass(SpawnerSpawnEvent::class);

		$constructor = $refClass->getConstructor();
		self::assertNotNull($constructor);
		$params = $constructor->getParameters();
		self::assertCount(3, $params);

		self::assertSame("spawner", $params[0]->getName());
		$type0 = $params[0]->getType();
		self::assertInstanceOf(ReflectionNamedType::class, $type0);
		self::assertSame(TileMonsterSpawner::class, $type0->getName());

		self::assertSame("entity", $params[1]->getName());
		$type1 = $params[1]->getType();
		self::assertInstanceOf(ReflectionNamedType::class, $type1);
		self::assertSame(Entity::class, $type1->getName());

		self::assertSame("spawnPosition", $params[2]->getName());
		$type2 = $params[2]->getType();
		self::assertInstanceOf(ReflectionNamedType::class, $type2);
		self::assertSame(Position::class, $type2->getName());

		$tileMethod = $refClass->getMethod("getSpawnerTile");
		$tileRet = $tileMethod->getReturnType();
		self::assertInstanceOf(ReflectionNamedType::class, $tileRet);
		self::assertSame(TileMonsterSpawner::class, $tileRet->getName());

		$entityMethod = $refClass->getMethod("getEntity");
		$entityRet = $entityMethod->getReturnType();
		self::assertInstanceOf(ReflectionNamedType::class, $entityRet);
		self::assertSame(Entity::class, $entityRet->getName());

		$posMethod = $refClass->getMethod("getSpawnPosition");
		$posRet = $posMethod->getReturnType();
		self::assertInstanceOf(ReflectionNamedType::class, $posRet);
		self::assertSame(Position::class, $posRet->getName());
	}

	public function testCancellationStateTransitions() : void{
		$refClass = new ReflectionClass(SpawnerSpawnEvent::class);
		/** @var SpawnerSpawnEvent $event */
		$event = $refClass->newInstanceWithoutConstructor();

		self::assertFalse($event->isCancelled());
		$event->cancel();
		self::assertTrue($event->isCancelled());
		$event->uncancel();
		self::assertFalse($event->isCancelled());
	}

	public function testConfigConstant() : void{
		self::assertSame("spawners.ignore-light-level", YmlServerProperties::SPAWNERS_IGNORE_LIGHT_LEVEL);
	}
}
