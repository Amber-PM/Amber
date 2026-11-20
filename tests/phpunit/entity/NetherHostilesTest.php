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

require_once __DIR__ . '/../fixtures/TestEncodingPolyfill.php';

use PHPUnit\Framework\TestCase;
use pocketmine\entity\projectile\LargeFireball;
use pocketmine\entity\projectile\SmallFireball;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\Item;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use ReflectionClass;
use ReflectionProperty;

final class NetherHostilesTest extends TestCase{

	private function createLivingMock(string $class) : Living{
		/** @var Living $entity */
		$entity = (new ReflectionClass($class))->newInstanceWithoutConstructor();
		(new ReflectionProperty(Entity::class, "closed"))->setValue($entity, true);
		$attrMapProp = new ReflectionProperty(Entity::class, "attributeMap");
		$attrMapProp->setValue($entity, new AttributeMap());
		$addAttributes = (new ReflectionClass($class))->getMethod("addAttributes");
		$addAttributes->invoke($entity);
		return $entity;
	}

	public function testBlazeProperties() : void{
		self::assertSame(EntityIds::BLAZE, Blaze::getNetworkTypeId());

		$blaze = $this->createLivingMock(Blaze::class);
		self::assertSame(20, $blaze->getMaxHealth());
		self::assertSame(0.0, $blaze->calculateFallDamage(20.0));
		self::assertSame(10, $blaze->getXpDropAmount());

		$drops = $blaze->getDrops();
		self::assertIsArray($drops);
		foreach($drops as $drop){
			self::assertInstanceOf(Item::class, $drop);
		}

		$ev = new EntityDamageEvent($blaze, EntityDamageEvent::CAUSE_FIRE, 5.0);
		$blaze->attack($ev);
		self::assertTrue($ev->isCancelled());

		$evLava = new EntityDamageEvent($blaze, EntityDamageEvent::CAUSE_LAVA, 10.0);
		$blaze->attack($evLava);
		self::assertTrue($evLava->isCancelled());
	}

	public function testGhastProperties() : void{
		self::assertSame(EntityIds::GHAST, Ghast::getNetworkTypeId());

		$ghast = $this->createLivingMock(Ghast::class);
		self::assertSame(10, $ghast->getMaxHealth());
		self::assertSame(0.0, $ghast->calculateFallDamage(50.0));
		self::assertSame(5, $ghast->getXpDropAmount());

		self::assertFalse($ghast->isCharging());
		$ghast->setCharging(true);
		self::assertTrue($ghast->isCharging());

		$drops = $ghast->getDrops();
		self::assertIsArray($drops);
		foreach($drops as $drop){
			self::assertInstanceOf(Item::class, $drop);
		}

		$ev = new EntityDamageEvent($ghast, EntityDamageEvent::CAUSE_FIRE, 5.0);
		$ghast->attack($ev);
		self::assertTrue($ev->isCancelled());
	}

	public function testFireballNetworkIds() : void{
		self::assertSame(EntityIds::SMALL_FIREBALL, SmallFireball::getNetworkTypeId());
		self::assertSame(EntityIds::FIREBALL, LargeFireball::getNetworkTypeId());
	}
}
