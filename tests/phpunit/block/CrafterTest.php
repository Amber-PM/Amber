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

namespace pmmp\encoding {
	if(!class_exists(BE::class, false)){
		class BE{
			public static function unpackSignedLong(string $bytes) : int{
				return (int) unpack('J', $bytes)[1];
			}
		}
		class LE{
			public static function packSignedLong(int $val) : string{
				return pack('P', $val);
			}
		}
	}
}

namespace pocketmine\block {

use PHPUnit\Framework\TestCase;
use pocketmine\block\inventory\CrafterInventory;
use pocketmine\block\tile\Crafter as TileCrafter;
use pocketmine\item\Item;
use pocketmine\item\ItemIdentifier;
use pocketmine\item\ItemTypeIds;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\world\Position;
use pocketmine\world\redstone\RedstoneEngine;
use pocketmine\world\World;

final class CrafterTest extends TestCase{

	public function testCrafterInventoryDisabledSlots() : void{
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);

		$pos = new Position(0, 64, 0, $world);
		$inventory = new CrafterInventory($pos);

		self::assertSame(0, $inventory->getDisabledSlots());
		self::assertFalse($inventory->isSlotDisabled(0));

		// Disable slot 0 and slot 4
		$inventory->setSlotDisabled(0, true);
		$inventory->setSlotDisabled(4, true);

		self::assertTrue($inventory->isSlotDisabled(0));
		self::assertTrue($inventory->isSlotDisabled(4));
		self::assertFalse($inventory->isSlotDisabled(1));
		self::assertSame(1 | (1 << 4), $inventory->getDisabledSlots());

		// Re-enable slot 0
		$inventory->setSlotDisabled(0, false);
		self::assertFalse($inventory->isSlotDisabled(0));
		self::assertSame(1 << 4, $inventory->getDisabledSlots());
	}

	public function testCrafterTileNbtSerialization() : void{
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);

		$tile = new TileCrafter($world, new Vector3(10, 64, 10));
		$tile->getInventory()->setDisabledSlots(0b001010101); // slots 0, 2, 4, 6
		$tile->setTriggered(true);

		$nbt = new CompoundTag();
		(new \ReflectionMethod(TileCrafter::class, "writeSaveData"))->invoke($tile, $nbt);

		self::assertSame(0b001010101, $nbt->getInt("disabled_slots"));
		self::assertSame(1, $nbt->getByte("triggered"));

		$tile2 = new TileCrafter($world, new Vector3(10, 64, 10));
		$tile2->getInventory()->setDisabledSlots($nbt->getInt("disabled_slots", 0));
		$tile2->setTriggered($nbt->getByte("triggered", 0) === 1);

		self::assertSame(0b001010101, $tile2->getInventory()->getDisabledSlots());
		self::assertTrue($tile2->isTriggered());
		self::assertTrue($tile2->getInventory()->isSlotDisabled(0));
		self::assertTrue($tile2->getInventory()->isSlotDisabled(2));
		self::assertFalse($tile2->getInventory()->isSlotDisabled(1));
	}

	public function testCrafterRedstoneRisingEdgeTrigger() : void{
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);

		$engine = new RedstoneEngine($world, 1000);

		$crafter = new Crafter(new BlockIdentifier(BlockTypeIds::CRAFTER), "Crafter", new BlockTypeInfo(BlockBreakInfo::instant()));
		$crafter->position($world, 5, 64, 5);

		// Rising edge: set powered = true
		$crafter->setPowered(true);
		self::assertTrue($crafter->isPowered());

		// Redstone scheduled update executes craft on tile
		$crafter->onRedstoneScheduledUpdate($engine);
		self::assertFalse($crafter->isCrafting());
	}

	public function testCrafterPistonMovableParity() : void{
		self::assertTrue(\pocketmine\block\piston\PistonMoveRules::isTileClassSupported(TileCrafter::class));
	}
}
}
