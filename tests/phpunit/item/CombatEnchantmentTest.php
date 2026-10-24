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
 * it under the terms of the GNU Lesser COmmercial Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author AmberPM Team
 * @link https://github.com/Amber-PM/Amber
 *
 *
 */

declare(strict_types=1);

namespace pocketmine\item;

use PHPUnit\Framework\TestCase;
use pocketmine\item\enchantment\DensityEnchantment;
use pocketmine\item\enchantment\BreachEnchantment;
use pocketmine\item\enchantment\WindBurstEnchantment;
use pocketmine\item\enchantment\ImpalingEnchantment;
use pocketmine\item\enchantment\IncompatibleEnchantmentRegistry;
use pocketmine\item\enchantment\Rarity;
use pocketmine\item\enchantment\StringToEnchantmentParser;
use pocketmine\item\enchantment\VanillaEnchantments;

final class CombatEnchantmentTest extends TestCase{

	public function testTridentEnchantmentsProperties() : void{
		$loyalty = VanillaEnchantments::LOYALTY();
		self::assertSame(3, $loyalty->getMaxLevel());
		self::assertSame(Rarity::UNCOMMON, $loyalty->getRarity());

		$riptide = VanillaEnchantments::RIPTIDE();
		self::assertSame(3, $riptide->getMaxLevel());
		self::assertSame(Rarity::RARE, $riptide->getRarity());

		$channeling = VanillaEnchantments::CHANNELING();
		self::assertSame(1, $channeling->getMaxLevel());
		self::assertSame(Rarity::MYTHIC, $channeling->getRarity());

		$impaling = VanillaEnchantments::IMPALING();
		self::assertSame(5, $impaling->getMaxLevel());
		self::assertSame(Rarity::RARE, $impaling->getRarity());
		self::assertEquals(12.5, $impaling->getDamageBonus(5));
	}

	public function testMaceEnchantmentsProperties() : void{
		$density = VanillaEnchantments::DENSITY();
		self::assertSame(5, $density->getMaxLevel());
		self::assertSame(Rarity::COMMON, $density->getRarity());
		self::assertEquals(2.5, $density->getSmashBonusPerBlock(5));

		$breach = VanillaEnchantments::BREACH();
		self::assertSame(4, $breach->getMaxLevel());
		self::assertSame(Rarity::RARE, $breach->getRarity());
		self::assertEqualsWithDelta(0.40, $breach->getArmorReductionMultiplier(4), 0.01);

		$windBurst = VanillaEnchantments::WIND_BURST();
		self::assertSame(3, $windBurst->getMaxLevel());
		self::assertSame(Rarity::MYTHIC, $windBurst->getRarity());
		self::assertEquals(21.0, $windBurst->getBounceBlocks(3));
	}

	public function testIncompatibilities() : void{
		$registry = IncompatibleEnchantmentRegistry::getInstance();

		self::assertFalse($registry->areCompatible(VanillaEnchantments::RIPTIDE(), VanillaEnchantments::LOYALTY()));
		self::assertFalse($registry->areCompatible(VanillaEnchantments::RIPTIDE(), VanillaEnchantments::CHANNELING()));
		self::assertTrue($registry->areCompatible(VanillaEnchantments::LOYALTY(), VanillaEnchantments::CHANNELING()));

		self::assertFalse($registry->areCompatible(VanillaEnchantments::DENSITY(), VanillaEnchantments::BREACH()));
	}

	public function testStringToEnchantmentParser() : void{
		$parser = StringToEnchantmentParser::getInstance();

		self::assertSame(VanillaEnchantments::LOYALTY(), $parser->parse("loyalty"));
		self::assertSame(VanillaEnchantments::RIPTIDE(), $parser->parse("riptide"));
		self::assertSame(VanillaEnchantments::CHANNELING(), $parser->parse("channeling"));
		self::assertSame(VanillaEnchantments::IMPALING(), $parser->parse("impaling"));
		self::assertSame(VanillaEnchantments::DENSITY(), $parser->parse("density"));
		self::assertSame(VanillaEnchantments::BREACH(), $parser->parse("breach"));
		self::assertSame(VanillaEnchantments::WIND_BURST(), $parser->parse("wind_burst"));
	}
}
