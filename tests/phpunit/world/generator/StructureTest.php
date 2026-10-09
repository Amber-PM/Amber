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
use pocketmine\block\BlockTypeIds;
use pocketmine\block\VanillaBlocks;
use pocketmine\inventory\SimpleInventory;
use pocketmine\utils\Random;
use pocketmine\world\format\Chunk;
use pocketmine\world\generator\normal\Normal;
use pocketmine\world\generator\structure\Structure;
use pocketmine\world\generator\structure\StructureLoot;
use pocketmine\world\generator\structure\StructurePopulator;
use pocketmine\world\SimpleChunkManager;
use pocketmine\world\World;
use function count;
if(!class_exists(\pocketmine\world\format\PalettedBlockArray::class)){
	class PalettedBlockArrayPolyfill{
		private array $data = [];
		public function __construct(private int $fill = 0){}
		public function get(int $x, int $y, int $z) : int{
			return $this->data[($x << 8) | ($z << 4) | $y] ?? $this->fill;
		}
		public function set(int $x, int $y, int $z, int $val) : void{
			$this->data[($x << 8) | ($z << 4) | $y] = $val;
		}
		public function getBitsPerBlock() : int{ return 0; }
		public function getPalette() : array{ return array_values(array_unique([$this->fill, ...array_values($this->data)])); }
		public static function fromData(int $bitsPerBlock, string $wordArray, array $palette) : self{ return new self($palette[0] ?? 0); }
	}
	class_alias(PalettedBlockArrayPolyfill::class, \pocketmine\world\format\PalettedBlockArray::class);
}

require_once __DIR__ . '/../../../../src/world/generator/structure/Structure.php';
require_once __DIR__ . '/../../../../src/world/generator/structure/StructurePopulator.php';
require_once __DIR__ . '/../../../../src/world/generator/structure/StructureLoot.php';
require_once __DIR__ . '/../../../../src/world/generator/structure/Boulder.php';
require_once __DIR__ . '/../../../../src/world/generator/structure/DesertWell.php';
require_once __DIR__ . '/../../../../src/world/generator/structure/Fossil.php';
require_once __DIR__ . '/../../../../src/world/generator/structure/Igloo.php';
require_once __DIR__ . '/../../../../src/world/generator/structure/RuinedPortal.php';
require_once __DIR__ . '/../../../../src/world/generator/structure/Ruins.php';

final class StructureTest extends TestCase{
	private const GROUND_Y = 64;

	/**
	 * 3x3 chunks around chunk 0,0 (what population can reach): stone up to GROUND_Y - 1, then $surface.
	 */
	private function makeWorld(Block $surface) : SimpleChunkManager{
		$world = new SimpleChunkManager(World::Y_MIN, World::Y_MAX);
		for($chunkX = -1; $chunkX <= 1; ++$chunkX){
			for($chunkZ = -1; $chunkZ <= 1; ++$chunkZ){
				$world->setChunk($chunkX, $chunkZ, new Chunk([], false));
			}
		}
		for($x = -16; $x < 32; ++$x){
			for($z = -16; $z < 32; ++$z){
				for($y = 20; $y < self::GROUND_Y; ++$y){
					$world->setBlockAt($x, $y, $z, VanillaBlocks::STONE());
				}
				$world->setBlockAt($x, self::GROUND_Y, $z, $surface);
			}
		}
		return $world;
	}

	/**
	 * @return int[] block type ID => count, in the area population can reach
	 */
	private function countBlocks(SimpleChunkManager $world) : array{
		$counts = [];
		for($x = -16; $x < 32; ++$x){
			for($z = -16; $z < 32; ++$z){
				for($y = 20; $y < self::GROUND_Y + 8; ++$y){
					$id = $world->getBlockAt($x, $y, $z)->getTypeId();
					$counts[$id] = ($counts[$id] ?? 0) + 1;
				}
			}
		}
		return $counts;
	}

	public function testGroundY() : void{
		$world = $this->makeWorld(VanillaBlocks::GRASS());
		$world->setBlockAt(8, self::GROUND_Y + 1, 8, VanillaBlocks::TALL_GRASS());
		self::assertSame(self::GROUND_Y, Structure::groundY($world, 8, 8));

		$world->setBlockAt(9, self::GROUND_Y + 1, 9, VanillaBlocks::WATER());
		self::assertNull(Structure::groundY($world, 9, 9), "ground under water");

		self::assertNull(Structure::groundY($world, 100, 100), "not loaded");
	}

	public function testDesertWellNeedsSand() : void{
		$well = StructurePopulator::defaultStructures()["desert_well"];
		self::assertFalse($well->place($this->makeWorld(VanillaBlocks::GRASS()), 8, 8, new Random(1)));

		$world = $this->makeWorld(VanillaBlocks::SAND());
		self::assertTrue($well->place($world, 8, 8, new Random(1)));
		self::assertSame(BlockTypeIds::WATER, $world->getBlockAt(8, self::GROUND_Y, 8)->getTypeId());
		self::assertSame(BlockTypeIds::SANDSTONE, $world->getBlockAt(9, self::GROUND_Y + 2, 9)->getTypeId(), "pillar");
		self::assertSame(BlockTypeIds::SANDSTONE, $world->getBlockAt(8, self::GROUND_Y + 3, 8)->getTypeId(), "roof");
	}

	public function testIglooIsHollowWithEntrance() : void{
		$world = $this->makeWorld(VanillaBlocks::GRASS());
		self::assertTrue(StructurePopulator::defaultStructures()["igloo"]->place($world, 8, 8, new Random(1)));
		self::assertSame(BlockTypeIds::SNOW, $world->getBlockAt(8, self::GROUND_Y + 3, 8)->getTypeId(), "dome top");
		self::assertSame(BlockTypeIds::AIR, $world->getBlockAt(8, self::GROUND_Y + 2, 8)->getTypeId(), "inside");
		self::assertSame(BlockTypeIds::CARPET, $world->getBlockAt(8, self::GROUND_Y + 1, 8)->getTypeId());
		foreach([2, 3, 4] as $dz){
			self::assertSame(BlockTypeIds::AIR, $world->getBlockAt(8, self::GROUND_Y + 1, 8 + $dz)->getTypeId(), "entrance at +$dz");
			self::assertSame(BlockTypeIds::AIR, $world->getBlockAt(8, self::GROUND_Y + 2, 8 + $dz)->getTypeId(), "entrance at +$dz");
		}
	}

	public function testStructuresRejectSteepGround() : void{
		$world = $this->makeWorld(VanillaBlocks::GRASS());
		for($x = -16; $x < 32; ++$x){
			for($z = 8; $z < 32; ++$z){
				for($y = self::GROUND_Y + 1; $y <= self::GROUND_Y + 5; ++$y){
					$world->setBlockAt($x, $y, $z, VanillaBlocks::STONE());
				}
			}
		}
		foreach(["igloo", "ruins", "ruined_portal"] as $name){
			self::assertFalse(StructurePopulator::defaultStructures()[$name]->place($world, 8, 8, new Random(1)), $name);
		}
	}

	public function testEveryStructureStaysInPopulationReach() : void{
		foreach(StructurePopulator::defaultStructures() as $name => $structure){
			for($seed = 0; $seed < 20; ++$seed){
				$world = $this->makeWorld($name === "desert_well" ? VanillaBlocks::SAND() : VanillaBlocks::GRASS());
				//writing outside the 3x3 chunks would throw
				self::assertTrue($structure->place($world, 8 + ($seed % 7) - 3, 8 - ($seed % 7) + 3, new Random($seed)), "$name, seed $seed");
			}
		}
	}

	public function testFossilIsBuried() : void{
		$world = $this->makeWorld(VanillaBlocks::GRASS());
		self::assertTrue(StructurePopulator::defaultStructures()["fossil"]->place($world, 8, 8, new Random(3)));
		$counts = $this->countBlocks($world);
		self::assertGreaterThan(10, ($counts[BlockTypeIds::BONE_BLOCK] ?? 0) + ($counts[BlockTypeIds::COAL_ORE] ?? 0));
		for($x = -16; $x < 32; ++$x){
			for($z = -16; $z < 32; ++$z){
				self::assertSame(BlockTypeIds::GRASS, $world->getBlockAt($x, self::GROUND_Y, $z)->getTypeId(), "surface untouched");
			}
		}
	}

	public function testOneCandidateChunkPerRegion() : void{
		$populator = new StructurePopulator(12345);
		foreach([[12, 4], [6, 2], [24, 8]] as [$spacing, $separation]){
			for($regionX = -2; $regionX <= 1; ++$regionX){
				for($regionZ = -2; $regionZ <= 1; ++$regionZ){
					$candidates = [];
					for($dx = 0; $dx < $spacing; ++$dx){
						for($dz = 0; $dz < $spacing; ++$dz){
							$chunkX = $regionX * $spacing + $dx;
							$chunkZ = $regionZ * $spacing + $dz;
							if($populator->isCandidate(0, $spacing, $separation, $chunkX, $chunkZ)){
								$candidates[] = [$dx, $dz];
							}
						}
					}
					self::assertCount(1, $candidates, "region $regionX,$regionZ with spacing $spacing");
					self::assertLessThan($spacing - $separation, $candidates[0][0]);
					self::assertLessThan($spacing - $separation, $candidates[0][1]);
				}
			}
		}
	}

	public function testIglooHasChest() : void{
		$world = $this->makeWorld(VanillaBlocks::GRASS());
		self::assertTrue(StructurePopulator::defaultStructures()["igloo"]->place($world, 8, 8, new Random(1)));
		self::assertSame(BlockTypeIds::CHEST, $world->getBlockAt(9, self::GROUND_Y + 1, 6)->getTypeId());
	}

	public function testLootFill() : void{
		foreach(["igloo", "ruins", "ruined_portal"] as $structure){
			self::assertTrue(StructureLoot::hasLoot($structure));
			for($seed = 0; $seed < 10; ++$seed){
				$inventory = new SimpleInventory(27);
				StructureLoot::fill($inventory, $structure, new Random($seed));
				$stacks = $inventory->getContents();
				self::assertGreaterThanOrEqual(3, count($stacks), $structure);
				self::assertLessThanOrEqual(6, count($stacks), $structure);
				foreach($stacks as $item){
					self::assertGreaterThan(0, $item->getCount());
					self::assertLessThanOrEqual($item->getMaxStackSize(), $item->getCount());
				}
			}
		}
		self::assertFalse(StructureLoot::hasLoot("fossil"));
	}

	public function testStructuresOption() : void{
		self::assertTrue(Normal::parseStructuresOption(""));
		self::assertTrue(Normal::parseStructuresOption("something=else"));
		self::assertFalse(Normal::parseStructuresOption("structures=false"));
		self::assertFalse(Normal::parseStructuresOption("a=b;Structures = off"));
		self::assertTrue(Normal::parseStructuresOption("structures=true"));

		$this->expectException(InvalidGeneratorOptionsException::class);
		Normal::parseStructuresOption("structures=maybe");
	}
}
