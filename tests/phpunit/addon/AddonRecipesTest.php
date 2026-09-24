<?php

declare(strict_types=1);

namespace pocketmine\addon;

use PHPUnit\Framework\TestCase;
use pocketmine\crafting\CraftingManager;
use function count;

final class AddonRecipesTest extends TestCase{

	private CraftingManager $craftingManager;
	private AddonRecipes $recipes;

	protected function setUp() : void{
		$this->craftingManager = new CraftingManager();
		$this->recipes = new AddonRecipes($this->craftingManager);
	}

	public function testEmptyShapedRowRejectsAndReportsError() : void{
		$recipe = [
			"minecraft:recipe_shaped" => [
				"description" => ["identifier" => "test:empty_row"],
				"tags" => ["crafting_table"],
				"pattern" => ["#", ""],
				"key" => [
					"#" => ["item" => "minecraft:stick"]
				],
				"result" => ["item" => "minecraft:torch"]
			]
		];

		$this->recipes->register($recipe, "test:empty_row");
		self::assertSame(0, $this->recipes->getRegisteredCount());
		self::assertCount(1, $this->recipes->getErrors());
		self::assertStringContainsString("shaped recipe rows must be strings of 1 to 3 characters", $this->recipes->getErrors()[0]);
	}

	public function testZeroWidthPatternRejectsAndReportsError() : void{
		$recipe = [
			"minecraft:recipe_shaped" => [
				"description" => ["identifier" => "test:zero_width"],
				"tags" => ["crafting_table"],
				"pattern" => [""],
				"key" => [],
				"result" => ["item" => "minecraft:torch"]
			]
		];

		$this->recipes->register($recipe, "test:zero_width");
		self::assertSame(0, $this->recipes->getRegisteredCount());
		self::assertCount(1, $this->recipes->getErrors());
		self::assertStringContainsString("shaped recipe rows must be strings of 1 to 3 characters", $this->recipes->getErrors()[0]);
	}

	public function testValidShapedRecipeRegisters() : void{
		$recipe = [
			"minecraft:recipe_shaped" => [
				"description" => ["identifier" => "test:valid_stick_to_torch"],
				"tags" => ["crafting_table"],
				"pattern" => ["#"],
				"key" => [
					"#" => ["item" => "minecraft:stick"]
				],
				"result" => ["item" => "minecraft:torch"]
			]
		];

		$this->recipes->register($recipe, "test:valid_stick_to_torch");
		self::assertSame(1, $this->recipes->getRegisteredCount());
		self::assertSame([], $this->recipes->getErrors());
		self::assertGreaterThanOrEqual(1, count($this->craftingManager->getShapedRecipes()));
	}

	public function testMalformedRecipeDoesNotEscapeUncaughtException() : void{
		$malformedInputs = [
			"zero_width_single" => [
				"minecraft:recipe_shaped" => [
					"tags" => ["crafting_table"],
					"pattern" => [""],
					"key" => [],
					"result" => ["item" => "minecraft:torch"]
				]
			],
			"empty_second_row" => [
				"minecraft:recipe_shaped" => [
					"tags" => ["crafting_table"],
					"pattern" => ["#", ""],
					"key" => ["#" => ["item" => "minecraft:stick"]],
					"result" => ["item" => "minecraft:torch"]
				]
			],
			"missing_key_mapping" => [
				"minecraft:recipe_shaped" => [
					"tags" => ["crafting_table"],
					"pattern" => ["X"],
					"key" => [],
					"result" => ["item" => "minecraft:torch"]
				]
			],
			"empty_pattern_array" => [
				"minecraft:recipe_shaped" => [
					"tags" => ["crafting_table"],
					"pattern" => [],
					"result" => ["item" => "minecraft:torch"]
				]
			]
		];

		foreach($malformedInputs as $source => $input){
			$this->recipes->register($input, $source);
		}

		self::assertSame(0, $this->recipes->getRegisteredCount());
		self::assertCount(count($malformedInputs), $this->recipes->getErrors());
	}
}
