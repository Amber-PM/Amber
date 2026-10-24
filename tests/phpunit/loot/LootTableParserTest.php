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

namespace pmmp\encoding {
	if(!class_exists(BE::class, false)){
		class BE{
			public static function unpackSignedLong(string $bytes) : int{
				return (int) unpack('J', $bytes)[1];
			}
		}
		class LE{
			public static function packSignedLong(int $val) : string{
				return pack('P', $val);
			}
		}
	}
}

namespace pocketmine\loot {

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

	public function testRegistrationAndRetrievalNormalization() : void{
		$reg = LootTableRegistry::getInstance();
		$table = new LootTable("test/example", []);
		$reg->register("loot_tables/test/example.json", $table);

		self::assertTrue($reg->has("loot_tables/test/example.json"));
		self::assertTrue($reg->has("test/example"));
		self::assertSame($table, $reg->get("loot_tables/test/example.json"));
		self::assertSame($table, $reg->get("test/example"));
	}

	public function testUnsupportedConditionRejectsPool() : void{
		$reg = LootTableRegistry::getInstance();
		$json = [
			"pools" => [
				[
					"rolls" => 1,
					"conditions" => [
						["condition" => "passenger_of_entity"]
					],
					"entries" => [
						["type" => "item", "name" => "minecraft:diamond"]
					]
				]
			]
		];

		$reg->register("test/unsupported_cond", $reg->parseTable("test/unsupported_cond", $json));
		$table = $reg->get("test/unsupported_cond");
		self::assertNotNull($table);
		self::assertSame([], $table->roll($this->createTestContext()));
	}

	public function testUnsupportedItemSkippedRatherThanCrashing() : void{
		$entry = new LootEntry("item", "minecraft:completely_unregistered_mystery_item_xyz", 1, 0.0, [], []);
		$items = $entry->roll($this->createTestContext());
		self::assertSame([], $items);
	}

	public function testSetDataFunctionResolvesWoolAndSuspiciousStewVariants() : void{
		$woolFunc = new SetDataFunction(14); // Red
		$woolItem = \pocketmine\block\VanillaBlocks::WOOL()->asItem();
		$resolvedWool = $woolFunc->apply($woolItem, $this->createTestContext());
		self::assertInstanceOf(\pocketmine\item\ItemBlock::class, $resolvedWool);
		$woolBlock = $resolvedWool->getBlock();
		self::assertInstanceOf(\pocketmine\block\Wool::class, $woolBlock);
		self::assertSame(\pocketmine\block\utils\DyeColor::RED, $woolBlock->getColor());

		$stewFunc = new SetDataFunction(3);
		$stewItem = \pocketmine\item\VanillaItems::SUSPICIOUS_STEW();
		$resolvedStew = $stewFunc->apply($stewItem, $this->createTestContext());
		self::assertInstanceOf(\pocketmine\item\SuspiciousStew::class, $resolvedStew);
		self::assertSame(\pocketmine\item\SuspiciousStewType::AZURE_BLUET, $resolvedStew->getType());
	}

	public function testEnchantRandomlyConvertsBookToEnchantedBookAndFiltersCompat() : void{
		$func = new EnchantRandomlyFunction();
		$book = \pocketmine\item\VanillaItems::BOOK();
		$enchanted = $func->apply($book, $this->createTestContext());
		self::assertSame(\pocketmine\item\ItemTypeIds::ENCHANTED_BOOK, $enchanted->getTypeId());
		self::assertTrue($enchanted->hasEnchantments());

		$sword = \pocketmine\item\VanillaItems::IRON_SWORD();
		for($i = 0; $i < 10; ++$i){
			$enchantedSword = $func->apply(clone $sword, $this->createTestContext());
			foreach($enchantedSword->getEnchantments() as $ench){
				self::assertNotSame(\pocketmine\item\enchantment\VanillaEnchantments::POWER(), $ench->getType());
				self::assertNotSame(\pocketmine\item\enchantment\VanillaEnchantments::PROTECTION(), $ench->getType());
			}
		}
	}

	public function testEnchantWithLevelsExcludesTreasureWhenDisabled() : void{
		$func = new EnchantWithLevelsFunction(minLevels: 30, maxLevels: 30, treasure: false);
		$book = \pocketmine\item\VanillaItems::BOOK();
		for($i = 0; $i < 20; ++$i){
			$res = $func->apply(clone $book, $this->createTestContext());
			foreach($res->getEnchantments() as $ench){
				self::assertNotSame(\pocketmine\item\enchantment\VanillaEnchantments::MENDING(), $ench->getType());
			}
		}
	}

	public function testConditionalFunctionRespectsCondition() : void{
		$reg = LootTableRegistry::getInstance();
		$json = [
			"pools" => [
				[
					"rolls" => 1,
					"entries" => [
						[
							"type" => "item",
							"name" => "minecraft:diamond",
							"functions" => [
								[
									"function" => "set_count",
									"count" => 9,
									"conditions" => [
										["condition" => "random_chance", "chance" => 0.0]
									]
								]
							]
						]
					]
				]
			]
		];

		$reg->register("test/cond_func", $reg->parseTable("test/cond_func", $json));
		$table = $reg->get("test/cond_func");
		self::assertNotNull($table);
		$items = $table->roll($this->createTestContext());
		self::assertCount(1, $items);
		self::assertSame(1, $items[0]->getCount(), "Count should remain 1 when function condition fails");
	}

	public function testSetNameAndLoreFunctions() : void{
		$reg = LootTableRegistry::getInstance();
		$json = [
			"pools" => [
				[
					"rolls" => 1,
					"entries" => [
						[
							"type" => "item",
							"name" => "minecraft:apple",
							"functions" => [
								[
									"function" => "set_name",
									"name" => "Golden Core Apple"
								],
								[
									"function" => "set_lore",
									"lore" => ["Legendary Item", "Restores vitality"]
								]
							]
						]
					]
				]
			]
		];

		$reg->register("test/name_lore", $reg->parseTable("test/name_lore", $json));
		$table = $reg->get("test/name_lore");
		self::assertNotNull($table);
		$items = $table->roll($this->createTestContext());
		self::assertCount(1, $items);
		self::assertSame("Golden Core Apple", $items[0]->getCustomName());
		self::assertSame(["Legendary Item", "Restores vitality"], $items[0]->getLore());
	}

	public function testFurnaceSmeltFunctionTransformsRawItems() : void{
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);

		$victim = $this->createMock(Entity::class);
		$victim->method("isOnFire")->willReturn(true);
		$victim->method("getPosition")->willReturn(new Position(0, 64, 0, $world));
		$victim->method("getLocation")->willReturn(new \pocketmine\entity\Location(0, 64, 0, $world, 0.0, 0.0));

		$context = new LootContext(
			position: new Position(0, 64, 0, $world),
			victim: $victim
		);

		$func = new FurnaceSmeltFunction();
		$rawBeef = VanillaItems::RAW_BEEF()->setCount(3);
		$cookedBeef = $func->apply($rawBeef, $context);

		self::assertSame(VanillaItems::STEAK()->getTypeId(), $cookedBeef->getTypeId());
		self::assertSame(3, $cookedBeef->getCount());
	}
}
}
