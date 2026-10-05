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

namespace pocketmine\item;

use PHPUnit\Framework\TestCase;
use pocketmine\data\bedrock\EnchantmentIdMap;
use pocketmine\data\bedrock\EnchantmentIds;
use pocketmine\item\enchantment\AvailableEnchantmentRegistry;
use pocketmine\item\enchantment\IncompatibleEnchantMap;
use pocketmine\item\enchantment\ItemEnchantmentTags;
use pocketmine\item\enchantment\Rarity;
use pocketmine\item\enchantment\VanillaEnchantments;
use pocketmine\lang\KnownTranslationKeys;
use pocketmine\lang\Translatable;

final class CrossbowEnchantmentTest extends TestCase{

	public function testMultishotEnchantmentProperties() : void{
		$multishot = VanillaEnchantments::MULTISHOT();
		self::assertSame(1, $multishot->getMaxLevel());
		self::assertSame(Rarity::RARE, $multishot->getRarity());

		$name = $multishot->getName();
		self::assertInstanceOf(Translatable::class, $name);
		self::assertSame(KnownTranslationKeys::ENCHANTMENT_CROSSBOWMULTISHOT, $name->getText());

		$idMap = EnchantmentIdMap::getInstance();
		self::assertSame(EnchantmentIds::MULTISHOT, $idMap->toId($multishot));
		self::assertSame($multishot, $idMap->fromId(EnchantmentIds::MULTISHOT));
	}

	public function testPiercingEnchantmentProperties() : void{
		$piercing = VanillaEnchantments::PIERCING();
		self::assertSame(4, $piercing->getMaxLevel());
		self::assertSame(Rarity::COMMON, $piercing->getRarity());

		$name = $piercing->getName();
		self::assertInstanceOf(Translatable::class, $name);
		self::assertSame(KnownTranslationKeys::ENCHANTMENT_CROSSBOWPIERCING, $name->getText());

		$idMap = EnchantmentIdMap::getInstance();
		self::assertSame(EnchantmentIds::PIERCING, $idMap->toId($piercing));
		self::assertSame($piercing, $idMap->fromId(EnchantmentIds::PIERCING));
	}

	public function testQuickChargeEnchantmentProperties() : void{
		$quickCharge = VanillaEnchantments::QUICK_CHARGE();
		self::assertSame(3, $quickCharge->getMaxLevel());
		self::assertSame(Rarity::UNCOMMON, $quickCharge->getRarity());

		$name = $quickCharge->getName();
		self::assertInstanceOf(Translatable::class, $name);
		self::assertSame(KnownTranslationKeys::ENCHANTMENT_CROSSBOWQUICKCHARGE, $name->getText());

		$idMap = EnchantmentIdMap::getInstance();
		self::assertSame(EnchantmentIds::QUICK_CHARGE, $idMap->toId($quickCharge));
		self::assertSame($quickCharge, $idMap->fromId(EnchantmentIds::QUICK_CHARGE));
	}

	public function testEnchantmentIncompatibility() : void{
		$multishot = VanillaEnchantments::MULTISHOT();
		$piercing = VanillaEnchantments::PIERCING();
		$quickCharge = VanillaEnchantments::QUICK_CHARGE();

		self::assertTrue(IncompatibleEnchantMap::isIncompatible($multishot, $piercing));
		self::assertTrue(IncompatibleEnchantMap::isIncompatible($piercing, $multishot));

		self::assertFalse(IncompatibleEnchantMap::isIncompatible($quickCharge, $multishot));
		self::assertFalse(IncompatibleEnchantMap::isIncompatible($quickCharge, $piercing));
	}

	public function testAvailableEnchantmentRegistryPrimaryTags() : void{
		$registry = AvailableEnchantmentRegistry::getInstance();

		$multishotPrimary = $registry->getPrimaryItemTags(VanillaEnchantments::MULTISHOT());
		self::assertContains(ItemEnchantmentTags::CROSSBOW, $multishotPrimary);

		$piercingPrimary = $registry->getPrimaryItemTags(VanillaEnchantments::PIERCING());
		self::assertContains(ItemEnchantmentTags::CROSSBOW, $piercingPrimary);

		$quickChargePrimary = $registry->getPrimaryItemTags(VanillaEnchantments::QUICK_CHARGE());
		self::assertContains(ItemEnchantmentTags::CROSSBOW, $quickChargePrimary);
	}
}
