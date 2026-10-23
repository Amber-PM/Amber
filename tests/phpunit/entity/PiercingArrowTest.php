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

namespace pocketmine\entity\projectile;

use PHPUnit\Framework\TestCase;
use pocketmine\entity\Entity;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Living;
use pocketmine\entity\Location;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\RayTraceResult;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\world\World;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

class DummyPiercingTarget extends Living{
	public static function getNetworkTypeId() : string{ return "minecraft:dummy"; }
	protected function getInitialSizeInfo() : EntitySizeInfo{ return new EntitySizeInfo(1.0, 1.0); }
	public function getName() : string{ return "Dummy"; }
	public function attack(\pocketmine\event\entity\EntityDamageEvent $source) : void{}
}

final class PiercingArrowTest extends TestCase{

	private function createArrow(?World $world = null) : Arrow{
		$arrow = (new ReflectionClass(Arrow::class))->newInstanceWithoutConstructor();
		(new ReflectionProperty(Entity::class, "closed"))->setValue($arrow, true);
		(new ReflectionProperty(Entity::class, "id"))->setValue($arrow, 100);
		(new ReflectionProperty(Entity::class, "size"))->setValue($arrow, new EntitySizeInfo(0.25, 0.25));
		(new ReflectionProperty(Entity::class, "motion"))->setValue($arrow, new Vector3(0.0, 0.0, 1.0));
		(new ReflectionProperty(Entity::class, "boundingBox"))->setValue($arrow, new AxisAlignedBB(0, 0, 0, 0.25, 0.25, 0.25));
		(new ReflectionProperty(Arrow::class, "pickupMode"))->setValue($arrow, Arrow::PICKUP_ANY);
		(new ReflectionProperty(Arrow::class, "pierceLevel"))->setValue($arrow, 0);
		(new ReflectionProperty(Arrow::class, "piercedEntityIds"))->setValue($arrow, []);

		if($world === null){
			$world = $this->createMock(World::class);
			$world->method("isLoaded")->willReturn(true);
		}
		(new ReflectionProperty(Entity::class, "location"))->setValue($arrow, new Location(0.0, 0.0, 0.0, $world, 0.0, 0.0));
		return $arrow;
	}

	private function createMockLiving(int $id) : Living{
		$entity = (new ReflectionClass(DummyPiercingTarget::class))->newInstanceWithoutConstructor();
		(new ReflectionProperty(Entity::class, "closed"))->setValue($entity, true);
		(new ReflectionProperty(Entity::class, "id"))->setValue($entity, $id);
		(new ReflectionProperty(Entity::class, "boundingBox"))->setValue($entity, new AxisAlignedBB(0, 0, 0, 1, 2, 1));
		return $entity;
	}

	public function testDefaultPiercingProperties() : void{
		$arrow = $this->createArrow();
		self::assertSame(0, $arrow->getPierceLevel());
		self::assertSame(0, $arrow->getPiercedEntityCount());

		$arrow->setPierceLevel(3);
		self::assertSame(3, $arrow->getPierceLevel());
	}

	public function testNbtSerializationRoundtrip() : void{
		$arrow = $this->createArrow();
		$arrow->setPierceLevel(2);

		$nbt = $arrow->saveNBT();
		self::assertInstanceOf(ByteTag::class, $nbt->getTag("pierce"));
		self::assertSame(2, $nbt->getByte("pierce"));

		$restoredArrow = $this->createArrow();
		$initEntity = new ReflectionMethod(Arrow::class, "initEntity");
		$initEntity->invoke($restoredArrow, $nbt);

		self::assertSame(2, $restoredArrow->getPierceLevel());
	}

	public function testCollisionFilteringForPiercedEntities() : void{
		$arrow = $this->createArrow();
		$arrow->setPierceLevel(1);

		$mob1 = $this->createMockLiving(201);
		$mob2 = $this->createMockLiving(202);

		self::assertTrue($arrow->canCollideWith($mob1));
		self::assertTrue($arrow->canCollideWith($mob2));
		self::assertFalse($arrow->hasPiercedEntity($mob1));

		// Simulate piercing mob1
		$hitResult = new RayTraceResult(new AxisAlignedBB(0, 0, 0, 1, 1, 1), 0, new Vector3(0, 0, 0));
		$onHitEntity = new ReflectionMethod(Arrow::class, "onHitEntity");
		$onHitEntity->invoke($arrow, $mob1, $hitResult);

		self::assertTrue($arrow->hasPiercedEntity($mob1));
		self::assertFalse($arrow->hasPiercedEntity($mob2));
		self::assertSame(1, $arrow->getPiercedEntityCount());

		// mob1 should no longer be collidable, but mob2 still is
		self::assertFalse($arrow->canCollideWith($mob1));
		self::assertTrue($arrow->canCollideWith($mob2));
	}

	public function testDespawnBehaviorUnderPierceLimit() : void{
		$arrow = $this->createArrow();
		$arrow->setPierceLevel(2);

		$despawnsMethod = new ReflectionMethod(Arrow::class, "despawnsOnEntityHit");
		$piercedProp = new ReflectionProperty(Arrow::class, "piercedEntityIds");

		// 0 hits
		self::assertFalse($despawnsMethod->invoke($arrow));

		// 1 hit (1 pierced <= 2)
		$piercedProp->setValue($arrow, [301 => true]);
		self::assertFalse($despawnsMethod->invoke($arrow));

		// 2 hits (2 pierced <= 2)
		$piercedProp->setValue($arrow, [301 => true, 302 => true]);
		self::assertFalse($despawnsMethod->invoke($arrow));

		// 3 hits (3 pierced > 2) -> exceeds pierce limit, should despawn
		$piercedProp->setValue($arrow, [301 => true, 302 => true, 303 => true]);
		self::assertTrue($despawnsMethod->invoke($arrow));
	}

	public function testNormalArrowDespawnsOnEntityHit() : void{
		$arrow = $this->createArrow();
		$arrow->setPierceLevel(0);

		$despawnsMethod = new ReflectionMethod(Arrow::class, "despawnsOnEntityHit");
		self::assertTrue($despawnsMethod->invoke($arrow));
	}

	public function testPickupModeRetained() : void{
		$arrow = $this->createArrow();
		$arrow->setPierceLevel(2);
		$arrow->setPickupMode(Arrow::PICKUP_ANY);

		$mob = $this->createMockLiving(501);
		$hitResult = new RayTraceResult(new AxisAlignedBB(0, 0, 0, 1, 1, 1), 0, new Vector3(0, 0, 0));

		$onHitEntity = new ReflectionMethod(Arrow::class, "onHitEntity");
		$onHitEntity->invoke($arrow, $mob, $hitResult);

		self::assertSame(Arrow::PICKUP_ANY, $arrow->getPickupMode());
	}
}
