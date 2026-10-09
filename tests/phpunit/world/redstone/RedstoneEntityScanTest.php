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

namespace pocketmine\world\redstone;

use PHPUnit\Framework\TestCase;
use pocketmine\entity\Entity;
use pocketmine\math\AxisAlignedBB;
use pocketmine\world\World;
use const PHP_INT_MAX;

final class RedstoneEntityScanTest extends TestCase{
	private function entity(World $world) : Entity{
		$entity = $this->createMock(Entity::class);
		(new \ReflectionProperty(Entity::class, "closed"))->setValue($entity, true);
		$entity->method("getWorld")->willReturn($world);
		$entity->method("getBoundingBox")->willReturn(AxisAlignedBB::one());
		return $entity;
	}

	public function testFullEventPopulationIsReturnedWithoutExceedingEachSlice() : void{
		$world = $this->createMock(World::class);
		$entities = [];
		for($i = 0; $i < 30; ++$i){ $entities[] = $this->entity($world); }
		$world->method("iterateEntityCandidates")->willReturnCallback(function() use ($entities) : \Generator{ yield from $entities; });
		$scan = new EntityScan($world, AxisAlignedBB::one(), 1);
		$result = null;
		for($i = 0; $i < 30 && $result === null; ++$i){
			$result = $scan->poll(3, PHP_INT_MAX, fn(Entity $entity) : bool => true);
			self::assertLessThanOrEqual(3, $scan->getWork());
		}
		self::assertSame($entities, $result);
	}

	public function testEntityAppearingInTwoChunkSnapshotsIsCountedOnce() : void{
		$world = $this->createMock(World::class);
		$entity = $this->entity($world);
		$world->method("iterateEntityCandidates")->willReturnCallback(function() use ($entity) : \Generator{ yield $entity; yield $entity; });
		$scan = new EntityScan($world, AxisAlignedBB::one(), 1);
		self::assertSame([$entity], $scan->poll(64, 15, fn(Entity $candidate) : bool => true));
	}

	public function testEntityLeavingDuringSuspendedScanIsDiscarded() : void{
		$world = $this->createMock(World::class);
		$inside = true;
		$entity = $this->entity($world);
		$world->method("iterateEntityCandidates")->willReturnCallback(function() use ($entity) : \Generator{ yield $entity; });
		$scan = new EntityScan($world, AxisAlignedBB::one(), 1);
		$filter = fn(Entity $candidate) : bool => $inside;
		self::assertNull($scan->poll(1, 1, $filter));
		$inside = false;
		self::assertSame([], $scan->poll(64, 1, fn(Entity $candidate) : bool => $inside));
	}
}
