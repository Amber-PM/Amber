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

namespace pocketmine\entity;

use PHPUnit\Framework\TestCase;
use pocketmine\entity\villager\TradeOffer;
use pocketmine\entity\villager\VillagerProfession;
use pocketmine\entity\villager\VillagerTradeTier;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\player\Player;
use pocketmine\world\World;

final class VillagerTradingTest extends TestCase{

	private function createVillager() : Villager{
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);
		$world->method("getFolderName")->willReturn("test_world");

		$location = new Location(0, 64, 0, $world, 0, 0);
		return new Villager($location, CompoundTag::create());
	}

	public function testLevelProgression() : void{
		$villager = $this->createVillager();
		$villager->setProfession(VillagerProfession::FARMER);
		$villager->setTradeTier(VillagerTradeTier::NOVICE);

		self::assertSame(VillagerTradeTier::NOVICE, $villager->getTradeTier());
		self::assertSame(0, $villager->getTradeExperience());

		// 9 XP: Still Novice
		$villager->addExperience(9);
		self::assertSame(VillagerTradeTier::NOVICE, $villager->getTradeTier());

		// +1 XP (10 total): Levels to Apprentice
		$villager->addExperience(1);
		self::assertSame(VillagerTradeTier::APPRENTICE, $villager->getTradeTier());

		// +60 XP (70 total): Levels to Journeyman
		$villager->addExperience(60);
		self::assertSame(VillagerTradeTier::JOURNEYMAN, $villager->getTradeTier());

		// +80 XP (150 total): Levels to Expert
		$villager->addExperience(80);
		self::assertSame(VillagerTradeTier::EXPERT, $villager->getTradeTier());

		// +100 XP (250 total): Levels to Master
		$villager->addExperience(100);
		self::assertSame(VillagerTradeTier::MASTER, $villager->getTradeTier());
	}

	public function testResupplyTrades() : void{
		$villager = $this->createVillager();
		$offer1 = new TradeOffer(VanillaItems::WHEAT()->setCount(20), null, VanillaItems::EMERALD(), uses: 16);
		$offer2 = new TradeOffer(VanillaItems::POTATO()->setCount(26), null, VanillaItems::EMERALD(), uses: 8);

		$villager->setTradeOffers([$offer1, $offer2]);

		self::assertTrue($offer1->isSoldOut());
		self::assertSame(16, $offer1->getUses());
		self::assertSame(8, $offer2->getUses());

		$villager->resupplyTrades();

		self::assertFalse($offer1->isSoldOut());
		self::assertSame(0, $offer1->getUses());
		self::assertSame(0, $offer2->getUses());
	}

	public function testWorkstationClaiming() : void{
		$villager = $this->createVillager();
		$pos = new Vector3(10, 64, 20);

		self::assertSame(VillagerProfession::NONE, $villager->getProfession());
		self::assertNull($villager->getWorkstationPosition());

		self::assertTrue($villager->claimWorkstation($pos, VillagerProfession::CLERIC));
		self::assertSame(VillagerProfession::CLERIC, $villager->getProfession());
		self::assertEquals($pos, $villager->getWorkstationPosition());
		self::assertNotEmpty($villager->getTradeOffers());

		$villager->releaseWorkstation();
		self::assertNull($villager->getWorkstationPosition());
	}

	public function testUpdateTradePacketGeneration() : void{
		$villager = $this->createVillager();
		$villager->setProfession(VillagerProfession::LIBRARIAN);
		$villager->setTradeTier(VillagerTradeTier::APPRENTICE);

		$offer = new TradeOffer(
			VanillaItems::EMERALD()->setCount(24),
			VanillaItems::BOOK()->setCount(1),
			VanillaItems::ENCHANTED_BOOK(),
			maxUses: 12,
			tier: 2
		);
		$villager->setTradeOffers([$offer]);

		$player = $this->getMockBuilder(Player::class)
			->disableOriginalConstructor()
			->onlyMethods(["getId", "onDispose"])
			->getMock();
		$player->method("getId")->willReturn(12345);
		$player->method("onDispose")->willReturnCallback(function() : void{});
		(new \ReflectionProperty(Entity::class, "closed"))->setValue($player, true);
		(new \ReflectionProperty(Player::class, "logger"))->setValue($player, $this->createMock(\Logger::class));

		$pk = $villager->createUpdateTradePacket(1, $player);

		self::assertSame(1, $pk->windowId);
		self::assertSame(VillagerTradeTier::APPRENTICE, $pk->tradeTier);
		self::assertSame($villager->getId(), $pk->traderActorUniqueId);
		self::assertSame(12345, $pk->playerActorUniqueId);
		self::assertSame("Librarian", $pk->displayName);
		self::assertTrue($pk->isV2Trading);
		self::assertTrue($pk->isEconomyTrading);

		$tag = $pk->offers->getRoot();
		self::assertNotNull($tag->getTag("Recipes"));
		self::assertNotNull($tag->getTag("TierExpRequirements"));
	}

	public function testNbtSaveAndLoad() : void{
		$villager = $this->createVillager();
		$villager->setProfession(VillagerProfession::WEAPON_SMITH);
		$villager->setTradeTier(VillagerTradeTier::JOURNEYMAN);
		$villager->addExperience(75);

		$offer = new TradeOffer(VanillaItems::COAL()->setCount(15), null, VanillaItems::EMERALD(), uses: 5);
		$villager->setTradeOffers([$offer]);

		$nbt = $villager->saveNBT();

		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);
		$world->method("getFolderName")->willReturn("test_world");

		$loaded = new Villager(new Location(0, 64, 0, $world, 0, 0), $nbt);

		self::assertSame(VillagerProfession::WEAPON_SMITH, $loaded->getProfession());
		self::assertSame(VillagerTradeTier::JOURNEYMAN, $loaded->getTradeTier());
		self::assertSame(75, $loaded->getTradeExperience());
		self::assertCount(1, $loaded->getTradeOffers());
		self::assertSame(5, $loaded->getTradeOffers()[0]->getUses());
	}
}
