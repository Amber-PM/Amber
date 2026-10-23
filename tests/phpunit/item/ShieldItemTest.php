<?php

declare(strict_types=1);

namespace pocketmine\item;

use PHPUnit\Framework\TestCase;

class ShieldItemTest extends TestCase{

	public function testShieldProperties() : void{
		$shield = VanillaItems::SHIELD();
		self::assertInstanceOf(Shield::class, $shield);
		self::assertSame(336, $shield->getMaxDurability());
		self::assertSame(1, $shield->getMaxStackSize());
		self::assertSame(300, $shield->getFuelTime());
		self::assertSame(100, $shield->getCooldownTicks());
		self::assertSame(ItemCooldownTags::SHIELD, $shield->getCooldownTag());
	}

	public function testShieldDurabilityDamage() : void{
		$shield = VanillaItems::SHIELD();
		self::assertSame(0, $shield->getDamage());
		self::assertTrue($shield->applyDamage(10));
		self::assertSame(10, $shield->getDamage());
		self::assertFalse($shield->isBroken());

		$shield->applyDamage(325);
		self::assertSame(335, $shield->getDamage());
		self::assertFalse($shield->isBroken());

		$shield->applyDamage(1);
		self::assertTrue($shield->isBroken());
		self::assertTrue($shield->isNull());
	}

	public function testStringToItemParser() : void{
		$item = StringToItemParser::getInstance()->parse("shield");
		self::assertInstanceOf(Shield::class, $item);

		$itemBedrock = StringToItemParser::getInstance()->parse("minecraft:shield");
		self::assertInstanceOf(Shield::class, $itemBedrock);
	}
}
