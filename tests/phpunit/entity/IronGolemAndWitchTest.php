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
use pocketmine\entity\effect\EffectManager;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\inventory\PlayerInventory;
use pocketmine\item\Item;
use pocketmine\item\ItemIdentifier;
use pocketmine\item\ItemTypeIds;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\player\Player;
use ReflectionClass;
use ReflectionProperty;

final class IronGolemAndWitchTest extends TestCase{

	private function createLivingMock(string $class) : Living{
		/** @var Living $entity */
		$entity = (new ReflectionClass($class))->newInstanceWithoutConstructor();
		(new ReflectionProperty(Entity::class, "closed"))->setValue($entity, true);
		$attrMapProp = new ReflectionProperty(Entity::class, "attributeMap");
		$attrMapProp->setValue($entity, new AttributeMap());
		$addAttributes = (new ReflectionClass($class))->getMethod("addAttributes");
		$addAttributes->invoke($entity);
		(new ReflectionProperty(Living::class, "effectManager"))->setValue($entity, new EffectManager($entity));
		return $entity;
	}

	public function testIronGolemProperties() : void{
		self::assertSame(EntityIds::IRON_GOLEM, IronGolem::getNetworkTypeId());

		/** @var IronGolem $golem */
		$golem = $this->createLivingMock(IronGolem::class);
		self::assertSame(100, $golem->getMaxHealth());
		self::assertSame(0.0, $golem->calculateFallDamage(40.0));
		self::assertSame(0, $golem->getXpDropAmount());

		$drops = $golem->getDrops();
		self::assertIsArray($drops);
		foreach($drops as $drop){
			self::assertInstanceOf(Item::class, $drop);
		}
	}

	public function testIronGolemRepairInteraction() : void{
		/** @var IronGolem $golem */
		$golem = $this->createLivingMock(IronGolem::class);
		$golem->setHealth(50.0);

		$inventory = $this->createMock(PlayerInventory::class);
		$ingot = (new Item(new ItemIdentifier(ItemTypeIds::IRON_INGOT)))->setCount(5);
		$inventory->method("getItemInHand")->willReturn($ingot);

		$player = $this->getMockBuilder(Player::class)
			->disableOriginalConstructor()
			->onlyMethods(["getInventory", "hasFiniteResources"])
			->getMock();
		(new ReflectionProperty(Entity::class, "closed"))->setValue($player, true);
		(new ReflectionProperty(Player::class, "logger"))->setValue($player, $this->createMock(\Logger::class));

		$player->method("getInventory")->willReturn($inventory);
		$player->method("hasFiniteResources")->willReturn(true);

		$success = $golem->onInteract($player, Vector3::zero());
		self::assertTrue($success);
		self::assertSame(75.0, $golem->getHealth());

		// Full health repair should fail
		$golem->setHealth(100.0);
		$successFull = $golem->onInteract($player, Vector3::zero());
		self::assertFalse($successFull);
	}

	public function testWitchProperties() : void{
		self::assertSame(EntityIds::WITCH, Witch::getNetworkTypeId());

		/** @var Witch $witch */
		$witch = $this->createLivingMock(Witch::class);
		self::assertSame(26, $witch->getMaxHealth());
		self::assertSame(5, $witch->getXpDropAmount());

		$drops = $witch->getDrops();
		self::assertIsArray($drops);
		foreach($drops as $drop){
			self::assertInstanceOf(Item::class, $drop);
		}
	}
}
