<?php

declare(strict_types=1);

namespace pocketmine\item;

use PHPUnit\Framework\TestCase;
use pocketmine\block\utils\BannerPatternLayer;
use pocketmine\block\utils\BannerPatternType;
use pocketmine\block\utils\DyeColor;

class ShieldBannerTest extends TestCase{

	public function testDefaultValues() : void{
		$shield = VanillaItems::SHIELD();
		self::assertNull($shield->getBaseColor());
		self::assertEmpty($shield->getPatterns());
	}

	public function testSettersAndGetters() : void{
		$shield = VanillaItems::SHIELD();
		$layer1 = new BannerPatternLayer(BannerPatternType::SKULL, DyeColor::RED);
		$layer2 = new BannerPatternLayer(BannerPatternType::CREEPER, DyeColor::LIME);

		$shield->setBaseColor(DyeColor::WHITE);
		$shield->setPatterns([$layer1, $layer2]);

		self::assertSame(DyeColor::WHITE, $shield->getBaseColor());
		self::assertCount(2, $shield->getPatterns());
		self::assertSame(BannerPatternType::SKULL, $shield->getPatterns()[0]->getType());
		self::assertSame(DyeColor::RED, $shield->getPatterns()[0]->getColor());
		self::assertSame(BannerPatternType::CREEPER, $shield->getPatterns()[1]->getType());
		self::assertSame(DyeColor::LIME, $shield->getPatterns()[1]->getColor());
	}

	public function testBannerPatternPersistenceRoundtrip() : void{
		$shield = VanillaItems::SHIELD();
		$pattern = new BannerPatternLayer(BannerPatternType::SKULL, DyeColor::RED);
		$shield->setBaseColor(DyeColor::WHITE);
		$shield->setPatterns([$pattern]);

		$nbt = $shield->nbtSerialize();
		$deserialized = Item::nbtDeserialize($nbt);

		self::assertInstanceOf(Shield::class, $deserialized);
		self::assertSame(DyeColor::WHITE, $deserialized->getBaseColor());
		self::assertCount(1, $deserialized->getPatterns());
		self::assertSame(BannerPatternType::SKULL, $deserialized->getPatterns()[0]->getType());
		self::assertSame(DyeColor::RED, $deserialized->getPatterns()[0]->getColor());
	}

	public function testEmptyShieldHasNoBlockEntityTag() : void{
		$shield = VanillaItems::SHIELD();
		$nbt = $shield->nbtSerialize();
		$tag = $nbt->getCompoundTag("tag");
		self::assertTrue($tag === null || $tag->getCompoundTag("BlockEntityTag") === null);
	}
}
