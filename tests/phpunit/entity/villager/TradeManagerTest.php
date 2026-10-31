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
use pocketmine\math\Vector3;
use pocketmine\world\World;

final class TradeManagerTest extends TestCase{

	public function testLoadFarmerTrades() : void{
		$manager = TradeManager::getInstance();
		$trades = $manager->generateTradesForTier(VillagerProfession::FARMER, VillagerTradeTier::NOVICE);

		self::assertNotEmpty($trades);
		foreach($trades as $trade){
			self::assertSame(VillagerTradeTier::NOVICE, $trade->getTier());
			self::assertGreaterThan(0, $trade->getBuyA()->getCount());
			self::assertGreaterThan(0, $trade->getSell()->getCount());
		}
	}

	public function testLoadLibrarianTrades() : void{
		$manager = TradeManager::getInstance();
		$trades = $manager->generateTradesForTier(VillagerProfession::LIBRARIAN, VillagerTradeTier::NOVICE);

		self::assertNotEmpty($trades);
		foreach($trades as $trade){
			self::assertSame(VillagerTradeTier::NOVICE, $trade->getTier());
			self::assertGreaterThan(0, $trade->getBuyA()->getCount());
			self::assertGreaterThan(0, $trade->getSell()->getCount());
		}
	}

	public function testGenerateAllTradesUpToMaster() : void{
		$manager = TradeManager::getInstance();
		$trades = $manager->generateAllTradesUpToTier(VillagerProfession::ARMORER, VillagerTradeTier::MASTER);

		self::assertNotEmpty($trades);
		$tiers = [];
		foreach($trades as $trade){
			$tiers[$trade->getTier()] = true;
		}

		self::assertArrayHasKey(VillagerTradeTier::NOVICE, $tiers);
		self::assertArrayHasKey(VillagerTradeTier::MASTER, $tiers);
	}

	public function testWanderingTraderTrades() : void{
		$manager = TradeManager::getInstance();
		$trades = $manager->generateWanderingTraderTrades();

		self::assertNotEmpty($trades);
		foreach($trades as $trade){
			self::assertSame(1, $trade->getTier());
			self::assertGreaterThan(0, $trade->getBuyA()->getCount());
			self::assertGreaterThan(0, $trade->getSell()->getCount());
		}
	}

	public function testWorkstationClaimingAndRelease() : void{
		$manager = TradeManager::getInstance();
		$world = $this->createMock(World::class);
		$world->method("getFolderName")->willReturn("test_world");

		$pos = new Vector3(100, 64, 200);

		// Initially unclaimed
		self::assertFalse($manager->isWorkstationClaimed($world, $pos));

		// Claim by entity 1
		self::assertTrue($manager->claimWorkstation($world, $pos, 1));
		self::assertTrue($manager->isWorkstationClaimed($world, $pos));

		// Entity 1 claiming again succeeds (already owns it)
		self::assertTrue($manager->claimWorkstation($world, $pos, 1));

		// Entity 2 claiming fails
		self::assertFalse($manager->claimWorkstation($world, $pos, 2));

		// Release workstation
		$manager->releaseWorkstation($world, $pos);
		self::assertFalse($manager->isWorkstationClaimed($world, $pos));

		// Now entity 2 can claim
		self::assertTrue($manager->claimWorkstation($world, $pos, 2));
		self::assertTrue($manager->isWorkstationClaimed($world, $pos));

		// Release all for entity 2
		$manager->releaseAllForEntity(2);
		self::assertFalse($manager->isWorkstationClaimed($world, $pos));
	}
}
