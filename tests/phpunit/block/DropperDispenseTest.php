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

namespace pocketmine\block;

use PHPUnit\Framework\TestCase;
use pocketmine\block\dispenser\BlockSource;
use pocketmine\block\dispenser\DefaultDispenseBehavior;
use pocketmine\block\dispenser\DispenseBehavior;
use pocketmine\block\dispenser\DispenseBehaviorRegistry;
use pocketmine\block\inventory\DropperInventory;
use pocketmine\block\inventory\FurnaceInventory;
use pocketmine\block\inventory\ShulkerBoxInventory;
use pocketmine\block\tile\Container;
use pocketmine\block\tile\Dropper as TileDropper;
use pocketmine\block\tile\Furnace as TileFurnace;
use pocketmine\block\tile\ShulkerBox as TileShulkerBox;
use pocketmine\crafting\FurnaceType;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\LevelEventPacket;
use pocketmine\network\mcpe\protocol\types\LevelEvent;
use pocketmine\world\Position;
use pocketmine\world\sound\ClickFailSound;
use pocketmine\world\World;

final class DropperDispenseTest extends TestCase{

	public function testBlockSourceDispensePositions() : void{
		$world = $this->createMock(World::class);
		$pos = new Vector3(10, 20, 30);

		foreach([Facing::DOWN, Facing::UP, Facing::NORTH, Facing::SOUTH, Facing::WEST, Facing::EAST] as $facing){
			$source = new BlockSource($world, $pos, $facing);
			self::assertSame($facing, $source->getFacing());
			self::assertSame($world, $source->getWorld());
			self::assertSame($pos, $source->getPos());

			[$dx, $dy, $dz] = Facing::OFFSET[$facing];
			$expectedPos = new Vector3(10.5 + $dx * 0.7, 20.5 + $dy * 0.7, 30.5 + $dz * 0.7);
			$actualPos = $source->getDispensePosition();
			self::assertEqualsWithDelta($expectedPos->x, $actualPos->x, 0.0001);
			self::assertEqualsWithDelta($expectedPos->y, $actualPos->y, 0.0001);
			self::assertEqualsWithDelta($expectedPos->z, $actualPos->z, 0.0001);
		}
	}

	public function testDispenseBehaviorRegistry() : void{
		$registry = DispenseBehaviorRegistry::getInstance();
		$default = $registry->getDefault();
		self::assertInstanceOf(DefaultDispenseBehavior::class, $default);

		$dirt = VanillaBlocks::DIRT()->asItem();
		self::assertSame($default, $registry->get($dirt));

		$customBehavior = new class implements DispenseBehavior{
			public function dispense(BlockSource $source, Item $item) : Item{
				return $item;
			}
		};

		$registry->register($dirt->getTypeId(), $customBehavior);
		self::assertSame($customBehavior, $registry->get($dirt));

		// Reset back to default
		$registry->register($dirt->getTypeId(), $default);
	}

	public function testClickFailSound() : void{
		$sound = new ClickFailSound();
		$packets = $sound->encode(new Vector3(0, 0, 0));
		self::assertCount(1, $packets);
		self::assertInstanceOf(LevelEventPacket::class, $packets[0]);
		self::assertSame(LevelEvent::SOUND_CLICK_FAIL, $packets[0]->eventId);
	}

	public function testDropperPushesIntoContainer() : void{
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);

		$dropperBlock = VanillaBlocks::DROPPER();
		$dropperBlock->setFacing(Facing::NORTH);

		$dropperPos = new Position(10, 20, 30, $world);
		$targetPos = $dropperPos->getSide(Facing::NORTH);

		$dropperInv = new DropperInventory($dropperPos);
		$dropperInv->setItem(0, VanillaItems::DIAMOND()->setCount(5));

		$targetInv = new DropperInventory($targetPos);

		$dropperTile = $this->createMock(TileDropper::class);
		$dropperTile->method("getInventory")->willReturn($dropperInv);

		$targetTile = $this->createMock(TileDropper::class);
		$targetTile->method("getInventory")->willReturn($targetInv);

		$world->method("getTile")->willReturnCallback(function(Vector3 $pos) use ($dropperPos, $targetPos, $dropperTile, $targetTile) : ?TileDropper{
			if($pos->equals($dropperPos)){
				return $dropperTile;
			}
			if($pos->equals($targetPos)){
				return $targetTile;
			}
			return null;
		});

		$dropperBlock->position($world, 10, 20, 30);

		$dropperBlock->drop();

		// Dropper should now have 4 diamonds
		self::assertSame(4, $dropperInv->getItem(0)->getCount());
		// Target should have 1 diamond
		self::assertSame(1, $targetInv->getItem(0)->getCount());
		self::assertSame(VanillaItems::DIAMOND()->getTypeId(), $targetInv->getItem(0)->getTypeId());
	}

	public function testDropperFailsWhenContainerFull() : void{
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);

		$dropperBlock = VanillaBlocks::DROPPER();
		$dropperBlock->setFacing(Facing::NORTH);

		$dropperPos = new Position(10, 20, 30, $world);
		$targetPos = $dropperPos->getSide(Facing::NORTH);

		$dropperInv = new DropperInventory($dropperPos);
		$dropperInv->setItem(0, VanillaItems::DIAMOND()->setCount(5));

		// Fill target inventory completely with different items so canAddItem is false
		$targetInv = new DropperInventory($targetPos);
		for($i = 0; $i < 9; ++$i){
			$targetInv->setItem($i, VanillaBlocks::DIRT()->asItem()->setCount(64));
		}

		$dropperTile = $this->createMock(TileDropper::class);
		$dropperTile->method("getInventory")->willReturn($dropperInv);

		$targetTile = $this->createMock(TileDropper::class);
		$targetTile->method("getInventory")->willReturn($targetInv);

		$world->method("getTile")->willReturnCallback(function(Vector3 $pos) use ($dropperPos, $targetPos, $dropperTile, $targetTile) : ?TileDropper{
			if($pos->equals($dropperPos)){
				return $dropperTile;
			}
			if($pos->equals($targetPos)){
				return $targetTile;
			}
			return null;
		});

		$dropperBlock->position($world, 10, 20, 30);

		$dropperBlock->drop();

		// Dropper item count unchanged because target container was full
		self::assertSame(5, $dropperInv->getItem(0)->getCount());
	}

	public function testDropperDoesNotInsertNonFuelIntoFurnaceSide() : void{
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);

		$dropperBlock = VanillaBlocks::DROPPER();
		$dropperBlock->setFacing(Facing::NORTH);

		$dropperPos = new Position(10, 20, 30, $world);
		$targetPos = $dropperPos->getSide(Facing::NORTH);

		$dropperInv = new DropperInventory($dropperPos);
		$dropperInv->setItem(0, VanillaItems::DIAMOND()->setCount(5));

		// Furnace with input full, fuel full, and result/output empty
		$furnaceInv = new FurnaceInventory($targetPos, FurnaceType::FURNACE);
		$furnaceInv->setSmelting(VanillaBlocks::COBBLESTONE()->asItem()->setCount(64));
		$furnaceInv->setFuel(VanillaItems::COAL()->setCount(64));
		// Output slot is empty

		$dropperTile = $this->createMock(TileDropper::class);
		$dropperTile->method("getInventory")->willReturn($dropperInv);

		$furnaceTile = $this->createMock(TileFurnace::class);
		$furnaceTile->method("getInventory")->willReturn($furnaceInv);

		$world->method("getTile")->willReturnCallback(function(Vector3 $pos) use ($dropperPos, $targetPos, $dropperTile, $furnaceTile) : ?Container{
			if($pos->equals($dropperPos)){
				return $dropperTile;
			}
			if($pos->equals($targetPos)){
				return $furnaceTile;
			}
			return null;
		});

		$dropperBlock->position($world, 10, 20, 30);
		$dropperBlock->drop();

		// Source diamond is left unchanged because diamond cannot be inserted into furnace side
		self::assertSame(5, $dropperInv->getItem(0)->getCount());
		// Output slot must remain empty and not have received the diamond
		self::assertTrue($furnaceInv->getResult()->isNull());
	}

	public function testDropperDoesNotInsertDiamondIntoEmptyFurnaceOutputFromSideEvenWhenInputAndFuelEmpty() : void{
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);

		$dropperBlock = VanillaBlocks::DROPPER();
		$dropperBlock->setFacing(Facing::NORTH);

		$dropperPos = new Position(10, 20, 30, $world);
		$targetPos = $dropperPos->getSide(Facing::NORTH);

		$dropperInv = new DropperInventory($dropperPos);
		$dropperInv->setItem(0, VanillaItems::DIAMOND()->setCount(3));

		// Completely empty furnace
		$furnaceInv = new FurnaceInventory($targetPos, FurnaceType::FURNACE);

		$dropperTile = $this->createMock(TileDropper::class);
		$dropperTile->method("getInventory")->willReturn($dropperInv);

		$furnaceTile = $this->createMock(TileFurnace::class);
		$furnaceTile->method("getInventory")->willReturn($furnaceInv);

		$world->method("getTile")->willReturnCallback(function(Vector3 $pos) use ($dropperPos, $targetPos, $dropperTile, $furnaceTile) : ?Container{
			if($pos->equals($dropperPos)){
				return $dropperTile;
			}
			if($pos->equals($targetPos)){
				return $furnaceTile;
			}
			return null;
		});

		$dropperBlock->position($world, 10, 20, 30);
		$dropperBlock->drop();

		// Non-fuel cannot enter from side, even if slots are empty
		self::assertSame(3, $dropperInv->getItem(0)->getCount());
		self::assertTrue($furnaceInv->getSmelting()->isNull());
		self::assertTrue($furnaceInv->getFuel()->isNull());
		self::assertTrue($furnaceInv->getResult()->isNull());
	}

	public function testDropperInsertsFuelIntoFurnaceSide() : void{
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);

		$dropperBlock = VanillaBlocks::DROPPER();
		$dropperBlock->setFacing(Facing::NORTH);

		$dropperPos = new Position(10, 20, 30, $world);
		$targetPos = $dropperPos->getSide(Facing::NORTH);

		$dropperInv = new DropperInventory($dropperPos);
		$dropperInv->setItem(0, VanillaItems::COAL()->setCount(5));

		$furnaceInv = new FurnaceInventory($targetPos, FurnaceType::FURNACE);

		$dropperTile = $this->createMock(TileDropper::class);
		$dropperTile->method("getInventory")->willReturn($dropperInv);

		$furnaceTile = $this->createMock(TileFurnace::class);
		$furnaceTile->method("getInventory")->willReturn($furnaceInv);

		$world->method("getTile")->willReturnCallback(function(Vector3 $pos) use ($dropperPos, $targetPos, $dropperTile, $furnaceTile) : ?Container{
			if($pos->equals($dropperPos)){
				return $dropperTile;
			}
			if($pos->equals($targetPos)){
				return $furnaceTile;
			}
			return null;
		});

		$dropperBlock->position($world, 10, 20, 30);
		$dropperBlock->drop();

		// 1 coal transferred to fuel slot
		self::assertSame(4, $dropperInv->getItem(0)->getCount());
		self::assertSame(1, $furnaceInv->getFuel()->getCount());
		self::assertSame(VanillaItems::COAL()->getTypeId(), $furnaceInv->getFuel()->getTypeId());
		self::assertTrue($furnaceInv->getResult()->isNull());
	}

	public function testDropperInsertsItemIntoFurnaceTop() : void{
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);

		$dropperBlock = VanillaBlocks::DROPPER();
		$dropperBlock->setFacing(Facing::DOWN); // Dropper above furnace facing down

		$dropperPos = new Position(10, 21, 30, $world);
		$targetPos = $dropperPos->getSide(Facing::DOWN);

		$dropperInv = new DropperInventory($dropperPos);
		$dropperInv->setItem(0, VanillaItems::DIAMOND()->setCount(5));

		$furnaceInv = new FurnaceInventory($targetPos, FurnaceType::FURNACE);

		$dropperTile = $this->createMock(TileDropper::class);
		$dropperTile->method("getInventory")->willReturn($dropperInv);

		$furnaceTile = $this->createMock(TileFurnace::class);
		$furnaceTile->method("getInventory")->willReturn($furnaceInv);

		$world->method("getTile")->willReturnCallback(function(Vector3 $pos) use ($dropperPos, $targetPos, $dropperTile, $furnaceTile) : ?Container{
			if($pos->equals($dropperPos)){
				return $dropperTile;
			}
			if($pos->equals($targetPos)){
				return $furnaceTile;
			}
			return null;
		});

		$dropperBlock->position($world, 10, 21, 30);
		$dropperBlock->drop();

		// 1 diamond transferred to input/smelting slot
		self::assertSame(4, $dropperInv->getItem(0)->getCount());
		self::assertSame(1, $furnaceInv->getSmelting()->getCount());
		self::assertSame(VanillaItems::DIAMOND()->getTypeId(), $furnaceInv->getSmelting()->getTypeId());
		self::assertTrue($furnaceInv->getResult()->isNull());
	}

	public function testDropperDoesNotInsertIntoFurnaceBottom() : void{
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);

		$dropperBlock = VanillaBlocks::DROPPER();
		$dropperBlock->setFacing(Facing::UP); // Dropper below furnace facing up

		$dropperPos = new Position(10, 19, 30, $world);
		$targetPos = $dropperPos->getSide(Facing::UP);

		$dropperInv = new DropperInventory($dropperPos);
		$dropperInv->setItem(0, VanillaItems::DIAMOND()->setCount(5));

		$furnaceInv = new FurnaceInventory($targetPos, FurnaceType::FURNACE);

		$dropperTile = $this->createMock(TileDropper::class);
		$dropperTile->method("getInventory")->willReturn($dropperInv);

		$furnaceTile = $this->createMock(TileFurnace::class);
		$furnaceTile->method("getInventory")->willReturn($furnaceInv);

		$world->method("getTile")->willReturnCallback(function(Vector3 $pos) use ($dropperPos, $targetPos, $dropperTile, $furnaceTile) : ?Container{
			if($pos->equals($dropperPos)){
				return $dropperTile;
			}
			if($pos->equals($targetPos)){
				return $furnaceTile;
			}
			return null;
		});

		$dropperBlock->position($world, 10, 19, 30);
		$dropperBlock->drop();

		// Insertion from bottom is invalid
		self::assertSame(5, $dropperInv->getItem(0)->getCount());
		self::assertTrue($furnaceInv->getResult()->isNull());
		self::assertTrue($furnaceInv->getSmelting()->isNull());
		self::assertTrue($furnaceInv->getFuel()->isNull());
	}

	public function testDropperDoesNotInsertShulkerBoxIntoShulkerBox() : void{
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);

		$dropperBlock = VanillaBlocks::DROPPER();
		$dropperBlock->setFacing(Facing::NORTH);

		$dropperPos = new Position(10, 20, 30, $world);
		$targetPos = $dropperPos->getSide(Facing::NORTH);

		$dropperInv = new DropperInventory($dropperPos);
		$dropperInv->setItem(0, VanillaBlocks::SHULKER_BOX()->asItem());

		$shulkerInv = new ShulkerBoxInventory($targetPos);

		$dropperTile = $this->createMock(TileDropper::class);
		$dropperTile->method("getInventory")->willReturn($dropperInv);

		$shulkerTile = $this->createMock(TileShulkerBox::class);
		$shulkerTile->method("getInventory")->willReturn($shulkerInv);

		$world->method("getTile")->willReturnCallback(function(Vector3 $pos) use ($dropperPos, $targetPos, $dropperTile, $shulkerTile) : ?Container{
			if($pos->equals($dropperPos)){
				return $dropperTile;
			}
			if($pos->equals($targetPos)){
				return $shulkerTile;
			}
			return null;
		});

		$dropperBlock->position($world, 10, 20, 30);
		$dropperBlock->drop();

		// Shulker box cannot be inserted into another shulker box
		self::assertFalse($dropperInv->getItem(0)->isNull());
		self::assertTrue($shulkerInv->getItem(0)->isNull());
	}
}
