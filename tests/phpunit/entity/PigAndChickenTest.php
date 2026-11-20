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
use pocketmine\item\Item;
use pocketmine\item\ItemIdentifier;
use pocketmine\item\ItemTypeIds;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\player\Player;
use pocketmine\world\World;
use ReflectionClass;
use ReflectionProperty;

final class PigAndChickenTest extends TestCase{

	private function createLivingMock(string $class) : Living{
		/** @var Living $entity */
		$entity = (new ReflectionClass($class))->newInstanceWithoutConstructor();
		(new ReflectionProperty(Entity::class, "closed"))->setValue($entity, true);
		(new ReflectionProperty(Entity::class, "size"))->setValue($entity, new EntitySizeInfo(0.9, 0.9));
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);
		(new ReflectionProperty(Entity::class, "location"))->setValue($entity, new Location(0.0, 10.0, 0.0, $world, 0.0, 0.0));
		(new ReflectionProperty(Entity::class, "motion"))->setValue($entity, Vector3::zero());
		$attrMapProp = new ReflectionProperty(Entity::class, "attributeMap");
		$attrMapProp->setValue($entity, new AttributeMap());
		$addAttributes = (new ReflectionClass($class))->getMethod("addAttributes");
		$addAttributes->invoke($entity);
		return $entity;
	}

	public function testPigPropertiesAndSaddling() : void{
		self::assertSame(EntityIds::PIG, Pig::getNetworkTypeId());

		/** @var Pig $pig */
		$pig = $this->createLivingMock(Pig::class);
		self::assertSame(10, $pig->getMaxHealth());
		self::assertFalse($pig->isSaddled());

		// Breeding items
		self::assertTrue($pig->isBreedingItem(VanillaItems::CARROT()));
		self::assertTrue($pig->isBreedingItem(VanillaItems::POTATO()));
		self::assertTrue($pig->isBreedingItem(VanillaItems::BEETROOT()));
		self::assertFalse($pig->isBreedingItem(VanillaItems::WHEAT()));

		// Saddling
		$saddleItem = new Item(new ItemIdentifier(ItemTypeIds::newId()), "Saddle");
		$inventory = $this->createMock(PlayerInventory::class);
		$inventory->method("getItemInHand")->willReturn($saddleItem);

		$player = $this->getMockBuilder(Player::class)
			->disableOriginalConstructor()
			->onlyMethods(["getInventory", "hasFiniteResources"])
			->getMock();
		(new ReflectionProperty(Entity::class, "closed"))->setValue($player, true);
		(new ReflectionProperty(Player::class, "logger"))->setValue($player, $this->createMock(\Logger::class));
		$player->method("getInventory")->willReturn($inventory);
		$player->method("hasFiniteResources")->willReturn(true);

		$success = $pig->onInteract($player, Vector3::zero());
		self::assertTrue($success);
		self::assertTrue($pig->isSaddled());

		$drops = $pig->getDrops();
		self::assertIsArray($drops);
		// With saddle, drops should include meat and saddle item
		self::assertCount(2, $drops);
	}

	public function testChickenPropertiesAndEggLaying() : void{
		self::assertSame(EntityIds::CHICKEN, Chicken::getNetworkTypeId());

		/** @var Chicken $chicken */
		$chicken = $this->createLivingMock(Chicken::class);
		self::assertSame(4, $chicken->getMaxHealth());
		self::assertSame(0.0, $chicken->calculateFallDamage(30.0));

		// Breeding items
		self::assertTrue($chicken->isBreedingItem(VanillaItems::WHEAT_SEEDS()));
		self::assertTrue($chicken->isBreedingItem(VanillaItems::BEETROOT_SEEDS()));
		self::assertTrue($chicken->isBreedingItem(VanillaItems::MELON_SEEDS()));
		self::assertTrue($chicken->isBreedingItem(VanillaItems::PUMPKIN_SEEDS()));
		self::assertFalse($chicken->isBreedingItem(VanillaItems::CARROT()));

		// Egg laying countdown
		$chicken->setEggLayTime(1);
		self::assertSame(1, $chicken->getEggLayTime());

		$drops = $chicken->getDrops();
		self::assertIsArray($drops);
		foreach($drops as $drop){
			self::assertInstanceOf(Item::class, $drop);
		}
	}
}
