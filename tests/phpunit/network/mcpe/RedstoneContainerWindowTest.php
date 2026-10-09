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

namespace pocketmine\network\mcpe;

use PHPUnit\Framework\TestCase;
use pocketmine\block\inventory\DispenserInventory;
use pocketmine\block\inventory\DropperInventory;
use pocketmine\network\mcpe\protocol\ContainerOpenPacket;
use pocketmine\network\mcpe\protocol\types\inventory\WindowTypes;
use pocketmine\world\Position;

final class RedstoneContainerWindowTest extends TestCase{
	public function testNativeWindowTypes() : void{
		$open = new \ReflectionMethod(InventoryManager::class, "createContainerOpen");
		foreach([[new DispenserInventory(new Position(1, 64, 3, null)), WindowTypes::DISPENSER], [new DropperInventory(new Position(1, 64, 3, null)), WindowTypes::DROPPER]] as [$inventory, $type]){
			$packets = $open->invoke(null, 12, $inventory);
			self::assertCount(1, $packets);
			self::assertInstanceOf(ContainerOpenPacket::class, $packets[0]);
			self::assertSame($type, $packets[0]->windowType);
			self::assertSame(12, $packets[0]->windowId);
			self::assertSame(9, $inventory->getSize());
		}
	}

	public function testOpenSyncCloseAndReopenUsesNativeSlotMapping() : void{
		$converter = convert\TypeConverter::getInstance();
		$packets = [];
		$session = $this->getMockBuilder(NetworkSession::class)->disableOriginalConstructor()->onlyMethods(["sendDataPacket", "getTypeConverter", "getLogger", "getProtocolId"])->getMock();
		$session->method("getTypeConverter")->willReturn($converter);
		$session->method("getProtocolId")->willReturn($converter->getProtocolId());
		$session->method("getLogger")->willReturn($this->createMock(\Logger::class));
		$session->method("sendDataPacket")->willReturnCallback(static function($packet) use (&$packets) : bool{
			$packets[] = $packet;
			return true;
		});
		$manager = (new \ReflectionClass(InventoryManager::class))->newInstanceWithoutConstructor();
		(new \ReflectionProperty(InventoryManager::class, "session"))->setValue($manager, $session);
		$callbacks = new \pocketmine\utils\ObjectSet();
		$open = new \ReflectionMethod(InventoryManager::class, "createContainerOpen");
		$callbacks->add(static fn(int $id, \pocketmine\inventory\Inventory $inventory) => $open->invoke(null, $id, $inventory));
		(new \ReflectionProperty(InventoryManager::class, "containerOpenCallbacks"))->setValue($manager, $callbacks);
		foreach([[new DispenserInventory(new Position(1, 64, 3, null)), WindowTypes::DISPENSER], [new DropperInventory(new Position(1, 64, 3, null)), WindowTypes::DROPPER]] as [$inventory, $type]){
			$items = \pocketmine\item\VanillaItems::DIAMOND()->setCount(9);
			$inventory->setItem(8, $items);
			$packets = [];
			$manager->onCurrentWindowChange($inventory);
			self::assertCount(3, $packets);
			self::assertInstanceOf(ContainerOpenPacket::class, $packets[0]);
			self::assertSame($type, $packets[0]->windowType);
			self::assertInstanceOf(protocol\InventoryContentPacket::class, $packets[1]);
			self::assertCount(9, $packets[1]->items);
			self::assertInstanceOf(protocol\InventoryContentPacket::class, $packets[2]);
			self::assertCount(9, $packets[2]->items);
			self::assertSame(9, $packets[2]->items[8]->getItemStack()->getCount());
			$id = $manager->getCurrentWindowId();
			for($slot = 0; $slot < 9; ++$slot){
				[$window, $nativeSlot] = handler\ItemStackContainerIdTranslator::translate(protocol\types\inventory\ContainerUIIds::LEVEL_ENTITY, $id, $slot);
				self::assertSame([$inventory, $slot], $manager->locateWindowAndSlot($window, $nativeSlot));
			}
			self::assertNull($manager->locateWindowAndSlot($id, 9));
			$manager->onCurrentWindowRemove();
			self::assertInstanceOf(protocol\ContainerClosePacket::class, $packets[3]);
			self::assertSame($type, $packets[3]->windowType);
			$manager->onClientRemoveWindow($id);
			self::assertNull($manager->locateWindowAndSlot($id, 8));
			self::assertTrue($inventory->getItem(8)->equalsExact($items));
		}
	}
}
