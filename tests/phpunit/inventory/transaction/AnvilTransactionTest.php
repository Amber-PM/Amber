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
use pocketmine\inventory\SimpleInventory;
use pocketmine\inventory\transaction\action\SlotChangeAction;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\enchantment\VanillaEnchantments;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\player\Player;
use ReflectionMethod;
use ReflectionProperty;
use function floor;

final class AnvilTransactionTest extends TestCase{

	/**
	 * @param Item[] $deleted
	 * @param Item[] $expected
	 */
	private static function assertConsumed(array $deleted, array $expected) : void{
		$method = new ReflectionMethod(AnvilTransaction::class, "assertConsumed");
		$method->invoke(null, $deleted, $expected);
	}

	private static function book() : Item{
		return VanillaItems::ENCHANTED_BOOK()->addEnchantment(new EnchantmentInstance(VanillaEnchantments::SHARPNESS(), 5));
	}

	private function createMockPlayer() : Player{
		$player = $this->getMockBuilder(Player::class)
			->disableOriginalConstructor()
			->onlyMethods(["getXpManager", "isCreative", "onDispose"])
			->getMock();

		$xpManager = $this->createMock(ExperienceManager::class);
		$xpManager->method("getXpLevel")->willReturn(100);
		$player->method("getXpManager")->willReturn($xpManager);
		$player->method("isCreative")->willReturn(false);

		(new ReflectionProperty(Entity::class, "closed"))->setValue($player, true);
		(new ReflectionProperty(Player::class, "logger"))->setValue($player, $this->createMock(Logger::class));

		return $player;
	}

	public function testExactInputsAccepted() : void{
		self::assertConsumed([VanillaItems::DIAMOND_SWORD(), self::book()], [VanillaItems::DIAMOND_SWORD(), self::book()]);
		self::assertConsumed([VanillaItems::DIAMOND()->setCount(2), VanillaItems::DIAMOND()], [VanillaItems::DIAMOND()->setCount(3)]);
		$this->addToAssertionCount(2);
	}

	public function testKeepingTheBookRejected() : void{
		$this->expectException(TransactionValidationException::class);
		self::assertConsumed([VanillaItems::DIAMOND_SWORD()], [VanillaItems::DIAMOND_SWORD(), self::book()]);
	}

	public function testConsumingSomethingElseRejected() : void{
		$this->expectException(TransactionValidationException::class);
		self::assertConsumed([VanillaItems::STICK(), self::book()], [VanillaItems::DIAMOND_SWORD(), self::book()]);
	}

	public function testConsumingTooLittleMaterialRejected() : void{
		$this->expectException(TransactionValidationException::class);
		self::assertConsumed([VanillaItems::DIAMOND_SWORD(), VanillaItems::DIAMOND()], [VanillaItems::DIAMOND_SWORD(), VanillaItems::DIAMOND()->setCount(3)]);
	}

	public function testConsumingTooMuchRejected() : void{
		$this->expectException(TransactionValidationException::class);
		self::assertConsumed([VanillaItems::DIAMOND_SWORD(), VanillaItems::DIAMOND()->setCount(4)], [VanillaItems::DIAMOND_SWORD(), VanillaItems::DIAMOND()->setCount(3)]);
	}

	public function testDifferentNbtRejected() : void{
		$this->expectException(TransactionValidationException::class);
		self::assertConsumed([VanillaItems::DIAMOND_SWORD(), VanillaItems::ENCHANTED_BOOK()], [VanillaItems::DIAMOND_SWORD(), self::book()]);
	}

	public function testValidateRejectsRetainingEnchantedBook() : void{
		$player = $this->createMockPlayer();
		$anvil = VanillaBlocks::ANVIL();
		$sword = VanillaItems::DIAMOND_SWORD();
		$book = self::book();

		$inv = new SimpleInventory(3);
		$inv->setItem(0, $sword);
		$inv->setItem(1, $book);

		$tx = new AnvilTransaction($player, $anvil, clone $sword, clone $book, null, []);
		$result = $tx->getResult();

		// Client attempts to claim output without deleting the enchanted book
		$tx->addAction(new SlotChangeAction($inv, 0, $sword, VanillaItems::AIR()));
		$tx->addAction(new SlotChangeAction($inv, 2, VanillaItems::AIR(), $result));

		$this->expectException(TransactionValidationException::class);
		$tx->validate();
	}

	public function testValidateRejectsSubstitutingMaterial() : void{
		$player = $this->createMockPlayer();
		$anvil = VanillaBlocks::ANVIL();
		$sword = VanillaItems::DIAMOND_SWORD();
		$book = self::book();
		$dirt = VanillaBlocks::DIRT()->asItem();

		$inv = new SimpleInventory(3);
		$inv->setItem(0, $sword);
		$inv->setItem(1, $dirt);

		$tx = new AnvilTransaction($player, $anvil, clone $sword, clone $book, null, []);
		$result = $tx->getResult();

		// Client consumes worthless dirt instead of the required enchanted book
		$tx->addAction(new SlotChangeAction($inv, 0, $sword, VanillaItems::AIR()));
		$tx->addAction(new SlotChangeAction($inv, 1, $dirt, VanillaItems::AIR()));
		$tx->addAction(new SlotChangeAction($inv, 2, VanillaItems::AIR(), $result));

		$this->expectException(TransactionValidationException::class);
		$tx->validate();
	}

	public function testValidateRejectsUnauthorizedOutputEnchantment() : void{
		$player = $this->createMockPlayer();
		$anvil = VanillaBlocks::ANVIL();
		$sword = VanillaItems::DIAMOND_SWORD();
		$book = self::book();

		$inv = new SimpleInventory(3);
		$inv->setItem(0, $sword);
		$inv->setItem(1, $book);

		$tx = new AnvilTransaction($player, $anvil, clone $sword, clone $book, null, []);
		// Output with an uncalculated extra enchantment
		$tamperedResult = (clone $tx->getResult())->addEnchantment(new EnchantmentInstance(VanillaEnchantments::UNBREAKING(), 3));

		$tx->addAction(new SlotChangeAction($inv, 0, $sword, VanillaItems::AIR()));
		$tx->addAction(new SlotChangeAction($inv, 1, $book, VanillaItems::AIR()));
		$tx->addAction(new SlotChangeAction($inv, 2, VanillaItems::AIR(), $tamperedResult));

		$this->expectException(TransactionValidationException::class);
		$tx->validate();
	}

	public function testValidateLegitimateEnchantingPasses() : void{
		$player = $this->createMockPlayer();
		$anvil = VanillaBlocks::ANVIL();
		$sword = VanillaItems::DIAMOND_SWORD();
		$book = self::book();

		$inv = new SimpleInventory(3);
		$inv->setItem(0, $sword);
		$inv->setItem(1, $book);

		$tx = new AnvilTransaction($player, $anvil, clone $sword, clone $book, null, []);
		$result = $tx->getResult();

		$tx->addAction(new SlotChangeAction($inv, 0, $sword, VanillaItems::AIR()));
		$tx->addAction(new SlotChangeAction($inv, 1, $book, VanillaItems::AIR()));
		$tx->addAction(new SlotChangeAction($inv, 2, VanillaItems::AIR(), $result));

		$tx->validate();
		self::assertTrue($result->hasEnchantment(VanillaEnchantments::SHARPNESS()));
		self::assertSame(5, $result->getEnchantmentLevel(VanillaEnchantments::SHARPNESS()));
	}

	public function testValidateLegitimateRepairConsumingTwoDiamondsPasses() : void{
		$player = $this->createMockPlayer();
		$anvil = VanillaBlocks::ANVIL();
		$sword = VanillaItems::DIAMOND_SWORD();
		// Set damage to exactly 2 diamond repair units (25% max durability repaired per diamond)
		$sword->setDamage((int) floor($sword->getMaxDurability() / 4) * 2);
		$diamonds = VanillaItems::DIAMOND()->setCount(5);

		$inv = new SimpleInventory(3);
		$inv->setItem(0, $sword);
		$inv->setItem(1, $diamonds);

		$tx = new AnvilTransaction($player, $anvil, clone $sword, clone $diamonds, null, []);
		$result = $tx->getResult();

		// Two diamonds consumed, leaving 3 diamonds in the slot
		$remainingDiamonds = VanillaItems::DIAMOND()->setCount(3);
		$tx->addAction(new SlotChangeAction($inv, 0, $sword, VanillaItems::AIR()));
		$tx->addAction(new SlotChangeAction($inv, 1, $diamonds, $remainingDiamonds));
		$tx->addAction(new SlotChangeAction($inv, 2, VanillaItems::AIR(), $result));

		$tx->validate();
		self::assertInstanceOf(\pocketmine\item\Durable::class, $result);
		self::assertSame(0, $result->getDamage());
	}

	public function testValidateLegitimateRenamingStackOf64Passes() : void{
		$player = $this->createMockPlayer();
		$anvil = VanillaBlocks::ANVIL();
		$dirt = VanillaBlocks::DIRT()->asItem()->setCount(64);
		$rename = "Custom Dirt";

		$inv = new SimpleInventory(3);
		$inv->setItem(0, $dirt);

		$tx = new AnvilTransaction($player, $anvil, $dirt, VanillaItems::AIR(), $rename, []);
		$result = $tx->getResult();

		$tx->addAction(new SlotChangeAction($inv, 0, $dirt, VanillaItems::AIR()));
		$tx->addAction(new SlotChangeAction($inv, 2, VanillaItems::AIR(), $result));

		$tx->validate();
		self::assertSame($rename, $result->getCustomName());
		self::assertSame(64, $result->getCount());
	}
}
