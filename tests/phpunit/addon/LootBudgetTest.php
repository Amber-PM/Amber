<?php
declare(strict_types=1);

namespace pocketmine\addon;

use PHPUnit\Framework\TestCase;
use pocketmine\addon\loot\LootContext;
use pocketmine\addon\loot\LootTables;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\enchantment\VanillaEnchantments;
use pocketmine\item\Item;
use pocketmine\item\ItemIdentifier;
use ReflectionProperty;

final class LootBudgetTest extends TestCase{
	/** @var list<string> */
	private array $files = [];
	private string $root;

	protected function setUp() : void{
		$this->root = sys_get_temp_dir() . "/amber-loot-" . bin2hex(random_bytes(8));
		mkdir($this->root);
	}

	protected function tearDown() : void{
		foreach($this->files as $file){
			unlink($file);
		}
		rmdir($this->root);
	}

	private function table(string $name, array $pools) : void{
		$file = $this->root . "/$name.json";
		file_put_contents($file, json_encode(["pools" => $pools], JSON_THROW_ON_ERROR));
		$this->files[] = $file;
	}

	private function tables() : LootTables{
		$tables = new LootTables([$this->root], $this->createMock(\Logger::class));
		(new ReflectionProperty(LootTables::class, "items"))->setValue($tables, ["minecraft:stone#0" => new Item(new ItemIdentifier(100))]);
		return $tables;
	}

	public function testOrdinaryAndBoundaryRollsProduceExpectedStacks() : void{
		$this->table("normal", [["rolls" => 2, "entries" => [["type" => "item", "name" => "minecraft:stone"]]]]);
		$this->table("boundary", [["rolls" => 4096, "entries" => [["type" => "item", "name" => "minecraft:stone"]]]]);
		self::assertCount(2, $this->tables()->roll("normal", new LootContext()));
		self::assertCount(4096, $this->tables()->roll("boundary", new LootContext()));
	}

	public function testExtremeRollsAreRejected() : void{
		$this->table("extreme", [["rolls" => 1000000000, "entries" => [["type" => "item", "name" => "minecraft:stone"]]]]);
		self::assertSame([], $this->tables()->roll("extreme", new LootContext()));
	}

	public function testNestedTablesShareRollAndOutputBudgets() : void{
		$this->table("child", [["rolls" => 4096, "entries" => [["type" => "item", "name" => "minecraft:stone"]]]]);
		$this->table("parent", [["rolls" => 2, "entries" => [["type" => "loot_table", "name" => "child"]]]]);
		self::assertCount(4095, $this->tables()->roll("parent", new LootContext()));
	}

	public function testSetCountCannotProduceMoreThanStackBudget() : void{
		$this->table("stacks", [["rolls" => 1, "entries" => [["type" => "item", "name" => "minecraft:stone", "functions" => [["function" => "set_count", "count" => 1000000000]]]]]]);
		self::assertCount(4096, $this->tables()->roll("stacks", new LootContext()));
	}

	public function testEmptyPoolsConsumeTheSharedScanBudget() : void{
		$pools = array_fill(0, 4096, ["rolls" => 1, "entries" => []]);
		$pools[] = ["rolls" => 1, "entries" => [["type" => "item", "name" => "minecraft:stone"]]];
		$this->table("many_pools", $pools);
		self::assertSame([], $this->tables()->roll("many_pools", new LootContext()));
	}

	public function testUnusableEntriesConsumeTheSharedScanBudget() : void{
		$entries = array_fill(0, 16384, null);
		$entries[] = ["type" => "item", "name" => "minecraft:stone"];
		$this->table("many_entries", [["rolls" => 1, "entries" => $entries]]);
		self::assertSame([], $this->tables()->roll("many_entries", new LootContext()));
	}

	public function testWeightedSelectionAlsoConsumesEntryBudget() : void{
		$this->table("many_choices", [
			["rolls" => 40, "entries" => array_fill(0, 1000, ["type" => "empty"])],
			["rolls" => 1, "entries" => [["type" => "item", "name" => "minecraft:stone"]]],
		]);
		mt_srand(1234);
		try{
			self::assertSame([], $this->tables()->roll("many_choices", new LootContext()));
		}finally{
			mt_srand();
		}
	}

	public function testExtremelyLargeEntryWeightsCannotOverflowThePoolTotal() : void{
		$this->table("oversized_weights", [["rolls" => 1, "entries" => [
			["type" => "item", "name" => "minecraft:stone", "weight" => 5000000000000000000],
			["type" => "item", "name" => "minecraft:stone", "weight" => 5000000000000000000],
		]]]);
		self::assertSame([], $this->tables()->roll("oversized_weights", new LootContext()));
	}

	public function testOrdinaryWeightedEntryAndMaximumAcceptedWeightStillRoll() : void{
		$this->table("weighted", [["rolls" => 1, "entries" => [
			["type" => "empty", "weight" => 0],
			["type" => "item", "name" => "minecraft:stone", "weight" => 3],
		]]]);
		$this->table("weight_boundary", [["rolls" => 1, "entries" => [
			["type" => "item", "name" => "minecraft:stone", "weight" => 1000000],
		]]]);
		$tables = $this->tables();
		self::assertCount(1, $tables->roll("weighted", new LootContext()));
		self::assertCount(1, $tables->roll("weight_boundary", new LootContext()));
	}

	public function testOversizedQualityBonusIsSkippedBeforeIntegerConversion() : void{
		$this->table("quality_overflow", [["rolls" => 1, "entries" => [
			["type" => "item", "name" => "minecraft:stone", "weight" => 1, "quality" => 5000000000000000000],
		]]]);
		$tool = new Item(new ItemIdentifier(101));
		$tool->addEnchantment(new EnchantmentInstance(VanillaEnchantments::FORTUNE(), 1));
		self::assertSame([], $this->tables()->roll("quality_overflow", new LootContext(tool: $tool)));
	}
}
