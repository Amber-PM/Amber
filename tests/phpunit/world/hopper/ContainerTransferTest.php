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

namespace pocketmine\world\hopper;

use PHPUnit\Framework\TestCase;
use pocketmine\block\tile\BrewingStand;
use pocketmine\block\tile\Dispenser;
use pocketmine\block\tile\Dropper;
use pocketmine\crafting\CraftingManager;
use pocketmine\crafting\ExactRecipeIngredient;
use pocketmine\crafting\PotionTypeRecipe;
use pocketmine\item\VanillaItems;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\Server;
use pocketmine\world\World;

final class ContainerTransferTest extends TestCase{
	public function testBrewingStandRejectsInvalidItemsAndRespectsSides() : void{
		$manager = new CraftingManager();
		$manager->registerPotionTypeRecipe(new PotionTypeRecipe(new ExactRecipeIngredient(VanillaItems::POTION()), new ExactRecipeIngredient(\pocketmine\block\VanillaBlocks::NETHER_WART()->asItem()), VanillaItems::POTION()));
		$server = $this->createMock(Server::class);
		$server->method("getCraftingManager")->willReturn($manager);
		$world = $this->createMock(World::class);
		$world->method("getServer")->willReturn($server);
		$world->method("isLoaded")->willReturn(true);
		$stand = new BrewingStand($world, new Vector3(0, 64, 0));
		$transfer = new ContainerTransfer(new ContainerTransferPolicy());
		foreach(Facing::ALL as $side){
			self::assertFalse($transfer->insertOne($stand, VanillaItems::DIAMOND(), $side));
		}
		self::assertTrue($transfer->insertOne($stand, \pocketmine\block\VanillaBlocks::NETHER_WART()->asItem(), Facing::UP));
		self::assertFalse($transfer->insertOne($stand, \pocketmine\block\VanillaBlocks::NETHER_WART()->asItem(), Facing::EAST));
		self::assertFalse($transfer->insertOne($stand, VanillaItems::POTION(), Facing::UP));
		self::assertTrue($transfer->insertOne($stand, VanillaItems::POTION(), Facing::EAST));
		self::assertTrue($transfer->insertOne($stand, VanillaItems::BLAZE_POWDER(), Facing::WEST));
		self::assertFalse($transfer->insertOne($stand, VanillaItems::BLAZE_POWDER(), Facing::DOWN));
		self::assertTrue($stand->getInventory()->getItem(0)->equalsExact(\pocketmine\block\VanillaBlocks::NETHER_WART()->asItem()));
		self::assertTrue($stand->getInventory()->getItem(1)->equalsExact(VanillaItems::POTION()));
		self::assertTrue($stand->getInventory()->getItem(4)->equalsExact(VanillaItems::BLAZE_POWDER()));
	}

	public function testDispenserAndDropperInsertionPrefersStacksAndRejectsFullInventory() : void{
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);
		$transfer = new ContainerTransfer(new ContainerTransferPolicy());
		foreach([new Dispenser($world, new Vector3(0, 64, 0)), new Dropper($world, new Vector3(1, 64, 0))] as $tile){
			$inventory = $tile->getInventory();
			$inventory->setItem(8, VanillaItems::DIAMOND()->setCount(2));
			self::assertTrue($transfer->insertOne($tile, VanillaItems::DIAMOND(), Facing::UP));
			self::assertSame(3, $inventory->getItem(8)->getCount());
			self::assertTrue($inventory->getItem(0)->isNull());
			for($slot = 0; $slot < $inventory->getSize(); ++$slot){
				$inventory->setItem($slot, VanillaItems::DIAMOND()->setCount(64));
			}
			self::assertFalse($transfer->insertOne($tile, VanillaItems::DIAMOND(), Facing::UP));
		}
	}

	public function testBrewingBottleSlotsNeverStackAndIngredientExtractionIsRestricted() : void{
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);
		$stand = new BrewingStand($world, new Vector3(0, 64, 0));
		$policy = new ContainerTransferPolicy();
		$transfer = new ContainerTransfer($policy);
		for($bottle = 0; $bottle < 3; ++$bottle){
			self::assertTrue($transfer->insertOne($stand, VanillaItems::GLASS_BOTTLE(), Facing::EAST));
		}
		self::assertFalse($transfer->insertOne($stand, VanillaItems::GLASS_BOTTLE(), Facing::EAST));
		foreach([1, 2, 3] as $slot){
			self::assertSame(1, $stand->getInventory()->getItem($slot)->getCount());
		}
		self::assertFalse($policy->canExtract($stand, 0, \pocketmine\block\VanillaBlocks::NETHER_WART()->asItem()));
		self::assertTrue($policy->canExtract($stand, 0, VanillaItems::GLASS_BOTTLE()));
	}
}
