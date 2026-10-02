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

namespace pocketmine\world\generator\structure;

use pocketmine\block\Chest;
use pocketmine\block\tile\Chest as ChestTile;
use pocketmine\inventory\Inventory;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\utils\Random;
use pocketmine\world\format\Chunk;
use pocketmine\world\World;
use function array_values;
use function count;

/**
 * Fills the chests of generated structures. Population runs off the main thread and cannot create tiles, so
 * structures place bare chest blocks, and once the chunk is populated this finds them and adds the chest with its loot.
 */
final class StructureLoot{
	/** How far from a candidate chunk's centre a structure's chest can be. */
	private const SEARCH_RADIUS = 10;
	private const SEARCH_DEPTH = 10;

	private StructurePopulator $populator;

	public function __construct(private World $world, int $seed){
		$this->populator = StructurePopulator::createDefault($seed);
	}

	public function onChunkPopulated(int $chunkX, int $chunkZ) : void{
		foreach($this->populator->getCandidates($chunkX, $chunkZ) as $structure){
			if(self::hasLoot($structure->getName())){
				self::fillChests(
					$this->world,
					($chunkX << Chunk::COORD_BIT_SIZE) + 8,
					($chunkZ << Chunk::COORD_BIT_SIZE) + 8,
					$structure->getName(),
					new Random(World::chunkHash($chunkX, $chunkZ) ^ $this->world->getSeed())
				);
			}
		}
	}

	public static function hasLoot(string $structure) : bool{
		return count(self::table($structure)) > 0;
	}

	/**
	 * Gives every bare chest block (one without a chest tile) near x/z its tile and loot for the structure. With
	 * $fillEmpty, chests that have a tile but nothing in them are filled too (setting a block on the main thread
	 * creates its tile).
	 *
	 * @return int chests filled
	 */
	public static function fillChests(World $world, int $x, int $z, string $structure, Random $random, bool $fillEmpty = false) : int{
		$filled = 0;
		for($cx = $x - self::SEARCH_RADIUS; $cx <= $x + self::SEARCH_RADIUS; ++$cx){
			for($cz = $z - self::SEARCH_RADIUS; $cz <= $z + self::SEARCH_RADIUS; ++$cz){
				if(!$world->isChunkLoaded($cx >> Chunk::COORD_BIT_SIZE, $cz >> Chunk::COORD_BIT_SIZE)){
					continue;
				}
				$top = $world->getHighestBlockAt($cx, $cz);
				if($top === null){
					continue;
				}
				for($cy = $top; $cy > $top - self::SEARCH_DEPTH && $cy >= $world->getMinY(); --$cy){
					if(!$world->getBlockAt($cx, $cy, $cz) instanceof Chest){
						continue;
					}
					$tile = $world->getTileAt($cx, $cy, $cz);
					if($tile === null){
						$tile = new ChestTile($world, new Vector3($cx, $cy, $cz));
						$world->addTile($tile);
					}elseif(!$fillEmpty || !$tile instanceof ChestTile || count($tile->getRealInventory()->getContents()) > 0){
						continue;
					}
					self::fill($tile->getRealInventory(), $structure, $random);
					++$filled;
				}
			}
		}
		return $filled;
	}

	public static function fill(Inventory $inventory, string $structure, Random $random) : void{
		$table = self::table($structure);
		if(count($table) === 0){
			return;
		}
		$slots = [];
		for($i = 0, $size = $inventory->getSize(); $i < $size; ++$i){
			$slots[] = $i;
		}
		$rolls = 3 + $random->nextBoundedInt(4);
		$totalWeight = 0;
		foreach($table as [, , , $weight]){
			$totalWeight += $weight;
		}
		for($roll = 0; $roll < $rolls && count($slots) > 0; ++$roll){
			$pick = $random->nextBoundedInt($totalWeight);
			foreach($table as [$item, $min, $max, $weight]){
				$pick -= $weight;
				if($pick < 0){
					$index = $random->nextBoundedInt(count($slots));
					$slot = $slots[$index];
					unset($slots[$index]);
					$slots = array_values($slots);
					$inventory->setItem($slot, (clone $item)->setCount($random->nextRange($min, $max)));
					break;
				}
			}
		}
	}

	/**
	 * @return list<array{Item, int, int, int}> item, min count, max count, weight
	 */
	private static function table(string $structure) : array{
		return match($structure){
			"igloo" => [
				[VanillaItems::APPLE(), 1, 3, 15],
				[VanillaItems::COAL(), 1, 4, 15],
				[VanillaItems::GOLD_NUGGET(), 1, 3, 10],
				[VanillaItems::STONE_AXE(), 1, 1, 2],
				[VanillaItems::ROTTEN_FLESH(), 1, 3, 10],
				[VanillaItems::EMERALD(), 1, 1, 1],
				[VanillaItems::WHEAT(), 2, 3, 10],
				[VanillaItems::GOLDEN_APPLE(), 1, 1, 1],
			],
			"ruins" => [
				[VanillaItems::IRON_INGOT(), 1, 4, 10],
				[VanillaItems::BREAD(), 1, 3, 15],
				[VanillaItems::WHEAT(), 1, 5, 15],
				[VanillaItems::COAL(), 1, 5, 10],
				[VanillaItems::BONE(), 1, 4, 10],
				[VanillaItems::STRING(), 1, 4, 10],
				[VanillaItems::EMERALD(), 1, 2, 3],
				[VanillaItems::IRON_PICKAXE(), 1, 1, 2],
			],
			"ruined_portal" => [
				[VanillaItems::IRON_NUGGET(), 9, 18, 40],
				[VanillaItems::FLINT_AND_STEEL(), 1, 1, 40],
				[VanillaItems::GOLD_NUGGET(), 4, 18, 40],
				[VanillaItems::GOLDEN_CARROT(), 4, 12, 15],
				[VanillaItems::GOLDEN_SWORD(), 1, 1, 15],
				[VanillaItems::GOLD_INGOT(), 2, 8, 5],
				[VanillaItems::CLOCK(), 1, 1, 5],
				[VanillaItems::GOLDEN_APPLE(), 1, 1, 2],
			],
			default => [],
		};
	}
}
