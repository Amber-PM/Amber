<?php

declare(strict_types=1);

namespace pocketmine\item;

use PHPUnit\Framework\TestCase;
use pocketmine\data\bedrock\item\ItemTypeNames;
use pocketmine\inventory\ArmorInventory;
use pocketmine\item\enchantment\ItemEnchantmentTags;
use pocketmine\world\format\io\GlobalItemDataHandlers;

class ElytraItemTest extends TestCase{

	public function testElytraProperties() : void{
		$elytra = VanillaItems::ELYTRA();
		self::assertInstanceOf(Elytra::class, $elytra);
		self::assertSame(432, $elytra->getMaxDurability());
		self::assertSame(ArmorInventory::SLOT_CHEST, $elytra->getArmorSlot());
		self::assertSame(0, $elytra->getDefensePoints());
		self::assertSame(1, $elytra->getMaxStackSize());
		self::assertContains(ItemEnchantmentTags::ELYTRA, $elytra->getEnchantmentTags());
		self::assertFalse($elytra->isBroken());
	}

	public function testDurabilityDegradationAndBrokenState() : void{
		$elytra = VanillaItems::ELYTRA();

		// Apply partial damage
		self::assertTrue($elytra->applyDamage(200));
		self::assertSame(200, $elytra->getDamage());
		self::assertFalse($elytra->isBroken());

		// Damage up to 431 (1 durability remaining)
		self::assertTrue($elytra->applyDamage(231));
		self::assertSame(431, $elytra->getDamage());
		self::assertTrue($elytra->isBroken());
		self::assertFalse($elytra->isNull());
		self::assertSame(1, $elytra->getCount());

		// Attempting further damage must return false and remain at 431 without disappearing
		self::assertFalse($elytra->applyDamage(50));
		self::assertSame(431, $elytra->getDamage());
		self::assertTrue($elytra->isBroken());
		self::assertFalse($elytra->isNull());
		self::assertSame(1, $elytra->getCount());
	}

	public function testStringToItemParser() : void{
		$parsed = StringToItemParser::getInstance()->parse("elytra");
		self::assertInstanceOf(Elytra::class, $parsed);
	}

	public function testItemSerializerDeserializer() : void{
		$serializer = GlobalItemDataHandlers::getSerializer();
		$deserializer = GlobalItemDataHandlers::getDeserializer();

		$elytra = VanillaItems::ELYTRA();
		$itemData = $serializer->serializeType($elytra);
		self::assertSame(ItemTypeNames::ELYTRA, $itemData->getName());

		$deserialized = $deserializer->deserializeType($itemData);
		self::assertInstanceOf(Elytra::class, $deserialized);
	}
}
