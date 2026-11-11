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
use pocketmine\entity\Entity;
use pocketmine\entity\ExperienceManager;
use pocketmine\entity\Location;
use pocketmine\entity\villager\TradeOffer;
use pocketmine\entity\Villager;
use pocketmine\item\ItemTypeIds;
use pocketmine\item\VanillaItems;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\player\Player;
use pocketmine\world\World;
use ReflectionProperty;

final class TradeTransactionTest extends TestCase{

	private function createMockPlayer() : Player{
		$player = $this->getMockBuilder(Player::class)
			->disableOriginalConstructor()
			->onlyMethods(["getXpManager", "isCreative", "onDispose"])
			->getMock();

		$xpManager = $this->createMock(ExperienceManager::class);
		$xpManager->method("getXpLevel")->willReturn(10);
		$player->method("getXpManager")->willReturn($xpManager);
		$player->method("isCreative")->willReturn(false);
		$player->method("onDispose")->willReturnCallback(function() : void{});

		(new ReflectionProperty(Entity::class, "closed"))->setValue($player, true);
		(new ReflectionProperty(Player::class, "logger"))->setValue($player, $this->createMock(Logger::class));

		return $player;
	}

	private function createMockVillager() : Villager{
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);
		$world->method("getFolderName")->willReturn("test_world");

		$location = new Location(0, 64, 0, $world, 0, 0);
		return new Villager($location, CompoundTag::create());
	}

	public function testValidSingleItemTrade() : void{
		$player = $this->createMockPlayer();
		$villager = $this->createMockVillager();

		$buyA = VanillaItems::WHEAT()->setCount(20);
		$sell = VanillaItems::EMERALD()->setCount(1);
		$offer = new TradeOffer($buyA, null, $sell, maxUses: 16, uses: 0, traderExp: 2);

		$inputA = VanillaItems::WHEAT()->setCount(20);

		$tx = new TradeTransaction($player, $villager, $offer, $inputA);
		$result = $tx->getResult();

		self::assertSame(ItemTypeIds::EMERALD, $result->getTypeId());
		self::assertSame(1, $result->getCount());
		self::assertCount(1, $tx->getConsumed());
		self::assertSame(20, $tx->getConsumed()[0]->getCount());
	}

	public function testValidTwoItemTrade() : void{
		$player = $this->createMockPlayer();
		$villager = $this->createMockVillager();

		$buyA = VanillaItems::EMERALD()->setCount(24);
		$buyB = VanillaItems::BOOK()->setCount(1);
		$sell = VanillaItems::ENCHANTED_BOOK();
		$offer = new TradeOffer($buyA, $buyB, $sell, maxUses: 12, uses: 0, traderExp: 1);

		$inputA = VanillaItems::EMERALD()->setCount(24);
		$inputB = VanillaItems::BOOK()->setCount(1);

		$tx = new TradeTransaction($player, $villager, $offer, $inputA, $inputB);
		$result = $tx->getResult();

		self::assertSame(ItemTypeIds::ENCHANTED_BOOK, $result->getTypeId());
		self::assertCount(2, $tx->getConsumed());
	}

	public function testSoldOutTradeThrows() : void{
		$player = $this->createMockPlayer();
		$villager = $this->createMockVillager();

		$buyA = VanillaItems::WHEAT()->setCount(20);
		$sell = VanillaItems::EMERALD()->setCount(1);
		$offer = new TradeOffer($buyA, null, $sell, maxUses: 16, uses: 16);

		$this->expectException(TransactionValidationException::class);
		new TradeTransaction($player, $villager, $offer, VanillaItems::WHEAT()->setCount(20));
	}

	public function testInsufficientPrimaryInputThrows() : void{
		$player = $this->createMockPlayer();
		$villager = $this->createMockVillager();

		$buyA = VanillaItems::WHEAT()->setCount(20);
		$sell = VanillaItems::EMERALD()->setCount(1);
		$offer = new TradeOffer($buyA, null, $sell);

		$this->expectException(TransactionValidationException::class);
		new TradeTransaction($player, $villager, $offer, VanillaItems::WHEAT()->setCount(19));
	}

	public function testMissingSecondaryInputThrows() : void{
		$player = $this->createMockPlayer();
		$villager = $this->createMockVillager();

		$buyA = VanillaItems::EMERALD()->setCount(24);
		$buyB = VanillaItems::BOOK()->setCount(1);
		$sell = VanillaItems::ENCHANTED_BOOK();
		$offer = new TradeOffer($buyA, $buyB, $sell);

		$this->expectException(TransactionValidationException::class);
		new TradeTransaction($player, $villager, $offer, VanillaItems::EMERALD()->setCount(24), null);
	}
}
