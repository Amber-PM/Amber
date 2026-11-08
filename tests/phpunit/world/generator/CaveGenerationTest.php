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

namespace pocketmine\world\generator;

use PHPUnit\Framework\TestCase;
use pocketmine\block\Block;
use pocketmine\block\VanillaBlocks;
use pocketmine\world\format\Chunk;
use pocketmine\world\generator\normal\ModernOverworld;

final class CaveGenerationTest extends TestCase{

	public function testNegativeYCavesCarveBelowZeroAndCarveDeepslate() : void{
		$gen = new ModernOverworld(seed: 1337, preset: "");
		$chunk = new Chunk([], false);

		$gen->generateChunkDirect($chunk, 0, 0);

		// Y=-64 must be bedrock
		self::assertSame(VanillaBlocks::BEDROCK()->getStateId(), $chunk->getBlockStateId(0, -64, 0));

		// Y=-30 should contain deepslate, air/water/lava from cave carving, or stone
		$yNeg30 = $chunk->getBlockStateId(8, -30, 8);
		self::assertThat($yNeg30, self::logicalOr(
			self::equalTo(VanillaBlocks::DEEPSLATE()->getStateId()),
			self::equalTo(Block::EMPTY_STATE_ID),
			self::equalTo(VanillaBlocks::WATER()->getStateId()),
			self::equalTo(VanillaBlocks::LAVA()->getStateId())
		));
	}

	public function testRoughBedrockPatternBetweenMinus64AndMinus60() : void{
		$gen = new ModernOverworld(seed: 777, preset: "");
		$chunk = new Chunk([], false);

		$gen->generateChunkDirect($chunk, 0, 0);

		// Y=-64 is 100% bedrock
		for($x = 0; $x < 16; ++$x){
			for($z = 0; $z < 16; ++$z){
				self::assertSame(VanillaBlocks::BEDROCK()->getStateId(), $chunk->getBlockStateId($x, -64, $z));
			}
		}

		// Y=-59 must NOT be bedrock
		self::assertNotSame(VanillaBlocks::BEDROCK()->getStateId(), $chunk->getBlockStateId(0, -59, 0));
	}

	public function testModernOrePopulatorPlacesStoneAndDeepslateOres() : void{
		$populator = new \pocketmine\world\generator\populator\ModernOrePopulator();
		$world = $this->createMock(\pocketmine\world\ChunkManager::class);
		$random = new \pocketmine\utils\Random(42);

		$blocks = [];
		$world->method("getBlockAt")->willReturnCallback(function(int $x, int $y, int $z) use (&$blocks) : Block{
			return $blocks["$x,$y,$z"] ?? ($y < 0 ? VanillaBlocks::DEEPSLATE() : VanillaBlocks::STONE());
		});

		$world->method("setBlockAt")->willReturnCallback(function(int $x, int $y, int $z, Block $block) use (&$blocks) : bool{
			$blocks["$x,$y,$z"] = $block;
			return true;
		});

		$populator->populate($world, 0, 0, $random);

		// Assert that ores were placed in the mock world
		self::assertNotEmpty($blocks, "Ores must be placed by ModernOrePopulator");

		$foundStoneOre = false;
		$foundDeepslateOre = false;

		foreach($blocks as $coord => $block){
			[$x, $y, $z] = explode(",", $coord);
			$y = (int) $y;
			if($y > 0 && str_contains($block->getName(), "Ore")){
				$foundStoneOre = true;
			}elseif($y < 0 && str_contains($block->getName(), "Deepslate") && str_contains($block->getName(), "Ore")){
				$foundDeepslateOre = true;
			}
		}

		self::assertTrue($foundStoneOre, "Must place stone ores above Y=0");
		self::assertTrue($foundDeepslateOre, "Must place deepslate ores below Y=0");
	}
}
