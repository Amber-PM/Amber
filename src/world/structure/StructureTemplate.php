<?php

/*
 *
 *     _             _               
 *    / \\   _ __ ___ | |__   ___ _ __ 
 *   / _ \\ | '_ ` _ \\| '_ \\ / _ \\ '__|
 *  / ___ \\| | | | | | |_) |  __/ |   
 * /_/   \\_\\_| |_| |_|_.__/ \\___|_|   
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

namespace pocketmine\world\structure;

use pocketmine\block\Block;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\RuntimeBlockStateRegistry;
use pocketmine\math\Vector3;
use pocketmine\nbt\LittleEndianNbtSerializer;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\TreeRoot;
use pocketmine\utils\Random;
use pocketmine\world\ChunkManager;
use pocketmine\world\format\Chunk;
use pocketmine\world\format\io\GlobalBlockStateHandlers;
use pocketmine\world\format\SubChunk;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function is_array;
use function max;
use function min;
use function zlib_decode;

class StructureTemplate{

	/**
	 * @param int[] $palette Array of block state IDs
	 * @param int[] $layer0 Flat array of palette indices of length sizeX * sizeY * sizeZ
	 * @param int[] $layer1 Waterlogging/secondary layer
	 */
	public function __construct(
		private Vector3 $size,
		private Vector3 $origin,
		private array $palette = [],
		private array $layer0 = [],
		private array $layer1 = []
	){}

	public function getSize() : Vector3{
		return $this->size;
	}

	public function getOrigin() : Vector3{
		return $this->origin;
	}

	/**
	 * @return int[]
	 */
	public function getPalette() : array{
		return $this->palette;
	}

	public function getBlockStateId(int $x, int $y, int $z) : int{
		$sx = (int) $this->size->x;
		$sy = (int) $this->size->y;
		$sz = (int) $this->size->z;

		if($x < 0 || $x >= $sx || $y < 0 || $y >= $sy || $z < 0 || $z >= $sz){
			return Block::EMPTY_STATE_ID;
		}

		$flatIdx = ($x * $sy + $y) * $sz + $z;
		$paletteIdx = $this->layer0[$flatIdx] ?? -1;
		if($paletteIdx < 0 || !isset($this->palette[$paletteIdx])){
			return Block::EMPTY_STATE_ID;
		}

		return $this->palette[$paletteIdx];
	}

	public function setBlockStateId(int $x, int $y, int $z, int $stateId) : void{
		$sx = (int) $this->size->x;
		$sy = (int) $this->size->y;
		$sz = (int) $this->size->z;

		if($x < 0 || $x >= $sx || $y < 0 || $y >= $sy || $z < 0 || $z >= $sz){
			return;
		}

		$paletteIdx = -1;
		foreach($this->palette as $k => $v){
			if($v === $stateId){
				$paletteIdx = (int) $k;
				break;
			}
		}

		if($paletteIdx === -1){
			$paletteIdx = count($this->palette);
			$this->palette[$paletteIdx] = $stateId;
		}

		$flatIdx = ($x * $sy + $y) * $sz + $z;
		$this->layer0[$flatIdx] = $paletteIdx;
	}

	/**
	 * @param StructureBlock[] $blocks
	 */
	public static function fromBlockMap(Vector3 $size, array $blocks) : self{
		$template = new self($size, new Vector3(0, 0, 0));
		foreach($blocks as $b){
			if($b instanceof StructureBlock){
				$template->setBlockStateId($b->x, $b->y, $b->z, $b->stateId);
			}
		}
		return $template;
	}

	public static function loadFromFile(string $path) : self{
		if(!file_exists($path)){
			throw new \InvalidArgumentException("Structure file not found: $path");
		}

		$raw = (string) file_get_contents($path);
		$decompressed = @zlib_decode($raw);
		if($decompressed !== false){
			$raw = $decompressed;
		}

		$nbt = (new LittleEndianNbtSerializer())->read($raw)->mustGetCompoundTag();
		return self::fromNbt($nbt);
	}

	public function saveToFile(string $path) : void{
		$nbt = $this->toNbt();
		$raw = (new LittleEndianNbtSerializer())->write(new TreeRoot($nbt));
		file_put_contents($path, $raw);
	}

	public static function fromNbt(CompoundTag $root) : self{
		$sizeTag = $root->getListTag("size");
		if($sizeTag === null || $sizeTag->count() < 3){
			throw new \InvalidArgumentException("Invalid or missing size tag in structure NBT");
		}

		$sizeVals = [];
		foreach($sizeTag->getValue() as $tag){
			if($tag instanceof IntTag){
				$sizeVals[] = $tag->getValue();
			}
		}
		$size = new Vector3($sizeVals[0] ?? 1, $sizeVals[1] ?? 1, $sizeVals[2] ?? 1);

		$origin = new Vector3(0, 0, 0);
		$originTag = $root->getListTag("structure_world_origin");
		if($originTag !== null && $originTag->count() >= 3){
			$origVals = [];
			foreach($originTag->getValue() as $tag){
				if($tag instanceof IntTag){
					$origVals[] = $tag->getValue();
				}
			}
			$origin = new Vector3($origVals[0] ?? 0, $origVals[1] ?? 0, $origVals[2] ?? 0);
		}

		$structureTag = $root->getCompoundTag("structure");
		if($structureTag === null){
			throw new \InvalidArgumentException("Missing 'structure' compound tag");
		}

		// Palette
		$palette = [];
		$paletteTag = $structureTag->getCompoundTag("palette");
		$defaultTag = $paletteTag?->getCompoundTag("default");
		$blockPaletteTag = $defaultTag?->getListTag("block_palette");

		if($blockPaletteTag !== null){
			$upgrader = GlobalBlockStateHandlers::getUpgrader();
			$deserializer = GlobalBlockStateHandlers::getDeserializer();
			foreach($blockPaletteTag->getValue() as $entry){
				if($entry instanceof CompoundTag){
					try{
						$stateData = $upgrader->upgradeBlockStateNbt($entry);
						$stateId = $deserializer->deserialize($stateData);
					}catch(\Throwable){
						$stateId = Block::EMPTY_STATE_ID;
					}
					$palette[] = $stateId;
				}
			}
		}

		// Block indices
		$indicesTag = $structureTag->getListTag("block_indices");
		$layer0 = [];
		$layer1 = [];

		if($indicesTag !== null && $indicesTag->count() > 0){
			$layers = $indicesTag->getValue();
			if(isset($layers[0]) && $layers[0] instanceof ListTag){
				foreach($layers[0]->getValue() as $idxTag){
					if($idxTag instanceof IntTag){
						$layer0[] = $idxTag->getValue();
					}
				}
			}
			if(isset($layers[1]) && $layers[1] instanceof ListTag){
				foreach($layers[1]->getValue() as $idxTag){
					if($idxTag instanceof IntTag){
						$layer1[] = $idxTag->getValue();
					}
				}
			}
		}

		return new self($size, $origin, $palette, $layer0, $layer1);
	}

	public function toNbt() : CompoundTag{
		$root = CompoundTag::create()->setInt("format_version", 1);

		$root->setTag("size", new ListTag([
			new IntTag((int) $this->size->x),
			new IntTag((int) $this->size->y),
			new IntTag((int) $this->size->z)
		]));

		$root->setTag("structure_world_origin", new ListTag([
			new IntTag((int) $this->origin->x),
			new IntTag((int) $this->origin->y),
			new IntTag((int) $this->origin->z)
		]));

		$blockPaletteList = new ListTag([]);
		$serializer = GlobalBlockStateHandlers::getSerializer();
		foreach($this->palette as $stateId){
			try{
				$blockPaletteList->push($serializer->serialize($stateId)->toNbt());
			}catch(\Throwable){
				$blockPaletteList->push(CompoundTag::create()->setString("name", "minecraft:air"));
			}
		}

		$totalBlocks = ((int) $this->size->x) * ((int) $this->size->y) * ((int) $this->size->z);

		$l0Tag = new ListTag([]);
		for($i = 0; $i < $totalBlocks; ++$i){
			$l0Tag->push(new IntTag($this->layer0[$i] ?? -1));
		}
		$l1Tag = new ListTag([]);
		for($i = 0; $i < $totalBlocks; ++$i){
			$l1Tag->push(new IntTag($this->layer1[$i] ?? -1));
		}

		$structure = CompoundTag::create();
		$structure->setTag("block_indices", new ListTag([$l0Tag, $l1Tag]));

		$defaultCompound = CompoundTag::create();
		$defaultCompound->setTag("block_palette", $blockPaletteList);
		$defaultCompound->setTag("block_position_data", CompoundTag::create());

		$paletteCompound = CompoundTag::create();
		$paletteCompound->setTag("default", $defaultCompound);
		$structure->setTag("palette", $paletteCompound);
		$structure->setTag("entities", new ListTag([]));

		$root->setTag("structure", $structure);
		return $root;
	}

	/**
	 * Pre-computes rotated and filtered state IDs per palette index for O(1) inner-loop throughput.
	 *
	 * @return array<int, int> paletteIndex => stateId (-2 represents skip/void)
	 */
	private function computeTransformedPalette(StructurePlacementSettings $settings) : array{
		$transformed = [];
		$registry = RuntimeBlockStateRegistry::getInstance();
		$rotation = $settings->rotation;

		foreach($this->palette as $idx => $stateId){
			if($stateId === Block::EMPTY_STATE_ID){
				$transformed[$idx] = $settings->ignoreAir ? -2 : Block::EMPTY_STATE_ID;
				continue;
			}

			try{
				$block = $registry->fromStateId($stateId);
			}catch(\Throwable){
				$transformed[$idx] = $stateId;
				continue;
			}

			if($block->getTypeId() === BlockTypeIds::STRUCTURE_VOID){
				$transformed[$idx] = -2;
				continue;
			}

			if($rotation !== StructureRotation::NONE){
				if($block instanceof \pocketmine\block\utils\HorizontalFacing || $block instanceof \pocketmine\block\utils\AnyFacing){
					$newFacing = $rotation->rotateFacing($block->getFacing());
					$block = (clone $block)->setFacing($newFacing);
				}
			}

			$transformed[$idx] = $block->getStateId();
		}

		return $transformed;
	}

	public function placeIntoChunk(Chunk $chunk, int $chunkBlockX, int $chunkBlockY, int $chunkBlockZ, StructurePlacementSettings $settings) : void{
		$sx = (int) $this->size->x;
		$sy = (int) $this->size->y;
		$sz = (int) $this->size->z;

		$rand = new Random(0);
		if($settings->seed !== null){
			$rand->setSeed($settings->seed);
		}

		$paletteMap = $this->computeTransformedPalette($settings);
		$minY = Chunk::MIN_SUBCHUNK_INDEX << SubChunk::COORD_BIT_SIZE;
		$maxY = ((Chunk::MAX_SUBCHUNK_INDEX + 1) << SubChunk::COORD_BIT_SIZE) - 1;

		for($x = 0; $x < $sx; ++$x){
			for($z = 0; $z < $sz; ++$z){
				[$mx, $mz] = $settings->mirror->mirrorCoordinate($x, $z, $sx, $sz);
				[$rx, $rz] = $settings->rotation->rotateCoordinate($mx, $mz, $sx, $sz);

				$targetX = $chunkBlockX + $rx;
				$targetZ = $chunkBlockZ + $rz;

				if($targetX < 0 || $targetX >= Chunk::EDGE_LENGTH || $targetZ < 0 || $targetZ >= Chunk::EDGE_LENGTH){
					continue;
				}

				for($y = 0; $y < $sy; ++$y){
					$targetY = $chunkBlockY + $y;
					if($targetY < $minY || $targetY > $maxY){
						continue;
					}

					if($settings->integrity < 1.0 && $rand->nextFloat() >= $settings->integrity){
						continue;
					}

					$flatIdx = ($x * $sy + $y) * $sz + $z;
					$pIdx = $this->layer0[$flatIdx] ?? -1;
					if($pIdx < 0 || !isset($paletteMap[$pIdx])){
						continue;
					}

					$stateId = $paletteMap[$pIdx];
					if($stateId === -2){
						continue;
					}

					$chunk->setBlockStateId($targetX, $targetY, $targetZ, $stateId);
				}
			}
		}
	}

	public function placeIntoWorld(ChunkManager $world, int $worldX, int $worldY, int $worldZ, StructurePlacementSettings $settings) : void{
		$sx = (int) $this->size->x;
		$sy = (int) $this->size->y;
		$sz = (int) $this->size->z;

		$rand = new Random(0);
		if($settings->seed !== null){
			$rand->setSeed($settings->seed);
		}

		$paletteMap = $this->computeTransformedPalette($settings);

		for($x = 0; $x < $sx; ++$x){
			for($z = 0; $z < $sz; ++$z){
				[$mx, $mz] = $settings->mirror->mirrorCoordinate($x, $z, $sx, $sz);
				[$rx, $rz] = $settings->rotation->rotateCoordinate($mx, $mz, $sx, $sz);

				$targetX = $worldX + $rx;
				$targetZ = $worldZ + $rz;

				for($y = 0; $y < $sy; ++$y){
					$targetY = $worldY + $y;

					if($settings->integrity < 1.0 && $rand->nextFloat() >= $settings->integrity){
						continue;
					}

					$flatIdx = ($x * $sy + $y) * $sz + $z;
					$pIdx = $this->layer0[$flatIdx] ?? -1;
					if($pIdx < 0 || !isset($paletteMap[$pIdx])){
						continue;
					}

					$stateId = $paletteMap[$pIdx];
					if($stateId === -2){
						continue;
					}

					$chunk = $world->getChunk($targetX >> 4, $targetZ >> 4);
					if($chunk !== null){
						$chunk->setBlockStateId($targetX & 0xf, $targetY, $targetZ & 0xf, $stateId);
					}
				}
			}
		}
	}
}
