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
use pocketmine\block\inventory\DispenserInventory;
use pocketmine\block\inventory\DropperInventory;
use pocketmine\block\tile\Dispenser as TileDispenser;
use pocketmine\block\tile\Dropper as TileDropper;
use pocketmine\block\tile\TileFactory;
use pocketmine\item\StringToItemParser;
use pocketmine\math\Facing;
use pocketmine\network\mcpe\protocol\types\inventory\WindowTypes;
use pocketmine\world\Position;
use ReflectionClass;

final class DispenserDropperBlockTest extends TestCase{

	public function testBlockTypeIds() : void{
		self::assertSame(10828, BlockTypeIds::DISPENSER);
		self::assertSame(10829, BlockTypeIds::DROPPER);
	}

	public function testVanillaBlocksAccessors() : void{
		$dispenser = VanillaBlocks::DISPENSER();
		self::assertInstanceOf(Dispenser::class, $dispenser);
		self::assertSame(BlockTypeIds::DISPENSER, $dispenser->getTypeId());
		self::assertSame("Dispenser", $dispenser->getName());

		$dropper = VanillaBlocks::DROPPER();
		self::assertInstanceOf(Dropper::class, $dropper);
		self::assertSame(BlockTypeIds::DROPPER, $dropper->getTypeId());
		self::assertSame("Dropper", $dropper->getName());
	}

	public function testFacingAndPoweredState() : void{
		$dispenser = VanillaBlocks::DISPENSER();
		self::assertSame(Facing::NORTH, $dispenser->getFacing());
		self::assertFalse($dispenser->isPowered());

		$dispenser->setFacing(Facing::UP);
		self::assertSame(Facing::UP, $dispenser->getFacing());

		$dispenser->setPowered(true);
		self::assertTrue($dispenser->isPowered());

		$dropper = VanillaBlocks::DROPPER();
		self::assertSame(Facing::NORTH, $dropper->getFacing());
		self::assertFalse($dropper->isPowered());

		$dropper->setFacing(Facing::DOWN);
		self::assertSame(Facing::DOWN, $dropper->getFacing());

		$dropper->setPowered(true);
		self::assertTrue($dropper->isPowered());
	}

	public function testStringToItemParser() : void{
		$parser = StringToItemParser::getInstance();

		$dispenserItem = $parser->parse("dispenser");
		self::assertNotNull($dispenserItem);
		self::assertSame(BlockTypeIds::DISPENSER, $dispenserItem->getBlock()->getTypeId());

		$dropperItem = $parser->parse("dropper");
		self::assertNotNull($dropperItem);
		self::assertSame(BlockTypeIds::DROPPER, $dropperItem->getBlock()->getTypeId());
	}

	public function testTileFactoryRegistration() : void{
		$tileFactory = TileFactory::getInstance();
		$refClass = new ReflectionClass(TileFactory::class);
		$knownTilesProp = $refClass->getProperty("knownTiles");
		/** @var array<string, class-string> $knownTiles */
		$knownTiles = $knownTilesProp->getValue($tileFactory);

		self::assertArrayHasKey("Dispenser", $knownTiles);
		self::assertSame(TileDispenser::class, $knownTiles["Dispenser"]);
		self::assertArrayHasKey("Trap", $knownTiles);
		self::assertSame(TileDispenser::class, $knownTiles["Trap"]);
		self::assertArrayHasKey("minecraft:dispenser", $knownTiles);
		self::assertSame(TileDispenser::class, $knownTiles["minecraft:dispenser"]);

		self::assertArrayHasKey("Dropper", $knownTiles);
		self::assertSame(TileDropper::class, $knownTiles["Dropper"]);
		self::assertArrayHasKey("minecraft:dropper", $knownTiles);
		self::assertSame(TileDropper::class, $knownTiles["minecraft:dropper"]);
	}

	public function testInventorySizes() : void{
		$dispenserInv = new DispenserInventory(new Position(0, 0, 0, null));
		self::assertSame(9, $dispenserInv->getSize());

		$dropperInv = new DropperInventory(new Position(0, 0, 0, null));
		self::assertSame(9, $dropperInv->getSize());
	}

	public function testWindowTypes() : void{
		self::assertSame(6, WindowTypes::DISPENSER);
		self::assertSame(7, WindowTypes::DROPPER);
	}
}
