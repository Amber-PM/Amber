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

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use pocketmine\block\tile\Chest;
use pocketmine\block\tile\Dispenser;
use pocketmine\block\tile\Dropper;
use pocketmine\block\tile\Hopper;
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\Entity;
use pocketmine\inventory\Inventory;
use pocketmine\item\enchantment\VanillaEnchantments;
use pocketmine\item\Item;
use pocketmine\item\ItemTypeIds;
use pocketmine\item\Potion;
use pocketmine\item\PotionType;
use pocketmine\item\SplashPotion;
use pocketmine\item\VanillaItems;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\LongTag;
use pocketmine\player\Player;
use pocketmine\world\hopper\ContainerTransfer;
use pocketmine\world\hopper\ContainerTransferPolicy;
use pocketmine\world\hopper\HopperTransfer;
use pocketmine\world\Position;
use pocketmine\world\World;
use ReflectionProperty;
use function count;
use function ksort;

/**
 * Regressions for review findings on the data-driven loot engine.
 */
final class LootRegressionTest extends TestCase{

	private LootTableRegistry $previous;

	protected function setUp() : void{
		$this->previous = LootTableRegistry::getInstance();
		LootTableRegistry::setInstance(new LootTableRegistry());
		$registry = LootTableRegistry::getInstance();
		$registry->register("test/one_apple", $registry->parseTable("test/one_apple", [
			"pools" => [["rolls" => 1, "entries" => [["type" => "item", "name" => "minecraft:apple"]]]]
		]));
	}

	protected function tearDown() : void{
		LootTableRegistry::setInstance($this->previous);
	}

	private function createMockWorld() : World&MockObject{
		$world = $this->createMock(World::class);
		$world->method('isLoaded')->willReturn(true);
		$world->method('getBlockAt')->willReturn(VanillaBlocks::AIR());
		return $world;
	}

	private function context() : LootContext{
		return new LootContext(position: new Position(0, 64, 0, null));
	}

	/**
	 * @param array<string, mixed> $entry
	 */
	private function rollEntry(array $entry) : array{
		$registry = LootTableRegistry::getInstance();
		return $registry->parseTable("test/entry", ["pools" => [["rolls" => 1, "entries" => [$entry]]]])->roll($this->context());
	}

	/**
	 * @param Item[] $items
	 */
	private static function countOfType(array $items, Item $type) : int{
		$total = 0;
		foreach($items as $item){
			if($item->getTypeId() === $type->getTypeId()){
				$total += $item->getCount();
			}
		}
		return $total;
	}

	public function testChestLootDoesNotOverwriteInsertedItems() : void{
		$transfer = new ContainerTransfer(new ContainerTransferPolicy());
		for($run = 0; $run < 30; ++$run){
			$world = $this->createMockWorld();
			$tile = new Chest($world, new Vector3(0, 64, 0));
			$world->method('getTile')->willReturn($tile);
			$tile->readSaveData(CompoundTag::create()->setString("LootTable", "loot_tables/test/one_apple.json"));
			for($i = 0; $i < 5; ++$i){
				self::assertTrue($transfer->insertOne($tile, VanillaItems::DIAMOND()->setCustomName("stored-$i"), Facing::WEST));
			}

			$seen = [];
			$player = $this->createMock(Player::class);
			(new ReflectionProperty(Entity::class, "closed"))->setValue($player, true);
			(new ReflectionProperty(Player::class, "logger"))->setValue($player, $this->createMock(\Logger::class));
			$player->method('setCurrentWindow')->willReturnCallback(function(Inventory $inventory) use (&$seen) : bool{
				$seen = $inventory->getContents();
				return true;
			});

			$block = VanillaBlocks::CHEST();
			$block->position($world, 0, 64, 0);
			$returned = [];
			$block->onInteract(VanillaItems::AIR(), Facing::UP, new Vector3(0.5, 0.5, 0.5), $player, $returned);

			self::assertSame(5, self::countOfType($seen, VanillaItems::DIAMOND()));
			self::assertSame(1, self::countOfType($seen, VanillaItems::APPLE()));
			$tile->closed = true;
		}
	}

	public function testFullInventoryDiscardsOverflowLoot() : void{
		$world = $this->createMockWorld();
		$tile = new Chest($world, new Vector3(0, 64, 0));
		$inventory = $tile->getRealInventory();
		for($slot = 0; $slot < $inventory->getSize(); ++$slot){
			$inventory->setItem($slot, VanillaItems::DIAMOND());
		}
		LootTableRegistry::getInstance()->get("test/one_apple")?->fillInventory($inventory, $this->context());
		self::assertSame($inventory->getSize(), self::countOfType($inventory->getContents(), VanillaItems::DIAMOND()));
		$tile->closed = true;
	}

	public function testDispenserDispenseGeneratesPendingLoot() : void{
		$world = $this->createMockWorld();
		$tile = new Dispenser($world, new Vector3(0, 64, 0));
		$world->method('getTile')->willReturn($tile);
		$tile->readSaveData(CompoundTag::create()->setString("LootTable", "test/one_apple"));

		$block = VanillaBlocks::DISPENSER();
		$block->position($world, 0, 64, 0);
		$block->dispense();

		self::assertNull($tile->getLootTable());
		$tile->closed = true;
	}

	public function testDropperDropGeneratesPendingLoot() : void{
		$world = $this->createMockWorld();
		$tile = new Dropper($world, new Vector3(0, 64, 0));
		$world->method('getTile')->willReturn($tile);
		$tile->readSaveData(CompoundTag::create()->setString("LootTable", "test/one_apple"));

		$block = VanillaBlocks::DROPPER();
		$block->position($world, 0, 64, 0);
		$block->drop();

		self::assertNull($tile->getLootTable());
		$tile->closed = true;
	}

	public function testPendingLootTablePersistsUntilGenerated() : void{
		$world = $this->createMockWorld();
		$tile = new Chest($world, new Vector3(0, 64, 0));
		$tile->setLootTable("test/one_apple", 1234);
		$saved = $tile->saveNBT();
		self::assertSame("test/one_apple", $saved->getString("LootTable"));
		self::assertSame(1234, $saved->getInt("LootTableSeed"));
		$tile->closed = true;

		$reloaded = new Chest($world, new Vector3(0, 64, 0));
		$reloaded->readSaveData($saved);
		self::assertSame("test/one_apple", $reloaded->getLootTable());
		$reloaded->generateLoot();
		self::assertCount(1, $reloaded->getRealInventory()->getContents());
		self::assertNull($reloaded->saveNBT()->getTag("LootTable"));
		$reloaded->closed = true;
	}

	public function testLootTableSeedOutsideInt32RoundTrips() : void{
		$world = $this->createMockWorld();
		foreach([
			[CompoundTag::create()->setLong("LootTableSeed", 5_000_000_000), LongTag::class, 5_000_000_000],
			[CompoundTag::create()->setLong("LootTableSeed", -5_000_000_000), LongTag::class, -5_000_000_000],
			[CompoundTag::create()->setLong("LootTableSeed", 42), IntTag::class, 42],
			[CompoundTag::create()->setInt("LootTableSeed", -1234), IntTag::class, -1234]
		] as [$nbt, $expectedTag, $expectedSeed]){
			$tile = new Chest($world, new Vector3(0, 64, 0));
			$tile->readSaveData($nbt->setString("LootTable", "test/one_apple"));
			$seedTag = $tile->saveNBT()->getTag("LootTableSeed");
			self::assertInstanceOf($expectedTag, $seedTag);
			self::assertSame($expectedSeed, $seedTag->getValue());

			$reloaded = new Chest($world, new Vector3(0, 64, 0));
			$reloaded->readSaveData($tile->saveNBT());
			self::assertSame($expectedSeed, $reloaded->saveNBT()->getTag("LootTableSeed")?->getValue());
			$tile->closed = true;
			$reloaded->closed = true;
		}
	}

	public function testItemsWithoutParserAliasResolve() : void{
		self::assertCount(1, $this->rollEntry(["type" => "item", "name" => "minecraft:music_disc_13"]));
		self::assertSame(VanillaItems::FIRE_CHARGE()->getTypeId(), $this->rollEntry(["type" => "item", "name" => "minecraft:fireball"])[0]->getTypeId());
		self::assertCount(1, $this->rollEntry(["type" => "item", "name" => "minecraft:lingering_potion"]));
		self::assertCount(0, $this->rollEntry(["type" => "item", "name" => "minecraft:definitely_not_an_item"]));
	}

	public function testSpecificEnchantsStringList() : void{
		$item = $this->rollEntry(["type" => "item", "name" => "minecraft:stick", "functions" => [
			["function" => "specific_enchants", "enchants" => ["knockback", "fire_aspect"]]
		]])[0];
		self::assertSame(1, $item->getEnchantmentLevel(VanillaEnchantments::KNOCKBACK()));
		self::assertSame(1, $item->getEnchantmentLevel(VanillaEnchantments::FIRE_ASPECT()));
	}

	public function testSpecificEnchantsBookRangeFromVanillaAncientCity() : void{
		$entry = ["type" => "item", "name" => "minecraft:book", "functions" => [
			["function" => "specific_enchants", "enchants" => [["id" => "swift_sneak", "level" => [1, 3]]]]
		]];
		$levels = [];
		for($i = 0; $i < 300; ++$i){
			$item = $this->rollEntry($entry)[0];
			self::assertSame(ItemTypeIds::ENCHANTED_BOOK, $item->getTypeId());
			$levels[$item->getEnchantmentLevel(VanillaEnchantments::SWIFT_SNEAK())] = true;
		}
		ksort($levels);
		self::assertSame([1 => true, 2 => true, 3 => true], $levels, "levels 1-3 must all occur");
	}

	public function testSpecificEnchantsLevelIsCappedAtMax() : void{
		$item = $this->rollEntry(["type" => "item", "name" => "minecraft:iron_sword", "functions" => [
			["function" => "specific_enchants", "enchants" => [["id" => "sharpness", "level" => 50]]]
		]])[0];
		self::assertSame(VanillaEnchantments::SHARPNESS()->getMaxLevel(), $item->getEnchantmentLevel(VanillaEnchantments::SHARPNESS()));
	}

	public function testSetPotion() : void{
		$potion = $this->rollEntry(["type" => "item", "name" => "minecraft:potion", "functions" => [["function" => "set_potion", "id" => "water_breathing"]]])[0];
		self::assertInstanceOf(Potion::class, $potion);
		self::assertSame(PotionType::WATER_BREATHING, $potion->getType());

		$nightVision = $this->rollEntry(["type" => "item", "name" => "minecraft:potion", "functions" => [["function" => "set_potion", "id" => "nightvision"]]])[0];
		self::assertSame(PotionType::NIGHT_VISION, $nightVision->getType());

		$splash = $this->rollEntry(["type" => "item", "name" => "minecraft:splash_potion", "functions" => [["function" => "set_potion", "id" => "poison"]]])[0];
		self::assertInstanceOf(SplashPotion::class, $splash);
		self::assertSame(PotionType::POISON, $splash->getType());

		$lingering = $this->rollEntry(["type" => "item", "name" => "minecraft:lingering_potion", "functions" => [["function" => "set_potion", "id" => "slow_falling"]]])[0];
		self::assertSame(PotionType::SLOW_FALLING, $lingering->getType());

		self::assertCount(0, $this->rollEntry(["type" => "item", "name" => "minecraft:potion", "functions" => [["function" => "set_potion", "id" => "oozing"]]]), "unsupported potion must not become water");
		self::assertCount(0, $this->rollEntry(["type" => "item", "name" => "minecraft:splash_potion", "functions" => [
			["function" => "set_potion", "id" => "wind_charged"],
			["function" => "set_count", "count" => ["min" => 2, "max" => 5]]
		]]), "set_count after an unsupported potion must not revive it");

		$arrow = $this->rollEntry(["type" => "item", "name" => "minecraft:arrow", "functions" => [["function" => "set_potion", "id" => "poison"]]]);
		self::assertSame(VanillaItems::ARROW()->getTypeId(), $arrow[0]->getTypeId());
	}

	public function testEnchantRandomGearHonoursChance() : void{
		$never = ["type" => "item", "name" => "minecraft:iron_sword", "functions" => [["function" => "enchant_random_gear", "chance" => 0]]];
		$always = ["type" => "item", "name" => "minecraft:iron_sword", "functions" => [["function" => "enchant_random_gear", "chance" => 2.0]]];
		for($i = 0; $i < 100; ++$i){
			self::assertFalse($this->rollEntry($never)[0]->hasEnchantments());
			self::assertTrue($this->rollEntry($always)[0]->hasEnchantments());
		}
	}

	public function testEnchantedLootBookKeepsNameAndLore() : void{
		foreach([
			["function" => "specific_enchants", "enchants" => ["unbreaking"]],
			["function" => "enchant_randomly"],
			["function" => "enchant_with_levels", "levels" => 30, "treasure" => false]
		] as $enchantFunction){
			$item = $this->rollEntry(["type" => "item", "name" => "minecraft:book", "functions" => [
				["function" => "set_name", "name" => "Named"],
				["function" => "set_lore", "lore" => ["line"]],
				$enchantFunction
			]])[0];
			self::assertSame(ItemTypeIds::ENCHANTED_BOOK, $item->getTypeId());
			self::assertSame("Named", $item->getCustomName());
			self::assertSame(["line"], $item->getLore());
		}
	}

	public function testHopperPullUnpacksPendingLoot() : void{
		$world = $this->createMockWorld();
		$chest = new Chest($world, new Vector3(0, 65, 0));
		$chest->readSaveData(CompoundTag::create()->setString("LootTable", "test/one_apple"));
		$hopper = new Hopper($world, new Vector3(0, 64, 0));
		$world->method('isInWorld')->willReturn(true);
		$world->method('isChunkLoaded')->willReturn(true);
		$world->method('getTileAt')->willReturnCallback(fn(int $x, int $y, int $z) => $y === 65 ? $chest : $hopper);

		$pull = new \ReflectionMethod(HopperTransfer::class, 'pullFromAbove');
		$result = $pull->invoke(new HopperTransfer($world, new ContainerTransferPolicy()), $hopper->getInventory(), 0, 64, 0);

		self::assertSame(1, $result->moved);
		self::assertNull($chest->getLootTable());
		self::assertSame(1, self::countOfType($hopper->getInventory()->getContents(), VanillaItems::APPLE()));
		$chest->closed = true;
		$hopper->closed = true;
	}

	public function testShippedAncientCityTableStillParses() : void{
		$table = LootTableRegistry::getInstance()->get("chests/ancient_city");
		self::assertNotNull($table);
		for($i = 0; $i < 50; ++$i){
			foreach($table->roll($this->context()) as $item){
				self::assertFalse($item->isNull());
			}
		}
		self::assertGreaterThan(0, count($table->pools));
	}
}
