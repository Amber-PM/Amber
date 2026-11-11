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

namespace pocketmine\entity\villager;

use PHPUnit\Framework\TestCase;
use pocketmine\item\VanillaItems;

final class TradeOfferTest extends TestCase{

	public function testBasicPropertiesAndSoldOut() : void{
		$buyA = VanillaItems::WHEAT()->setCount(20);
		$sell = VanillaItems::EMERALD()->setCount(1);

		$offer = new TradeOffer($buyA, null, $sell, maxUses: 16, uses: 0, traderExp: 2, rewardExp: true, tier: 1);

		self::assertFalse($offer->isSoldOut());
		self::assertSame(0, $offer->getUses());
		self::assertSame(16, $offer->getMaxUses());
		self::assertSame(2, $offer->getTraderExp());
		self::assertTrue($offer->rewardsExp());
		self::assertSame(1, $offer->getTier());

		$offer->incrementUses(15);
		self::assertSame(15, $offer->getUses());
		self::assertFalse($offer->isSoldOut());

		$offer->incrementUses(1);
		self::assertSame(16, $offer->getUses());
		self::assertTrue($offer->isSoldOut());

		$offer->resetUses();
		self::assertSame(0, $offer->getUses());
		self::assertFalse($offer->isSoldOut());
	}

	public function testMatchesInputsSingle() : void{
		$buyA = VanillaItems::WHEAT()->setCount(20);
		$sell = VanillaItems::EMERALD()->setCount(1);

		$offer = new TradeOffer($buyA, null, $sell);

		// Matching exact
		self::assertTrue($offer->matchesInputs(VanillaItems::WHEAT()->setCount(20), VanillaItems::AIR()));
		// Matching surplus
		self::assertTrue($offer->matchesInputs(VanillaItems::WHEAT()->setCount(64), VanillaItems::AIR()));
		// Insufficient
		self::assertFalse($offer->matchesInputs(VanillaItems::WHEAT()->setCount(19), VanillaItems::AIR()));
		// Wrong item
		self::assertFalse($offer->matchesInputs(VanillaItems::CARROT()->setCount(20), VanillaItems::AIR()));
	}

	public function testMatchesInputsDouble() : void{
		$buyA = VanillaItems::EMERALD()->setCount(24);
		$buyB = VanillaItems::BOOK()->setCount(1);
		$sell = VanillaItems::ENCHANTED_BOOK();

		$offer = new TradeOffer($buyA, $buyB, $sell);

		self::assertTrue($offer->matchesInputs(
			VanillaItems::EMERALD()->setCount(24),
			VanillaItems::BOOK()->setCount(1)
		));
		self::assertTrue($offer->matchesInputs(
			VanillaItems::EMERALD()->setCount(64),
			VanillaItems::BOOK()->setCount(16)
		));
		// Missing buyB
		self::assertFalse($offer->matchesInputs(
			VanillaItems::EMERALD()->setCount(24),
			VanillaItems::AIR()
		));
		// Insufficient buyA
		self::assertFalse($offer->matchesInputs(
			VanillaItems::EMERALD()->setCount(23),
			VanillaItems::BOOK()->setCount(1)
		));
	}

	public function testNbtSerializationRoundtrip() : void{
		$buyA = VanillaItems::EMERALD()->setCount(12);
		$buyB = VanillaItems::BOOK()->setCount(1);
		$sell = VanillaItems::ENCHANTED_BOOK();

		$offer = new TradeOffer(
			$buyA,
			$buyB,
			$sell,
			maxUses: 12,
			uses: 3,
			traderExp: 4,
			rewardExp: true,
			tier: 2,
			priceMultiplierA: 0.1,
			priceMultiplierB: 0.2,
			demand: 5
		);

		$tag = $offer->toNbt();
		$deserialized = TradeOffer::fromNbt($tag);

		self::assertSame($offer->getBuyA()->getTypeId(), $deserialized->getBuyA()->getTypeId());
		self::assertSame(12, $deserialized->getBuyA()->getCount());
		self::assertNotNull($deserialized->getBuyB());
		self::assertSame($offer->getBuyB()->getTypeId(), $deserialized->getBuyB()->getTypeId());
		self::assertSame(1, $deserialized->getBuyB()->getCount());
		self::assertSame($offer->getSell()->getTypeId(), $deserialized->getSell()->getTypeId());
		self::assertSame(12, $deserialized->getMaxUses());
		self::assertSame(3, $deserialized->getUses());
		self::assertSame(4, $deserialized->getTraderExp());
		self::assertTrue($deserialized->rewardsExp());
		self::assertSame(2, $deserialized->getTier());
		self::assertEqualsWithDelta(0.1, $deserialized->getPriceMultiplierA(), 0.001);
		self::assertEqualsWithDelta(0.2, $deserialized->getPriceMultiplierB(), 0.001);
		self::assertSame(5, $deserialized->getDemand());
	}
}
