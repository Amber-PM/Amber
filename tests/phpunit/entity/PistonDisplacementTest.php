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

namespace pocketmine\entity;

use PHPUnit\Framework\TestCase;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector3;
use pocketmine\world\World;

final class PistonCollisionEntity extends Entity{
	public static function getNetworkTypeId() : string{
		return "minecraft:test";
	}

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(1.8, 0.6);
	}

	protected function getInitialDragMultiplier() : float{
		return 0.02;
	}

	protected function getInitialGravity() : float{
		return 0.08;
	}

	public function initialize(World $world) : void{
		\pocketmine\timings\Timings::init();
		$this->closed = true;
		$this->id = 123;
		$this->size = $this->getInitialSizeInfo();
		$this->boundingBox = new AxisAlignedBB(0, 64, 0, 0.6, 65.8, 0.6);
		$this->location = new Location(0.3, 64, 0.3, $world, 0, 0);
		$this->lastLocation = clone $this->location;
		$this->motion = $this->lastMotion = new Vector3(0, 0, 0);
		$this->keepMovement = true;
	}

	protected function checkBlockIntersections() : void{}

	protected function updateMovement(bool $teleport = false) : void{
		if($teleport){
			throw new \LogicException("Piston movement must not be a teleport");
		}
	}
}

final class PistonDisplacementTest extends TestCase{
	public function testNativeCollisionStopsEntityAtWall() : void{
		$world = $this->createMock(World::class);
		$world->method("isInWorld")->willReturn(true);
		$world->method("isLoaded")->willReturn(true);
		$world->method("isChunkLoaded")->willReturn(true);
		$world->method("getBlockCollisionBoxes")->willReturn([new AxisAlignedBB(1, 63, -1, 2, 67, 2)]);
		$entity = (new \ReflectionClass(PistonCollisionEntity::class))->newInstanceWithoutConstructor();
		$entity->initialize($world);
		$entity->moveByPiston(new Vector3(1, 0, 0));
		self::assertEqualsWithDelta(1.0, $entity->getBoundingBox()->maxX, 0.000001);
		self::assertEqualsWithDelta(0.7, $entity->getPosition()->x, 0.000001);
	}

	public function testUnavailableChunkPreventsMovementWithoutCollisionReads() : void{
		$world = $this->createMock(World::class);
		$world->method("isInWorld")->willReturn(true);
		$world->method("isLoaded")->willReturn(true);
		$world->method("isChunkLoaded")->willReturn(false);
		$world->expects(self::never())->method("getBlockCollisionBoxes");
		$entity = (new \ReflectionClass(PistonCollisionEntity::class))->newInstanceWithoutConstructor();
		$entity->initialize($world);
		$entity->moveByPiston(new Vector3(1, 0, 0));
		self::assertSame(0.3, $entity->getPosition()->x);
	}
}
