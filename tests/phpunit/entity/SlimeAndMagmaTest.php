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
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\Item;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use ReflectionClass;
use ReflectionProperty;

final class SlimeAndMagmaTest extends TestCase{

	private function createLivingMock(string $class) : Living{
		/** @var Living $entity */
		$entity = (new ReflectionClass($class))->newInstanceWithoutConstructor();
		(new ReflectionProperty(Entity::class, "closed"))->setValue($entity, true);
		(new ReflectionProperty(Entity::class, "size"))->setValue($entity, new EntitySizeInfo(0.52, 0.52));
		$world = $this->createMock(\pocketmine\world\World::class);
		$world->method("isLoaded")->willReturn(true);
		(new ReflectionProperty(Entity::class, "location"))->setValue($entity, new Location(0.0, 0.0, 0.0, $world, 0.0, 0.0));
		(new ReflectionProperty(Entity::class, "motion"))->setValue($entity, \pocketmine\math\Vector3::zero());
		$attrMapProp = new ReflectionProperty(Entity::class, "attributeMap");
		$attrMapProp->setValue($entity, new AttributeMap());
		$addAttributes = (new ReflectionClass($class))->getMethod("addAttributes");
		$addAttributes->invoke($entity);
		return $entity;
	}

	public function testSlimeSizesAndDrops() : void{
		self::assertSame(EntityIds::SLIME, Slime::getNetworkTypeId());

		/** @var Slime $slime */
		$slime = $this->createLivingMock(Slime::class);

		// Size 1
		$slime->setSlimeSize(1);
		self::assertSame(1, $slime->getSlimeSize());
		self::assertSame(1, $slime->getMaxHealth());
		self::assertSame(1, $slime->getXpDropAmount());
		$drops1 = $slime->getDrops();
		self::assertIsArray($drops1);
		foreach($drops1 as $drop){
			self::assertInstanceOf(Item::class, $drop);
		}

		// Size 2
		$slime->setSlimeSize(2);
		self::assertSame(2, $slime->getSlimeSize());
		self::assertSame(4, $slime->getMaxHealth());
		self::assertSame(0, $slime->getXpDropAmount());
		self::assertSame([], $slime->getDrops());

		// Size 4
		$slime->setSlimeSize(4);
		self::assertSame(4, $slime->getSlimeSize());
		self::assertSame(16, $slime->getMaxHealth());
		self::assertSame(0, $slime->getXpDropAmount());
		self::assertSame([], $slime->getDrops());

		self::assertSame(0.0, $slime->calculateFallDamage(30.0));
	}

	public function testMagmaCubeProperties() : void{
		self::assertSame(EntityIds::MAGMA_CUBE, MagmaCube::getNetworkTypeId());

		/** @var MagmaCube $magma */
		$magma = $this->createLivingMock(MagmaCube::class);
		$magma->setSlimeSize(1);
		self::assertSame(1, $magma->getSlimeSize());
		self::assertSame(1, $magma->getMaxHealth());
		self::assertSame(1, $magma->getXpDropAmount());

		$drops = $magma->getDrops();
		self::assertIsArray($drops);
		foreach($drops as $drop){
			self::assertInstanceOf(Item::class, $drop);
		}

		$ev = new EntityDamageEvent($magma, EntityDamageEvent::CAUSE_FIRE, 10.0);
		$magma->attack($ev);
		self::assertTrue($ev->isCancelled());
	}
}
