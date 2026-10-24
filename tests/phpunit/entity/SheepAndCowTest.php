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
use pocketmine\block\BlockTypeIds;
use pocketmine\block\utils\DyeColor;
use pocketmine\block\VanillaBlocks;
use pocketmine\inventory\PlayerInventory;
use pocketmine\item\Dye;
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

final class SheepAndCowTest extends TestCase{

	private function createLivingMock(string $class) : Living{
		/** @var Living $entity */
		$entity = (new ReflectionClass($class))->newInstanceWithoutConstructor();
		(new ReflectionProperty(Entity::class, "closed"))->setValue($entity, true);
		(new ReflectionProperty(Entity::class, "size"))->setValue($entity, new EntitySizeInfo(1.3, 0.9));
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

	public function testSheepPropertiesAndColors() : void{
		self::assertSame(EntityIds::SHEEP, Sheep::getNetworkTypeId());

		/** @var Sheep $sheep */
		$sheep = $this->createLivingMock(Sheep::class);
		self::assertSame(8, $sheep->getMaxHealth());
		self::assertFalse($sheep->isSheared());

		$sheep->setColor(DyeColor::RED);
		self::assertSame(DyeColor::RED, $sheep->getColor());

		$sheep->setSheared(true);
		self::assertTrue($sheep->isSheared());

		// Test random color produces valid DyeColor
		for($i = 0; $i < 20; ++$i){
			$color = Sheep::getRandomColor();
			self::assertInstanceOf(DyeColor::class, $color);
		}
	}

	public function testSheepGrazingRegrowsWool() : void{
		/** @var Sheep $sheep */
		$sheep = $this->createLivingMock(Sheep::class);
		$sheep->setSheared(true);
		self::assertTrue($sheep->isSheared());

		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);
		$grassBlock = VanillaBlocks::GRASS();
		$world->method("getBlock")->willReturn($grassBlock);

		(new ReflectionProperty(Entity::class, "location"))->setValue($sheep, new Location(0.0, 10.0, 0.0, $world, 0.0, 0.0));

		$ate = $sheep->eatGrass();
		self::assertTrue($ate);
		self::assertFalse($sheep->isSheared());
	}

	public function testSheepShearingInteraction() : void{
		/** @var Sheep $sheep */
		$sheep = $this->createLivingMock(Sheep::class);
		$sheep->setColor(DyeColor::BLUE);

		$shears = VanillaItems::SHEARS();
		$inventory = $this->createMock(PlayerInventory::class);
		$inventory->method("getItemInHand")->willReturn($shears);

		$player = $this->getMockBuilder(Player::class)
			->disableOriginalConstructor()
			->onlyMethods(["getInventory", "hasFiniteResources"])
			->getMock();
		(new ReflectionProperty(Entity::class, "closed"))->setValue($player, true);
		(new ReflectionProperty(Player::class, "logger"))->setValue($player, $this->createMock(\Logger::class));
		$player->method("getInventory")->willReturn($inventory);
		$player->method("hasFiniteResources")->willReturn(true);

		$success = $sheep->onInteract($player, Vector3::zero());
		self::assertTrue($success);
		self::assertTrue($sheep->isSheared());

		// Shearing again should fail
		$successAgain = $sheep->onInteract($player, Vector3::zero());
		self::assertFalse($successAgain);
	}

	public function testCowPropertiesAndMilking() : void{
		self::assertSame(EntityIds::COW, Cow::getNetworkTypeId());

		/** @var Cow $cow */
		$cow = $this->createLivingMock(Cow::class);
		self::assertSame(10, $cow->getMaxHealth());

		$bucket = VanillaItems::BUCKET();
		$inventory = $this->createMock(PlayerInventory::class);
		$inventory->method("getItemInHand")->willReturn($bucket);

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

		$drops = $cow->getDrops();
		self::assertIsArray($drops);
		foreach($drops as $drop){
			self::assertInstanceOf(Item::class, $drop);
		}
	}
}
