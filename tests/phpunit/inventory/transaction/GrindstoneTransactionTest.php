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

namespace pocketmine\inventory\transaction;

use Logger;
use PHPUnit\Framework\TestCase;
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\Entity;
use pocketmine\entity\ExperienceManager;
use pocketmine\item\Durable;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\enchantment\VanillaEnchantments;
use pocketmine\item\ItemTypeIds;
use pocketmine\item\VanillaItems;
use pocketmine\player\Player;
use ReflectionProperty;

final class GrindstoneTransactionTest extends TestCase{

	private function createMockPlayer() : Player{
		$player = $this->getMockBuilder(Player::class)
			->disableOriginalConstructor()
			->onlyMethods(["getXpManager", "isCreative", "onDispose"])
			->getMock();

		$xpManager = $this->createMock(ExperienceManager::class);
		$xpManager->method("getXpLevel")->willReturn(10);
		$player->method("getXpManager")->willReturn($xpManager);
		$player->method("isCreative")->willReturn(false);

		(new ReflectionProperty(Entity::class, "closed"))->setValue($player, true);
		(new ReflectionProperty(Player::class, "logger"))->setValue($player, $this->createMock(Logger::class));

		return $player;
	}

	public function testDisenchantSingleTool() : void{
		$player = $this->createMockPlayer();
		$grindstone = VanillaBlocks::GRINDSTONE();

		$sword = VanillaItems::DIAMOND_SWORD()
			->setCustomName("Excalibur")
			->addEnchantment(new EnchantmentInstance(VanillaEnchantments::SHARPNESS(), 5))
			->addEnchantment(new EnchantmentInstance(VanillaEnchantments::UNBREAKING(), 3));

		$air = VanillaItems::AIR();

		$tx = new GrindstoneTransaction($player, $grindstone, $sword, $air);
		$result = $tx->getResult();

		self::assertFalse($result->hasEnchantments());
		self::assertFalse($result->hasCustomName());
		self::assertSame(ItemTypeIds::DIAMOND_SWORD, $result->getTypeId());
		self::assertGreaterThan(0, $tx->getXp());
	}

	public function testDisenchantEnchantedBook() : void{
		$player = $this->createMockPlayer();
		$grindstone = VanillaBlocks::GRINDSTONE();

		$book = VanillaItems::ENCHANTED_BOOK()
			->addEnchantment(new EnchantmentInstance(VanillaEnchantments::PROTECTION(), 4));

		$air = VanillaItems::AIR();

		$tx = new GrindstoneTransaction($player, $grindstone, $book, $air);
		$result = $tx->getResult();

		self::assertSame(ItemTypeIds::BOOK, $result->getTypeId());
		self::assertFalse($result->hasEnchantments());
		self::assertGreaterThan(0, $tx->getXp());
	}

	public function testPreservesCurseOfVanishing() : void{
		$player = $this->createMockPlayer();
		$grindstone = VanillaBlocks::GRINDSTONE();

		$sword = VanillaItems::DIAMOND_SWORD()
			->addEnchantment(new EnchantmentInstance(VanillaEnchantments::SHARPNESS(), 3))
			->addEnchantment(new EnchantmentInstance(VanillaEnchantments::VANISHING(), 1));

		$air = VanillaItems::AIR();

		$tx = new GrindstoneTransaction($player, $grindstone, $sword, $air);
		$result = $tx->getResult();

		self::assertTrue($result->hasEnchantment(VanillaEnchantments::VANISHING()));
		self::assertFalse($result->hasEnchantment(VanillaEnchantments::SHARPNESS()));
		self::assertGreaterThan(0, $tx->getXp());
	}

	public function testCombineTwoDamagedItems() : void{
		$player = $this->createMockPlayer();
		$grindstone = VanillaBlocks::GRINDSTONE();

		$pick1 = VanillaItems::IRON_PICKAXE();
		$pick2 = VanillaItems::IRON_PICKAXE();
		$max = $pick1->getMaxDurability();

		// Damaged by 100 each (uses left = max - 100)
		$pick1->setDamage(100);
		$pick2->setDamage(100);

		$pick1->addEnchantment(new EnchantmentInstance(VanillaEnchantments::EFFICIENCY(), 2));

		$tx = new GrindstoneTransaction($player, $grindstone, $pick1, $pick2);
		$result = $tx->getResult();

		// Expected combined uses = (max - 100) + (max - 100) + floor(max * 0.05)
		$bonus = (int) floor($max * 0.05);
		$expectedDamage = max(0, $max - (($max - 100) + ($max - 100) + $bonus));

		self::assertInstanceOf(Durable::class, $result);
		self::assertSame($expectedDamage, $result->getDamage());
		self::assertFalse($result->hasEnchantments());
		self::assertGreaterThan(0, $tx->getXp());
	}

	public function testIncompatibleItemsThrow() : void{
		$player = $this->createMockPlayer();
		$grindstone = VanillaBlocks::GRINDSTONE();

		$sword = VanillaItems::DIAMOND_SWORD();
		$pickaxe = VanillaItems::IRON_PICKAXE();

		$this->expectException(TransactionValidationException::class);
		new GrindstoneTransaction($player, $grindstone, $sword, $pickaxe);
	}

	public function testEmptyInputsThrow() : void{
		$player = $this->createMockPlayer();
		$grindstone = VanillaBlocks::GRINDSTONE();

		$this->expectException(TransactionValidationException::class);
		new GrindstoneTransaction($player, $grindstone, VanillaItems::AIR(), VanillaItems::AIR());
	}
}
