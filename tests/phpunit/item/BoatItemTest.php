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

namespace pocketmine\tests\item;

use PHPUnit\Framework\TestCase;
use pocketmine\item\Boat;
use pocketmine\item\BoatType;
use pocketmine\item\ChestBoat;
use pocketmine\item\ItemIdentifier;
use pocketmine\item\ItemTypeIds;
use pocketmine\item\StringToItemParser;
use pocketmine\item\VanillaItems;

class BoatItemTest extends TestCase{

	public function testBoatTypeVariants() : void{
		$cases = BoatType::cases();
		self::assertCount(10, $cases);

		self::assertTrue(BoatType::BAMBOO->isRaft());
		self::assertFalse(BoatType::OAK->isRaft());
		self::assertFalse(BoatType::CHERRY->isRaft());

		self::assertSame(0, BoatType::OAK->getVariantId());
		self::assertSame(1, BoatType::SPRUCE->getVariantId());
		self::assertSame(2, BoatType::BIRCH->getVariantId());
		self::assertSame(3, BoatType::JUNGLE->getVariantId());
		self::assertSame(4, BoatType::ACACIA->getVariantId());
		self::assertSame(5, BoatType::DARK_OAK->getVariantId());
		self::assertSame(6, BoatType::MANGROVE->getVariantId());
		self::assertSame(7, BoatType::BAMBOO->getVariantId());
		self::assertSame(8, BoatType::CHERRY->getVariantId());
		self::assertSame(9, BoatType::PALE_OAK->getVariantId());
	}

	public function testBoatItemProperties() : void{
		$boat = new Boat(new ItemIdentifier(ItemTypeIds::OAK_BOAT), "Oak Boat", BoatType::OAK);
		self::assertSame(1, $boat->getMaxStackSize());
		self::assertSame(1200, $boat->getFuelTime());
		self::assertSame(BoatType::OAK, $boat->getType());

		$raft = new Boat(new ItemIdentifier(ItemTypeIds::BAMBOO_RAFT), "Bamboo Raft", BoatType::BAMBOO);
		self::assertSame(BoatType::BAMBOO, $raft->getType());
	}

	public function testChestBoatItemProperties() : void{
		$chestBoat = new ChestBoat(new ItemIdentifier(ItemTypeIds::OAK_CHEST_BOAT), "Oak Boat with Chest", BoatType::OAK);
		self::assertSame(1, $chestBoat->getMaxStackSize());
		self::assertSame(1200, $chestBoat->getFuelTime());
		self::assertSame(BoatType::OAK, $chestBoat->getType());

		$chestRaft = new ChestBoat(new ItemIdentifier(ItemTypeIds::BAMBOO_CHEST_RAFT), "Bamboo Chest Raft", BoatType::BAMBOO);
		self::assertSame(BoatType::BAMBOO, $chestRaft->getType());
	}

	public function testStringToItemParser() : void{
		$parser = StringToItemParser::getInstance();

		self::assertInstanceOf(Boat::class, $parser->parse("oak_boat"));
		self::assertInstanceOf(Boat::class, $parser->parse("bamboo_raft"));
		self::assertInstanceOf(Boat::class, $parser->parse("cherry_boat"));
		self::assertInstanceOf(Boat::class, $parser->parse("pale_oak_boat"));

		self::assertInstanceOf(ChestBoat::class, $parser->parse("oak_chest_boat"));
		self::assertInstanceOf(ChestBoat::class, $parser->parse("bamboo_chest_raft"));
		self::assertInstanceOf(ChestBoat::class, $parser->parse("cherry_chest_boat"));
		self::assertInstanceOf(ChestBoat::class, $parser->parse("pale_oak_chest_boat"));
	}

	public function testVanillaItemsAccessors() : void{
		self::assertSame(BoatType::OAK, VanillaItems::OAK_BOAT()->getType());
		self::assertSame(BoatType::BAMBOO, VanillaItems::BAMBOO_RAFT()->getType());
		self::assertSame(BoatType::CHERRY, VanillaItems::CHERRY_BOAT()->getType());
		self::assertSame(BoatType::PALE_OAK, VanillaItems::PALE_OAK_BOAT()->getType());

		self::assertSame(BoatType::OAK, VanillaItems::OAK_CHEST_BOAT()->getType());
		self::assertSame(BoatType::BAMBOO, VanillaItems::BAMBOO_CHEST_RAFT()->getType());
		self::assertSame(BoatType::CHERRY, VanillaItems::CHERRY_CHEST_BOAT()->getType());
		self::assertSame(BoatType::PALE_OAK, VanillaItems::PALE_OAK_CHEST_BOAT()->getType());
	}
}
