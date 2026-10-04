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

namespace pocketmine\tests\entity;

use PHPUnit\Framework\TestCase;
use pocketmine\entity\Attribute;
use pocketmine\entity\AttributeMap;
use pocketmine\entity\Entity;
use pocketmine\entity\Location;
use pocketmine\entity\object\Boat;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\Boat as BoatItem;
use pocketmine\item\BoatType;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\player\Player;
use pocketmine\world\World;
use ReflectionClass;
use ReflectionProperty;

class BoatEntityTest extends TestCase{

	private function createTestBoat(BoatType $type = BoatType::OAK, ?World $world = null) : Boat{
		if($world === null){
			$world = $this->createMock(World::class);
			$world->method("isLoaded")->willReturn(true);
		}

		$boat = (new ReflectionClass(Boat::class))->newInstanceWithoutConstructor();

		(new ReflectionProperty(Entity::class, "attributeMap"))->setValue($boat, new AttributeMap());
		(new ReflectionProperty(Entity::class, "networkProperties"))->setValue($boat, new EntityMetadataCollection());
		(new ReflectionProperty(Boat::class, "boatType"))->setValue($boat, $type);

		$size = (new ReflectionClass(Boat::class))->getMethod("getInitialSizeInfo")->invoke($boat);
		(new ReflectionProperty(Entity::class, "size"))->setValue($boat, $size);

		$location = new Location(0.0, 10.0, 0.0, $world, 0.0, 0.0);
		(new ReflectionProperty(Entity::class, "location"))->setValue($boat, $location);
		(new ReflectionProperty(Entity::class, "boundingBox"))->setValue($boat, new AxisAlignedBB(-0.7, 10.0, -0.7, 0.7, 10.6, 0.7));
		(new ReflectionProperty(Entity::class, "motion"))->setValue($boat, Vector3::zero());

		(new ReflectionClass(Boat::class))->getMethod("initBoatProperties")->invoke($boat);

		return $boat;
	}

	public function testDimensions() : void{
		$boat = $this->createTestBoat(BoatType::OAK);
		self::assertEqualsWithDelta(0.6, $boat->getSize()->getHeight(), 0.001);
		self::assertEqualsWithDelta(1.4, $boat->getSize()->getWidth(), 0.001);

		$raft = $this->createTestBoat(BoatType::BAMBOO);
		self::assertEqualsWithDelta(0.45, $raft->getSize()->getHeight(), 0.001);
		self::assertEqualsWithDelta(1.4, $raft->getSize()->getWidth(), 0.001);
	}

	public function testMetadataVariant() : void{
		$boat = $this->createTestBoat(BoatType::MANGROVE);
		$properties = $boat->getNetworkProperties();
		$prop = $properties->getAll()[EntityMetadataProperties::VARIANT];
		self::assertInstanceOf(\pocketmine\network\mcpe\protocol\types\entity\IntMetadataProperty::class, $prop);
		self::assertSame(BoatType::MANGROVE->getVariantId(), $prop->getValue());
	}

	public function testHealthAndSurvivalDamage() : void{
		$boat = $this->createTestBoat(BoatType::SPRUCE);
		self::assertEqualsWithDelta(40.0, $boat->getHealth(), 0.001);
		self::assertEqualsWithDelta(40.0, $boat->getMaxHealth(), 0.001);

		$damageEvent = new EntityDamageEvent($boat, EntityDamageEvent::CAUSE_ENTITY_ATTACK, 10.0);
		$boat->attack($damageEvent);

		self::assertEqualsWithDelta(30.0, $boat->getHealth(), 0.001);
		self::assertFalse($boat->isClosed());
	}

	public function testDestructionDropsItem() : void{
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);
		$droppedItem = null;
		$world->method("dropItem")->willReturnCallback(function(Vector3 $pos, \pocketmine\item\Item $item) use (&$droppedItem){
			$droppedItem = $item;
			return null;
		});

		$boat = $this->createTestBoat(BoatType::DARK_OAK, $world);

		$fatalDamage = new EntityDamageEvent($boat, EntityDamageEvent::CAUSE_ENTITY_ATTACK, 45.0);
		$boat->attack($fatalDamage);

		self::assertTrue($boat->isClosed());
		self::assertInstanceOf(BoatItem::class, $droppedItem);
		self::assertSame(BoatType::DARK_OAK, $droppedItem->getType());
	}

	public function testCreativeAttackBreaksWithoutDrop() : void{
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);
		$dropCalled = false;
		$world->method("dropItem")->willReturnCallback(function() use (&$dropCalled){
			$dropCalled = true;
			return null;
		});

		$player = $this->createMock(Player::class);
		$player->method("isCreative")->willReturn(true);
		(new ReflectionProperty(Entity::class, "closed"))->setValue($player, true);
		(new ReflectionProperty(Player::class, "logger"))->setValue($player, $this->createMock(\Logger::class));

		$boat = $this->createTestBoat(BoatType::BIRCH, $world);

		$creativeDamage = $this->createMock(EntityDamageByEntityEvent::class);
		$creativeDamage->method("getDamager")->willReturn($player);
		$creativeDamage->method("getFinalDamage")->willReturn(1.0);
		$boat->attack($creativeDamage);

		self::assertTrue($boat->isClosed());
		self::assertFalse($dropCalled);
	}

	public function testNbtPersistence() : void{
		$boat = $this->createTestBoat(BoatType::CHERRY);
		$boat->setHealth(25.0);

		$nbt = CompoundTag::create();
		(new ReflectionClass(Boat::class))->getMethod("writeSaveData")->invoke($boat, $nbt);

		self::assertSame("cherry", $nbt->getString("Type"));
		self::assertEqualsWithDelta(25.0, $nbt->getFloat("Health"), 0.001);

		$newBoat = $this->createTestBoat(BoatType::OAK);
		(new ReflectionClass(Boat::class))->getMethod("readSaveData")->invoke($newBoat, $nbt);

		self::assertSame(BoatType::CHERRY, $newBoat->getBoatType());
		self::assertEqualsWithDelta(25.0, $newBoat->getHealth(), 0.001);
	}
}
