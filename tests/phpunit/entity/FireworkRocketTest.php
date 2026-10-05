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

namespace pocketmine\entity\object;

use PHPUnit\Framework\TestCase;
use pocketmine\entity\Entity;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Living;
use pocketmine\entity\Location;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector3;
use pocketmine\world\World;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

class DummyFireworkTarget extends Living{
	public static function getNetworkTypeId() : string{ return "minecraft:dummy"; }
	protected function getInitialSizeInfo() : EntitySizeInfo{ return new EntitySizeInfo(1.0, 1.0); }
	public function getName() : string{ return "Dummy"; }
	public function attack(\pocketmine\event\entity\EntityDamageEvent $source) : void{}
}

final class FireworkRocketTest extends TestCase{

	private function createRocket(?World $world = null) : FireworkRocket{
		$rocket = (new ReflectionClass(FireworkRocket::class))->newInstanceWithoutConstructor();
		(new ReflectionProperty(Entity::class, "closed"))->setValue($rocket, false);
		(new ReflectionProperty(Entity::class, "needsDespawn"))->setValue($rocket, false);
		(new ReflectionProperty(Entity::class, "id"))->setValue($rocket, 150);
		(new ReflectionProperty(Entity::class, "size"))->setValue($rocket, new EntitySizeInfo(0.25, 0.25));
		(new ReflectionProperty(Entity::class, "motion"))->setValue($rocket, new Vector3(1.0, 0.0, 0.0));
		(new ReflectionProperty(Entity::class, "boundingBox"))->setValue($rocket, new AxisAlignedBB(0, 0, 0, 0.25, 0.25, 0.25));
		(new ReflectionProperty(Entity::class, "ticksLived"))->setValue($rocket, 10);
		(new ReflectionProperty(Entity::class, "isCollided"))->setValue($rocket, false);
		(new ReflectionProperty(Entity::class, "networkProperties"))->setValue($rocket, new \pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection());
		(new ReflectionProperty(Entity::class, "hasSpawned"))->setValue($rocket, []);
		(new ReflectionProperty(FireworkRocket::class, "maxFlightTimeTicks"))->setValue($rocket, 60);
		(new ReflectionProperty(FireworkRocket::class, "explosions"))->setValue($rocket, []);
		(new ReflectionProperty(FireworkRocket::class, "shotFromCrossbow"))->setValue($rocket, false);

		if($world === null){
			$world = $this->createMock(World::class);
			$world->method("isLoaded")->willReturn(true);
			$world->method("getCollidingEntities")->willReturn([]);
		}
		(new ReflectionProperty(Entity::class, "location"))->setValue($rocket, new Location(0.0, 0.0, 0.0, $world, 0.0, 0.0));
		return $rocket;
	}

	public function testCrossbowFlagAccessors() : void{
		$rocket = $this->createRocket();
		self::assertFalse($rocket->isShotFromCrossbow());

		$rocket->setShotFromCrossbow(true);
		self::assertTrue($rocket->isShotFromCrossbow());
	}

	public function testExplodesOnCollisionWhenShotFromCrossbow() : void{
		$rocket = $this->createRocket();
		$rocket->setShotFromCrossbow(true);
		(new ReflectionProperty(Entity::class, "isCollided"))->setValue($rocket, true);

		$baseTick = new ReflectionMethod(FireworkRocket::class, "entityBaseTick");
		$baseTick->invoke($rocket, 1);

		self::assertTrue($rocket->isFlaggedForDespawn());
		(new ReflectionProperty(Entity::class, "closed"))->setValue($rocket, true);
	}

	public function testExplodesOnLivingEntityCollisionWhenShotFromCrossbow() : void{
		$target = (new ReflectionClass(DummyFireworkTarget::class))->newInstanceWithoutConstructor();
		(new ReflectionProperty(Entity::class, "closed"))->setValue($target, true);
		(new ReflectionProperty(Entity::class, "id"))->setValue($target, 501);

		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);
		$world->method("getCollidingEntities")->willReturn([$target]);

		$rocket = $this->createRocket($world);
		$rocket->setShotFromCrossbow(true);

		$baseTick = new ReflectionMethod(FireworkRocket::class, "entityBaseTick");
		$baseTick->invoke($rocket, 1);

		self::assertTrue($rocket->isFlaggedForDespawn());
		(new ReflectionProperty(Entity::class, "closed"))->setValue($rocket, true);
	}
}
