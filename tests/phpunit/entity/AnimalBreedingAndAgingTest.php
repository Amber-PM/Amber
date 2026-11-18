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
use pocketmine\inventory\PlayerInventory;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\World;
use ReflectionClass;
use ReflectionProperty;

final class AnimalBreedingAndAgingTest extends TestCase{

	private function setupLivingProperties(Living $entity) : void{
		(new ReflectionProperty(Entity::class, "closed"))->setValue($entity, true);
		(new ReflectionProperty(Entity::class, "size"))->setValue($entity, new EntitySizeInfo(1.3, 0.9));
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);
		(new ReflectionProperty(Entity::class, "location"))->setValue($entity, new Location(0.0, 10.0, 0.0, $world, 0.0, 0.0));
		(new ReflectionProperty(Entity::class, "motion"))->setValue($entity, Vector3::zero());
		$attrMapProp = new ReflectionProperty(Entity::class, "attributeMap");
		$attrMapProp->setValue($entity, new AttributeMap());
		$addAttributes = (new ReflectionClass($entity))->getMethod("addAttributes");
		$addAttributes->invoke($entity);
	}

	private function createLivingMock(string $class) : Living{
		/** @var Living $entity */
		$entity = (new ReflectionClass($class))->newInstanceWithoutConstructor();
		$this->setupLivingProperties($entity);
		return $entity;
	}

	public function testBabyAgingAndGrowth() : void{
		/** @var Cow $cow */
		$cow = $this->createLivingMock(Cow::class);
		self::assertFalse($cow->isBaby());

		$cow->setBaby(true);
		self::assertTrue($cow->isBaby());
		self::assertSame(-Animal::BABY_DURATION_TICKS, $cow->getAge());

		// Feeding baby with wheat speeds up growth
		$wheat = VanillaItems::WHEAT();
		$inventory = $this->createMock(PlayerInventory::class);
		$inventory->method("getItemInHand")->willReturn($wheat);

		$player = $this->getMockBuilder(Player::class)
			->disableOriginalConstructor()
			->onlyMethods(["getInventory", "hasFiniteResources"])
			->getMock();
		(new ReflectionProperty(Entity::class, "closed"))->setValue($player, true);
		(new ReflectionProperty(Player::class, "logger"))->setValue($player, $this->createMock(\Logger::class));
		$player->method("getInventory")->willReturn($inventory);
		$player->method("hasFiniteResources")->willReturn(true);

		$fed = $cow->onInteract($player, Vector3::zero());
		self::assertTrue($fed);
		self::assertSame(-Animal::BABY_DURATION_TICKS + 2400, $cow->getAge());

		// Reaching age 0 grows up
		$cow->setAge(0);
		self::assertFalse($cow->isBaby());
	}

	public function testAdultLoveModeAndBreeding() : void{
		$babyMock = $this->getMockBuilder(Cow::class)->disableOriginalConstructor()->onlyMethods(["spawnToAll", "setBaby"])->getMock();
		(new ReflectionProperty(Entity::class, "closed"))->setValue($babyMock, true);
		$babyMock->expects($this->once())->method("setBaby")->with(true);
		$babyMock->expects($this->once())->method("spawnToAll");

		/** @var Cow $cow */
		$cow = $this->getMockBuilder(Cow::class)->disableOriginalConstructor()->onlyMethods(["createBabyEntity"])->getMock();
		$this->setupLivingProperties($cow);
		$cow->method("createBabyEntity")->willReturn($babyMock);

		self::assertFalse($cow->isInLove());
		self::assertFalse($cow->canBreed());

		$wheat = VanillaItems::WHEAT();
		$inventory = $this->createMock(PlayerInventory::class);
		$inventory->method("getItemInHand")->willReturn($wheat);

		$player = $this->getMockBuilder(Player::class)
			->disableOriginalConstructor()
			->onlyMethods(["getInventory", "hasFiniteResources"])
			->getMock();
		(new ReflectionProperty(Entity::class, "closed"))->setValue($player, true);
		(new ReflectionProperty(Player::class, "logger"))->setValue($player, $this->createMock(\Logger::class));
		$player->method("getInventory")->willReturn($inventory);
		$player->method("hasFiniteResources")->willReturn(true);

		$success = $cow->onInteract($player, Vector3::zero());
		self::assertTrue($success);
		self::assertTrue($cow->isInLove());
		self::assertTrue($cow->canBreed());
		self::assertSame(Animal::IN_LOVE_TICKS, $cow->getInLoveTicks());

		// Feeding again while in love should fail
		$successAgain = $cow->onInteract($player, Vector3::zero());
		self::assertFalse($successAgain);

		// Partner
		/** @var Cow $partner */
		$partner = $this->createLivingMock(Cow::class);
		$partner->setInLove();

		$baby = $cow->breed($partner);
		self::assertSame($babyMock, $baby);

		// Both parents should now have breeding cooldown and not be in love
		self::assertFalse($cow->isInLove());
		self::assertFalse($partner->isInLove());
		self::assertSame(Animal::BREEDING_COOLDOWN_TICKS, $cow->getBreedingCooldown());
		self::assertSame(Animal::BREEDING_COOLDOWN_TICKS, $partner->getBreedingCooldown());
	}
}
