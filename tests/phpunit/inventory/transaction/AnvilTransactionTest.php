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

use PHPUnit\Framework\TestCase;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\enchantment\VanillaEnchantments;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;

final class AnvilTransactionTest extends TestCase{

	/**
	 * @param Item[] $deleted
	 * @param Item[] $expected
	 */
	private static function assertConsumed(array $deleted, array $expected) : void{
		$method = new \ReflectionMethod(AnvilTransaction::class, "assertConsumed");
		$method->invoke(null, $deleted, $expected);
	}

	private static function book() : Item{
		return VanillaItems::ENCHANTED_BOOK()->addEnchantment(new EnchantmentInstance(VanillaEnchantments::SHARPNESS(), 5));
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
}
