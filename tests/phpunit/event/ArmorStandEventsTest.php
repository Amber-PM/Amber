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

namespace pocketmine\event\entity;

use PHPUnit\Framework\TestCase;
use pocketmine\entity\object\ArmorStand;
use pocketmine\event\Cancellable;
use pocketmine\item\Item;
use pocketmine\player\Player;
use ReflectionClass;
use ReflectionNamedType;

final class ArmorStandEventsTest extends TestCase{

	public function testPoseChangeEventInheritanceAndCancellable() : void{
		self::assertTrue(is_subclass_of(ArmorStandPoseChangeEvent::class, EntityEvent::class));
		self::assertTrue(is_subclass_of(ArmorStandPoseChangeEvent::class, Cancellable::class));
	}

	public function testEquipEventInheritanceAndCancellable() : void{
		self::assertTrue(is_subclass_of(ArmorStandEquipEvent::class, EntityEvent::class));
		self::assertTrue(is_subclass_of(ArmorStandEquipEvent::class, Cancellable::class));
	}

	public function testEquipEventSlotConstants() : void{
		self::assertSame(0, ArmorStandEquipEvent::SLOT_HEAD);
		self::assertSame(1, ArmorStandEquipEvent::SLOT_CHEST);
		self::assertSame(2, ArmorStandEquipEvent::SLOT_LEGS);
		self::assertSame(3, ArmorStandEquipEvent::SLOT_FEET);
		self::assertSame(4, ArmorStandEquipEvent::SLOT_MAIN_HAND);
	}

	public function testPoseChangeEventReflectionSignatures() : void{
		$refClass = new ReflectionClass(ArmorStandPoseChangeEvent::class);

		$constructor = $refClass->getConstructor();
		self::assertNotNull($constructor);
		$params = $constructor->getParameters();
		self::assertCount(4, $params);

		self::assertSame("armorStand", $params[0]->getName());
		$type0 = $params[0]->getType();
		self::assertInstanceOf(ReflectionNamedType::class, $type0);
		self::assertSame(ArmorStand::class, $type0->getName());
		self::assertFalse($type0->allowsNull());

		self::assertSame("player", $params[1]->getName());
		$type1 = $params[1]->getType();
		self::assertInstanceOf(ReflectionNamedType::class, $type1);
		self::assertSame(Player::class, $type1->getName());
		self::assertTrue($type1->allowsNull());

		self::assertSame("oldPose", $params[2]->getName());
		$type2 = $params[2]->getType();
		self::assertInstanceOf(ReflectionNamedType::class, $type2);
		self::assertSame("int", $type2->getName());
		self::assertFalse($type2->allowsNull());

		self::assertSame("newPose", $params[3]->getName());
		$type3 = $params[3]->getType();
		self::assertInstanceOf(ReflectionNamedType::class, $type3);
		self::assertSame("int", $type3->getName());
		self::assertFalse($type3->allowsNull());

		$armorStandMethod = $refClass->getMethod("getArmorStand");
		$armorStandRet = $armorStandMethod->getReturnType();
		self::assertInstanceOf(ReflectionNamedType::class, $armorStandRet);
		self::assertSame(ArmorStand::class, $armorStandRet->getName());

		$playerMethod = $refClass->getMethod("getPlayer");
		$playerRet = $playerMethod->getReturnType();
		self::assertInstanceOf(ReflectionNamedType::class, $playerRet);
		self::assertSame(Player::class, $playerRet->getName());
		self::assertTrue($playerRet->allowsNull());

		$oldPoseMethod = $refClass->getMethod("getOldPose");
		$oldPoseRet = $oldPoseMethod->getReturnType();
		self::assertInstanceOf(ReflectionNamedType::class, $oldPoseRet);
		self::assertSame("int", $oldPoseRet->getName());

		$newPoseMethod = $refClass->getMethod("getNewPose");
		$newPoseRet = $newPoseMethod->getReturnType();
		self::assertInstanceOf(ReflectionNamedType::class, $newPoseRet);
		self::assertSame("int", $newPoseRet->getName());

		$setNewPoseMethod = $refClass->getMethod("setNewPose");
		self::assertSame(1, $setNewPoseMethod->getNumberOfParameters());
		$setNewPoseParam = $setNewPoseMethod->getParameters()[0];
		self::assertSame("newPose", $setNewPoseParam->getName());
		$setNewPoseParamType = $setNewPoseParam->getType();
		self::assertInstanceOf(ReflectionNamedType::class, $setNewPoseParamType);
		self::assertSame("int", $setNewPoseParamType->getName());
		$setNewPoseRet = $setNewPoseMethod->getReturnType();
		self::assertInstanceOf(ReflectionNamedType::class, $setNewPoseRet);
		self::assertSame("void", $setNewPoseRet->getName());
	}

	public function testEquipEventReflectionSignatures() : void{
		$refClass = new ReflectionClass(ArmorStandEquipEvent::class);

		$constructor = $refClass->getConstructor();
		self::assertNotNull($constructor);
		$params = $constructor->getParameters();
		self::assertCount(5, $params);

		self::assertSame("armorStand", $params[0]->getName());
		$type0 = $params[0]->getType();
		self::assertInstanceOf(ReflectionNamedType::class, $type0);
		self::assertSame(ArmorStand::class, $type0->getName());
		self::assertFalse($type0->allowsNull());

		self::assertSame("player", $params[1]->getName());
		$type1 = $params[1]->getType();
		self::assertInstanceOf(ReflectionNamedType::class, $type1);
		self::assertSame(Player::class, $type1->getName());
		self::assertFalse($type1->allowsNull());

		self::assertSame("slot", $params[2]->getName());
		$type2 = $params[2]->getType();
		self::assertInstanceOf(ReflectionNamedType::class, $type2);
		self::assertSame("int", $type2->getName());
		self::assertFalse($type2->allowsNull());

		self::assertSame("oldItem", $params[3]->getName());
		$type3 = $params[3]->getType();
		self::assertInstanceOf(ReflectionNamedType::class, $type3);
		self::assertSame(Item::class, $type3->getName());
		self::assertFalse($type3->allowsNull());

		self::assertSame("newItem", $params[4]->getName());
		$type4 = $params[4]->getType();
		self::assertInstanceOf(ReflectionNamedType::class, $type4);
		self::assertSame(Item::class, $type4->getName());
		self::assertFalse($type4->allowsNull());

		$armorStandMethod = $refClass->getMethod("getArmorStand");
		$armorStandRet = $armorStandMethod->getReturnType();
		self::assertInstanceOf(ReflectionNamedType::class, $armorStandRet);
		self::assertSame(ArmorStand::class, $armorStandRet->getName());

		$playerMethod = $refClass->getMethod("getPlayer");
		$playerRet = $playerMethod->getReturnType();
		self::assertInstanceOf(ReflectionNamedType::class, $playerRet);
		self::assertSame(Player::class, $playerRet->getName());
		self::assertFalse($playerRet->allowsNull());

		$slotMethod = $refClass->getMethod("getSlot");
		$slotRet = $slotMethod->getReturnType();
		self::assertInstanceOf(ReflectionNamedType::class, $slotRet);
		self::assertSame("int", $slotRet->getName());

		$oldItemMethod = $refClass->getMethod("getOldItem");
		$oldItemRet = $oldItemMethod->getReturnType();
		self::assertInstanceOf(ReflectionNamedType::class, $oldItemRet);
		self::assertSame(Item::class, $oldItemRet->getName());

		$newItemMethod = $refClass->getMethod("getNewItem");
		$newItemRet = $newItemMethod->getReturnType();
		self::assertInstanceOf(ReflectionNamedType::class, $newItemRet);
		self::assertSame(Item::class, $newItemRet->getName());

		$setNewItemMethod = $refClass->getMethod("setNewItem");
		self::assertSame(1, $setNewItemMethod->getNumberOfParameters());
		$setNewItemParam = $setNewItemMethod->getParameters()[0];
		self::assertSame("newItem", $setNewItemParam->getName());
		$setNewItemParamType = $setNewItemParam->getType();
		self::assertInstanceOf(ReflectionNamedType::class, $setNewItemParamType);
		self::assertSame(Item::class, $setNewItemParamType->getName());
		$setNewItemRet = $setNewItemMethod->getReturnType();
		self::assertInstanceOf(ReflectionNamedType::class, $setNewItemRet);
		self::assertSame("void", $setNewItemRet->getName());
	}

	public function testPoseChangeEventCancellationStateTransitions() : void{
		$refClass = new ReflectionClass(ArmorStandPoseChangeEvent::class);
		/** @var ArmorStandPoseChangeEvent $event */
		$event = $refClass->newInstanceWithoutConstructor();

		self::assertFalse($event->isCancelled());
		$event->cancel();
		self::assertTrue($event->isCancelled());
		$event->uncancel();
		self::assertFalse($event->isCancelled());
	}

	public function testEquipEventCancellationStateTransitions() : void{
		$refClass = new ReflectionClass(ArmorStandEquipEvent::class);
		/** @var ArmorStandEquipEvent $event */
		$event = $refClass->newInstanceWithoutConstructor();

		self::assertFalse($event->isCancelled());
		$event->cancel();
		self::assertTrue($event->isCancelled());
		$event->uncancel();
		self::assertFalse($event->isCancelled());
	}
}
