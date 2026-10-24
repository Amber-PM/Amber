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
use pocketmine\item\ItemTypeIds;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\world\World;

final class ComposterTest extends TestCase{

	public function testFillLevelBounds() : void{
		$composter = VanillaBlocks::COMPOSTER();
		self::assertSame(0, $composter->getFillLevel());

		$composter->setFillLevel(8);
		self::assertSame(8, $composter->getFillLevel());

		$this->expectException(\InvalidArgumentException::class);
		$composter->setFillLevel(9);
	}

	public function testCompostChances() : void{
		// 100%
		self::assertSame(100, Composter::getCompostChance(VanillaItems::PUMPKIN_PIE()));
		self::assertSame(100, Composter::getCompostChance(VanillaBlocks::CAKE()->asItem()));

		// 85%
		self::assertSame(85, Composter::getCompostChance(VanillaItems::BREAD()));
		self::assertSame(85, Composter::getCompostChance(VanillaItems::BAKED_POTATO()));
		self::assertSame(85, Composter::getCompostChance(VanillaItems::COOKIE()));
		self::assertSame(85, Composter::getCompostChance(VanillaBlocks::HAY_BALE()->asItem()));

		// 65%
		self::assertSame(65, Composter::getCompostChance(VanillaItems::APPLE()));
		self::assertSame(65, Composter::getCompostChance(VanillaItems::CARROT()));
		self::assertSame(65, Composter::getCompostChance(VanillaItems::POTATO()));
		self::assertSame(65, Composter::getCompostChance(VanillaItems::BEETROOT()));
		self::assertSame(65, Composter::getCompostChance(VanillaBlocks::NETHER_WART()->asItem()));
		self::assertSame(65, Composter::getCompostChance(VanillaBlocks::MELON()->asItem()));
		self::assertSame(65, Composter::getCompostChance(VanillaBlocks::PUMPKIN()->asItem()));
		self::assertSame(65, Composter::getCompostChance(VanillaBlocks::DANDELION()->asItem()));

		// 50%
		self::assertSame(50, Composter::getCompostChance(VanillaItems::MELON()));
		self::assertSame(50, Composter::getCompostChance(VanillaBlocks::SUGARCANE()->asItem()));
		self::assertSame(50, Composter::getCompostChance(VanillaBlocks::CACTUS()->asItem()));
		self::assertSame(50, Composter::getCompostChance(VanillaBlocks::DRIED_KELP()->asItem()));

		// 30%
		self::assertSame(30, Composter::getCompostChance(VanillaItems::WHEAT_SEEDS()));
		self::assertSame(30, Composter::getCompostChance(VanillaItems::BEETROOT_SEEDS()));
		self::assertSame(30, Composter::getCompostChance(VanillaItems::SWEET_BERRIES()));
		self::assertSame(30, Composter::getCompostChance(VanillaItems::GLOW_BERRIES()));
		self::assertSame(30, Composter::getCompostChance(VanillaItems::DRIED_KELP()));
		self::assertSame(30, Composter::getCompostChance(VanillaBlocks::OAK_LEAVES()->asItem()));
		self::assertSame(30, Composter::getCompostChance(VanillaBlocks::OAK_SAPLING()->asItem()));

		// Non-compostable
		self::assertNull(Composter::getCompostChance(VanillaItems::DIAMOND()));
		self::assertNull(Composter::getCompostChance(VanillaItems::IRON_INGOT()));
	}

	public function testCompostHarvestAtLevel8() : void{
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);
		$droppedItem = null;
		$world->method("dropItem")->willReturnCallback(function($pos, $item) use (&$droppedItem){
			$droppedItem = $item;
			return null;
		});

		$composter = VanillaBlocks::COMPOSTER()->setFillLevel(8);
		$composter->position($world, 0, 64, 0);

		$returned = [];
		$res = $composter->onInteract(VanillaItems::AIR(), 1, new Vector3(0, 0, 0), null, $returned);
		self::assertTrue($res);
		self::assertNotNull($droppedItem);
		self::assertSame(ItemTypeIds::BONE_MEAL, $droppedItem->getTypeId());
	}

	public function testScheduledUpdateTransitionsLevel7To8() : void{
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);
		$updatedBlock = null;
		$world->method("setBlock")->willReturnCallback(function($pos, $block) use (&$updatedBlock){
			$updatedBlock = $block;
			return true;
		});

		$composter = VanillaBlocks::COMPOSTER()->setFillLevel(7);
		$composter->position($world, 0, 64, 0);

		$composter->onScheduledUpdate();
		self::assertInstanceOf(Composter::class, $updatedBlock);
		self::assertSame(8, $updatedBlock->getFillLevel());
	}
}
