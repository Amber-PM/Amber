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
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\effect\EffectManager;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\math\Vector3;
use pocketmine\world\World;
use function abs;

final class HoneyPhysicsTest extends TestCase{
	private function living(World $world, float $x = 0.5) : Living{
		$entity = $this->getMockBuilder(Living::class)->disableOriginalConstructor()->onlyMethods(["attack", "broadcastSound"])->getMockForAbstractClass();
		(new \ReflectionProperty(Entity::class, "closed"))->setValue($entity, true);
		(new \ReflectionProperty(Entity::class, "location"))->setValue($entity, new Location($x, 64.9375, 0.5, $world, 0, 0));
		(new \ReflectionProperty(Entity::class, "motion"))->setValue($entity, new Vector3(0, 0, 0));
		(new \ReflectionProperty(Living::class, "effectManager"))->setValue($entity, new EffectManager($entity));
		$entity->size = new EntitySizeInfo(1.8, 0.6);
		return $entity;
	}

	public function testJumpUsesRegisteredGroundBlockMultiplier() : void{
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);
		$ground = VanillaBlocks::HONEY_BLOCK();
		$world->method("getBlock")->willReturnCallback(function() use (&$ground){ return $ground; });
		$entity = $this->living($world);
		$entity->onGround = true;
		$entity->jump();
		self::assertEqualsWithDelta(0.21, $entity->getMotion()->y, 0.000001);
		$ground = VanillaBlocks::STONE();
		$entity->jump();
		self::assertEqualsWithDelta(0.42, $entity->getMotion()->y, 0.000001);
	}

	public function testLandingAppliesHoneyDamageReduction() : void{
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);
		$honey = VanillaBlocks::HONEY_BLOCK();
		$honey->position($world, 0, 64, 0);
		$world->method("getBlock")->willReturn($honey);
		$entity = $this->living($world);
		$entity->fallDistance = 10;
		$entity->expects(self::once())->method("attack")->with(self::callback(fn(EntityDamageEvent $event) => $event->getCause() === EntityDamageEvent::CAUSE_FALL && abs($event->getBaseDamage() - 1.4) < 0.000001));
		(new \ReflectionMethod(Living::class, "onHitGround"))->invoke($entity);
	}

	public function testSideSlideResetsFallDistanceAndGroundMovementKeepsVerticalMotion() : void{
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);
		$honey = VanillaBlocks::HONEY_BLOCK();
		$honey->position($world, 0, 64, 0);
		$entity = $this->living($world, -0.24);
		$entity->setMotion(new Vector3(0.4, -0.5, 0.2));
		$entity->fallDistance = 10;
		$honey->onEntityInside($entity);
		self::assertEqualsWithDelta(-0.05, $entity->getMotion()->y, 0.000001);
		self::assertEqualsWithDelta(0.04, $entity->getMotion()->x, 0.000001);
		self::assertSame(0.0, $entity->fallDistance);
		$entity->onGround = true;
		$entity->setMotion(new Vector3(0.4, 0.2, 0.1));
		$honey->onEntityInside($entity);
		self::assertEqualsWithDelta(0.16, $entity->getMotion()->x, 0.000001);
		self::assertSame(0.2, $entity->getMotion()->y);
	}
}
