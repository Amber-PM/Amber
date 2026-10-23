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
use pocketmine\data\runtime\RuntimeDataDescriber;
use pocketmine\data\runtime\RuntimeDataReader;
use pocketmine\data\runtime\RuntimeDataSizeCalculator;
use pocketmine\data\runtime\RuntimeDataWriter;
use pocketmine\entity\effect\VanillaEffects;

final class ArrowTest extends TestCase{

	public function testDefaultArrow() : void{
		$arrow = VanillaItems::ARROW();
		self::assertNull($arrow->getTipType());
		self::assertSame([], $arrow->getPotionEffects());
	}

	public function testSetTipType() : void{
		$arrow = VanillaItems::ARROW();
		$arrow->setTipType(PotionType::SWIFTNESS);
		self::assertSame(PotionType::SWIFTNESS, $arrow->getTipType());

		$effects = $arrow->getPotionEffects();
		self::assertCount(1, $effects);
		self::assertSame(VanillaEffects::SPEED(), $effects[0]->getType());

		$arrow->setTipType(null);
		self::assertNull($arrow->getTipType());
		self::assertSame([], $arrow->getPotionEffects());
	}

	public function testEquality() : void{
		$plain1 = VanillaItems::ARROW();
		$plain2 = VanillaItems::ARROW();
		$swift = VanillaItems::ARROW()->setTipType(PotionType::SWIFTNESS);
		$swift2 = VanillaItems::ARROW()->setTipType(PotionType::SWIFTNESS);
		$slowness = VanillaItems::ARROW()->setTipType(PotionType::SLOWNESS);

		self::assertTrue($plain1->equalsExact($plain2));
		self::assertFalse($plain1->equalsExact($swift));
		self::assertTrue($swift->equalsExact($swift2));
		self::assertFalse($swift->equalsExact($slowness));
	}

	public function testRuntimeDataRoundtrip() : void{
		$describeState = new \ReflectionMethod(Arrow::class, "describeState");

		foreach(PotionType::cases() as $potionType){
			$arrow = VanillaItems::ARROW()->setTipType($potionType);

			$sizeCalculator = new RuntimeDataSizeCalculator();
			$describeState->invoke($arrow, $sizeCalculator);
			$bitsUsed = $sizeCalculator->getBitsUsed();

			$writer = new RuntimeDataWriter($bitsUsed);
			$describeState->invoke($arrow, $writer);

			$newArrow = VanillaItems::ARROW();
			$reader = new RuntimeDataReader($bitsUsed, $writer->getValue());
			$describeState->invoke($newArrow, $reader);

			self::assertSame($potionType, $newArrow->getTipType());
			self::assertTrue($arrow->equalsExact($newArrow));
		}

		$plainArrow = VanillaItems::ARROW();
		$sizeCalculator = new RuntimeDataSizeCalculator();
		$describeState->invoke($plainArrow, $sizeCalculator);
		$bitsUsed = $sizeCalculator->getBitsUsed();

		$writer = new RuntimeDataWriter($bitsUsed);
		$describeState->invoke($plainArrow, $writer);

		$newArrow = VanillaItems::ARROW();
		$reader = new RuntimeDataReader($bitsUsed, $writer->getValue());
		$describeState->invoke($newArrow, $reader);

		self::assertNull($newArrow->getTipType());
		self::assertTrue($plainArrow->equalsExact($newArrow));
	}

	public function testStringToItemParserAliases() : void{
		$parser = StringToItemParser::getInstance();

		$swiftnessArrow = $parser->parse("swiftness_arrow");
		self::assertInstanceOf(Arrow::class, $swiftnessArrow);
		self::assertSame(PotionType::SWIFTNESS, $swiftnessArrow->getTipType());

		$tippedSwiftness = $parser->parse("tipped_arrow_swiftness");
		self::assertInstanceOf(Arrow::class, $tippedSwiftness);
		self::assertSame(PotionType::SWIFTNESS, $tippedSwiftness->getTipType());

		$poisonArrow = $parser->parse("poison_arrow");
		self::assertInstanceOf(Arrow::class, $poisonArrow);
		self::assertSame(PotionType::POISON, $poisonArrow->getTipType());

		$waterArrow = $parser->parse("water_arrow");
		self::assertInstanceOf(Arrow::class, $waterArrow);
		self::assertSame(PotionType::WATER, $waterArrow->getTipType());

		$harmingArrow = $parser->parse("harming_arrow");
		self::assertInstanceOf(Arrow::class, $harmingArrow);
		self::assertSame(PotionType::HARMING, $harmingArrow->getTipType());

		$healingArrow = $parser->parse("healing_arrow");
		self::assertInstanceOf(Arrow::class, $healingArrow);
		self::assertSame(PotionType::HEALING, $healingArrow->getTipType());

		$longSwiftness = $parser->parse("long_swiftness_arrow");
		self::assertInstanceOf(Arrow::class, $longSwiftness);
		self::assertSame(PotionType::LONG_SWIFTNESS, $longSwiftness->getTipType());

		$strongSlowness = $parser->parse("strong_slowness_arrow");
		self::assertInstanceOf(Arrow::class, $strongSlowness);
		self::assertSame(PotionType::STRONG_SLOWNESS, $strongSlowness->getTipType());
	}
}
