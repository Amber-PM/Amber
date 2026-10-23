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

namespace pocketmine\loot;

use PHPUnit\Framework\TestCase;
use pocketmine\entity\Entity;
use pocketmine\item\VanillaItems;
use pocketmine\player\Player;
use pocketmine\world\Position;
use pocketmine\world\World;
use ReflectionProperty;

final class LootTableParserTest extends TestCase{

	private function createTestContext(?Player $player = null, int $looting = 0) : LootContext{
		$world = $this->createMock(World::class);
		$world->method('isLoaded')->willReturn(true);
		return new LootContext(
			position: new Position(0, 64, 0, $world),
			killer: $player,
			lootingLevel: $looting
		);
	}

	private function createPlayerMock() : Player{
		$player = $this->createMock(Player::class);
		(new ReflectionProperty(Entity::class, "closed"))->setValue($player, true);
		(new ReflectionProperty(Player::class, "logger"))->setValue($player, $this->createMock(\Logger::class));
		return $player;
	}

	public function testSimpleDungeonLootGeneratesItems() : void{
		$table = LootTableRegistry::getInstance()->get("chests/simple_dungeon");
		self::assertNotNull($table);

		$context = $this->createTestContext();
		$results = $table->roll($context);
		self::assertNotEmpty($results);

		foreach($results as $item){
			self::assertFalse($item->isNull());
			self::assertGreaterThan(0, $item->getCount());
		}
	}

	public function testZombieLootTableRespectsPlayerKillCondition() : void{
		$table = LootTableRegistry::getInstance()->get("entities/zombie");
		self::assertNotNull($table);

		// Non-player kill: cannot drop player-only pools
		$nonPlayerContext = $this->createTestContext(player: null);
		for($i = 0; $i < 20; ++$i){
			$drops = $table->roll($nonPlayerContext);
			foreach($drops as $item){
				// Should only drop rotten flesh
				self::assertSame(VanillaItems::ROTTEN_FLESH()->getTypeId(), $item->getTypeId());
			}
		}

		// Player kill with high looting: rare pool condition passes
		$player = $this->createPlayerMock();
		$playerContext = $this->createTestContext(player: $player, looting: 100);
		$gotRare = false;
		for($i = 0; $i < 50; ++$i){
			$drops = $table->roll($playerContext);
			foreach($drops as $item){
				$id = $item->getTypeId();
				if(
					$id === VanillaItems::IRON_INGOT()->getTypeId() ||
					$id === VanillaItems::CARROT()->getTypeId() ||
					$id === VanillaItems::POTATO()->getTypeId()
				){
					$gotRare = true;
					break 2;
				}
			}
		}
		self::assertTrue($gotRare, "Player kill with Looting should roll rare drops");
	}

	public function testLootingEnchantIncreasesYield() : void{
		$func = new LootingEnchantFunction(1, 2);
		$item = VanillaItems::ROTTEN_FLESH();
		$item->setCount(1);

		$contextZero = $this->createTestContext(looting: 0);
		$itemZero = $func->apply(clone $item, $contextZero);
		self::assertSame(1, $itemZero->getCount());

		$contextLooting = $this->createTestContext(looting: 3);
		$itemLooting = $func->apply(clone $item, $contextLooting);
		self::assertGreaterThan(1, $itemLooting->getCount());
	}

	public function testSetCountFunctionExactAndRange() : void{
		$funcExact = new SetCountFunction(5, 5);
		$item = VanillaItems::DIAMOND();
		$funcExact->apply($item, $this->createTestContext());
		self::assertSame(5, $item->getCount());

		$funcRange = new SetCountFunction(2, 4);
		$itemRange = VanillaItems::DIAMOND();
		$funcRange->apply($itemRange, $this->createTestContext());
		self::assertGreaterThanOrEqual(2, $itemRange->getCount());
		self::assertLessThanOrEqual(4, $itemRange->getCount());
	}

	public function testRandomChanceWithLootingCondition() : void{
		$cond = new RandomChanceWithLootingCondition(chance: 0.0, lootingMultiplier: 1.0);
		$ctx0 = $this->createTestContext(looting: 0);
		self::assertFalse($cond->test($ctx0));

		$ctx1 = $this->createTestContext(looting: 1);
		self::assertTrue($cond->test($ctx1));
	}
}
