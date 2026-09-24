<?php

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketMine Team
 * @link http://www.pocketmine.net/
 *
 *
 */

declare(strict_types=1);

namespace pocketmine\addon\world;

use pocketmine\addon\AddonManager;
use pocketmine\block\Block;
use pocketmine\block\VanillaBlocks;
use pocketmine\data\bedrock\block\BlockStateData;
use pocketmine\nbt\LittleEndianNbtSerializer;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\world\format\io\GlobalBlockStateHandlers;
use pocketmine\world\World;
use function count;
use function file_get_contents;

/**
 * One .mcstructure file: its size, block palette and block layout. Placing it resolves each palette entry once
 * (through the block state upgrader, so structures saved by older game versions place correctly).
 */
final class AddonStructure{
	/**
	 * @param array{int, int, int}      $size
	 * @param list<int>                 $indices  palette index per position (-1: leave the world's block)
	 * @param list<CompoundTag>         $palette
	 */
	private function __construct(
		private string $name,
		private array $size,
		private array $indices,
		private array $palette
	){}

	/** @throws \RuntimeException */
	public static function load(string $name, string $file) : self{
		$data = @file_get_contents($file);
		if($data === false){
			throw new \RuntimeException("cannot read $file");
		}
		$root = (new LittleEndianNbtSerializer())->read($data)->mustGetCompoundTag();
		$sizeTag = $root->getListTag("size");
		if($sizeTag === null || count($sizeTag) !== 3){
			throw new \RuntimeException("no size");
		}
		$size = [];
		foreach($sizeTag->getValue() as $tag){
			$size[] = $tag instanceof IntTag ? $tag->getValue() : 0;
		}
		$structure = $root->getCompoundTag("structure") ?? throw new \RuntimeException("no structure data");
		$layers = $structure->getListTag("block_indices") ?? throw new \RuntimeException("no block indices");
		$first = $layers->first();
		if(!$first instanceof ListTag){
			throw new \RuntimeException("no block layer");
		}
		$indices = [];
		foreach($first->getValue() as $tag){
			$indices[] = $tag instanceof IntTag ? $tag->getValue() : -1;
		}
		$palette = [];
		$paletteTag = $structure->getCompoundTag("palette")?->getCompoundTag("default")?->getListTag("block_palette");
		foreach($paletteTag?->getValue() ?? [] as $entry){
			if($entry instanceof CompoundTag){
				$palette[] = $entry;
			}
		}
		if(count($indices) !== $size[0] * $size[1] * $size[2]){
			throw new \RuntimeException("block layer does not match the size");
		}
		return new self($name, [$size[0], $size[1], $size[2]], $indices, $palette);
	}

	public function getName() : string{ return $this->name; }

	/** @return array{int, int, int} */
	public function getSize() : array{ return $this->size; }

	/**
	 * Places the structure with its lowest corner at (x, y, z). Returns the number of blocks set.
	 */
	public function place(World $world, int $x, int $y, int $z, AddonManager $manager) : int{
		/** @var list<Block|null> $blocks */
		$blocks = [];
		foreach($this->palette as $entry){
			$blocks[] = self::resolve($entry, $manager);
		}
		[$sx, $sy, $sz] = $this->size;
		$placed = 0;
		$i = 0;
		//the game stores positions x-major, then y, then z
		for($dx = 0; $dx < $sx; ++$dx){
			for($dy = 0; $dy < $sy; ++$dy){
				for($dz = 0; $dz < $sz; ++$dz){
					$index = $this->indices[$i++];
					$block = $index >= 0 ? ($blocks[$index] ?? null) : null;
					if($block === null || !$world->isInWorld($x + $dx, $y + $dy, $z + $dz)){
						continue;
					}
					$world->setBlockAt($x + $dx, $y + $dy, $z + $dz, $block, false);
					$placed++;
				}
			}
		}
		return $placed;
	}

	private static function resolve(CompoundTag $entry, AddonManager $manager) : ?Block{
		$name = $entry->getString("name", "minecraft:air");
		if($name === "minecraft:structure_void"){
			return null;
		}
		$addon = $manager->getBlock($name);
		if($addon !== null){
			return $addon;
		}
		try{
			$data = GlobalBlockStateHandlers::getUpgrader()->getBlockStateUpgrader()->upgrade(BlockStateData::fromNbt($entry));
			return GlobalBlockStateHandlers::getDeserializer()->deserializeBlock($data);
		}catch(\Throwable){
			return VanillaBlocks::AIR();
		}
	}
}
