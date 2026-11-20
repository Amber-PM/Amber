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
use pocketmine\block\Rail;
use pocketmine\block\RuntimeBlockStateRegistry;
use pocketmine\block\StraightOnlyRail;
use pocketmine\block\utils\AnyFacing;
use pocketmine\block\utils\HorizontalFacing;
use pocketmine\block\utils\PillarRotation;
use pocketmine\block\utils\RailConnectionInfo;
use pocketmine\math\Axis;
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
use pocketmine\world\World;
use function count;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function strlen;
use function zlib_decode;

class StructureTemplate{

	private const MIN_Y = Chunk::MIN_SUBCHUNK_INDEX * SubChunk::EDGE_LENGTH;
	private const MAX_Y = (Chunk::MAX_SUBCHUNK_INDEX + 1) * SubChunk::EDGE_LENGTH - 1;
	private const SKIP_STATE = -2;

	/** @var array<int, CompoundTag> Block entity NBT indexed by local flat block index */
	private array $blockEntityData = [];

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

	public function setBlockStateId(int $x, int $y, int $z, int $stateId, ?CompoundTag $nbt = null) : void{
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
		if($nbt !== null){
			$this->blockEntityData[$flatIdx] = clone $nbt;
		}else{
			unset($this->blockEntityData[$flatIdx]);
		}
	}

	/**
	 * @param StructureBlock[] $blocks
	 */
	public static function fromBlockMap(Vector3 $size, array $blocks) : self{
		$template = new self($size, new Vector3(0, 0, 0));
		foreach($blocks as $b){
			if($b instanceof StructureBlock){
				$template->setBlockStateId($b->x, $b->y, $b->z, $b->stateId, $b->nbt);
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
		if(@file_put_contents($path, $raw) !== strlen($raw)){
			throw new \RuntimeException("Failed to write structure file: $path");
		}
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

		$template = new self($size, $origin, $palette, $layer0, $layer1);
		$positionData = $defaultTag?->getCompoundTag("block_position_data");
		$totalBlocks = (int) $size->x * (int) $size->y * (int) $size->z;
		if($positionData !== null){
			foreach($positionData->getValue() as $index => $entry){
				$flatIdx = (int) $index;
				if((string) $flatIdx === (string) $index && $flatIdx >= 0 && $flatIdx < $totalBlocks &&
					$entry instanceof CompoundTag && ($nbt = $entry->getCompoundTag("block_entity_data")) !== null){
					$template->blockEntityData[$flatIdx] = clone $nbt;
				}
			}
		}
		return $template;
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
		$positionData = CompoundTag::create();
		foreach($this->blockEntityData as $index => $nbt){
			$positionData->setTag((string) $index, CompoundTag::create()->setTag("block_entity_data", clone $nbt));
		}
		$defaultCompound->setTag("block_position_data", $positionData);

		$paletteCompound = CompoundTag::create();
		$paletteCompound->setTag("default", $defaultCompound);
		$structure->setTag("palette", $paletteCompound);
		$structure->setTag("entities", new ListTag([]));

		$root->setTag("structure", $structure);
		return $root;
	}

	/**
	 * Pre-computes transformed and filtered state IDs per palette index for O(1) inner-loop throughput.
	 *
	 * @return array<int, int> paletteIndex => stateId (-2 represents skip/void)
	 */
	private function computeTransformedPalette(StructurePlacementSettings $settings) : array{
		$transformed = [];
		$registry = RuntimeBlockStateRegistry::getInstance();
		$rotation = $settings->rotation;
		$mirror = $settings->mirror;
		$quarterTurn = $rotation === StructureRotation::CLOCKWISE_90 || $rotation === StructureRotation::COUNTERCLOCKWISE_90;

		foreach($this->palette as $idx => $stateId){
			if($stateId === Block::EMPTY_STATE_ID){
				$transformed[$idx] = $settings->ignoreAir ? self::SKIP_STATE : Block::EMPTY_STATE_ID;
				continue;
			}

			try{
				$block = $registry->fromStateId($stateId);
			}catch(\Throwable){
				$transformed[$idx] = $stateId;
				continue;
			}

			if($block->getTypeId() === BlockTypeIds::STRUCTURE_VOID){
				$transformed[$idx] = self::SKIP_STATE;
				continue;
			}

			if($mirror === StructureMirror::NONE && $rotation === StructureRotation::NONE){
				$transformed[$idx] = $stateId;
				continue;
			}

			if($block instanceof HorizontalFacing || $block instanceof AnyFacing){
				$block->setFacing($rotation->rotateFacing($mirror->mirrorFacing($block->getFacing())));
			}
			if($quarterTurn && $block instanceof PillarRotation){
				$block->setAxis(match($block->getAxis()){
					Axis::X => Axis::Z,
					Axis::Z => Axis::X,
					default => $block->getAxis(),
				});
			}
			if($block instanceof Rail || $block instanceof StraightOnlyRail){
				$shapes = RailConnectionInfo::CONNECTIONS + ($block instanceof Rail ? RailConnectionInfo::CURVE_CONNECTIONS : []);
				[$a, $b] = $shapes[$block->getShape()];
				$a = $rotation->rotateFacing($mirror->mirrorFacing($a & ~RailConnectionInfo::FLAG_ASCEND)) | ($a & RailConnectionInfo::FLAG_ASCEND);
				$b = $rotation->rotateFacing($mirror->mirrorFacing($b & ~RailConnectionInfo::FLAG_ASCEND)) | ($b & RailConnectionInfo::FLAG_ASCEND);
				foreach($shapes as $shape => [$first, $second]){
					if(($a === $first && $b === $second) || ($a === $second && $b === $first)){
						$block->setShape($shape);
						break;
					}
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
					if($targetY < self::MIN_Y || $targetY > self::MAX_Y){
						continue;
					}

					if($settings->integrity < 1.0 && $rand->nextFloat() >= $settings->integrity){
						continue;
					}

					$flatIdx = ($x * $sy + $y) * $sz + $z;
					$primaryIndex = $this->layer0[$flatIdx] ?? -1;
					$secondaryIndex = $this->layer1[$flatIdx] ?? -1;
					$stateId = $primaryIndex < 0 ? self::SKIP_STATE : ($paletteMap[$primaryIndex] ?? self::SKIP_STATE);
					$secondaryId = $secondaryIndex < 0 ? self::SKIP_STATE : ($paletteMap[$secondaryIndex] ?? self::SKIP_STATE);
					$this->writeChunkLayers($chunk, $targetX, $targetY, $targetZ, $stateId, $secondaryId);
					if($stateId !== self::SKIP_STATE && isset($this->blockEntityData[$flatIdx])){
						$chunk->queueBlockEntityData($targetX, $targetY, $targetZ, $this->blockEntityData[$flatIdx]);
					}
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
					if($targetY < self::MIN_Y || $targetY > self::MAX_Y){
						continue;
					}

					if($settings->integrity < 1.0 && $rand->nextFloat() >= $settings->integrity){
						continue;
					}

					$flatIdx = ($x * $sy + $y) * $sz + $z;
					$primaryIndex = $this->layer0[$flatIdx] ?? -1;
					$secondaryIndex = $this->layer1[$flatIdx] ?? -1;
					$stateId = $primaryIndex < 0 ? self::SKIP_STATE : ($paletteMap[$primaryIndex] ?? self::SKIP_STATE);
					$secondaryId = $secondaryIndex < 0 ? self::SKIP_STATE : ($paletteMap[$secondaryIndex] ?? self::SKIP_STATE);
					if($stateId === self::SKIP_STATE && $secondaryId === self::SKIP_STATE){
						continue;
					}

					$chunk = $world->getChunk($targetX >> Chunk::COORD_BIT_SIZE, $targetZ >> Chunk::COORD_BIT_SIZE);
					$localX = $targetX & Chunk::COORD_MASK;
					$localZ = $targetZ & Chunk::COORD_MASK;
					if($chunk !== null){
						$this->writeChunkLayers($chunk, $localX, $targetY, $localZ, $stateId, $secondaryId);
					}elseif($stateId !== self::SKIP_STATE){
						$block = RuntimeBlockStateRegistry::getInstance()->fromStateId($stateId);
						$world->setBlockAt($targetX, $targetY, $targetZ, $block);
						if($secondaryId !== self::SKIP_STATE || isset($this->blockEntityData[$flatIdx])){
							$chunk = $world->getChunk($targetX >> Chunk::COORD_BIT_SIZE, $targetZ >> Chunk::COORD_BIT_SIZE);
							if($chunk !== null && $secondaryId !== self::SKIP_STATE){
								$chunk->setBlockStateId($localX, $targetY, $localZ, $secondaryId, 1);
							}
						}
					}

					if($stateId !== self::SKIP_STATE && isset($this->blockEntityData[$flatIdx])){
						$nbt = $this->blockEntityData[$flatIdx];
						if($world instanceof World){
							$world->applyStructureBlockEntityData($targetX, $targetY, $targetZ, $nbt);
						}else{
							$chunk?->queueBlockEntityData($localX, $targetY, $localZ, $nbt);
						}
					}
				}
			}
		}
	}

	private function writeChunkLayers(Chunk $chunk, int $x, int $y, int $z, int $stateId, int $secondaryId) : void{
		if($stateId !== self::SKIP_STATE){
			$chunk->setBlockStateId($x, $y, $z, $stateId);
		}
		if($secondaryId !== self::SKIP_STATE){
			$chunk->setBlockStateId($x, $y, $z, $secondaryId, 1);
		}
	}
}
