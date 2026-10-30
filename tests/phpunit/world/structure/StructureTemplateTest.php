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

use PHPUnit\Framework\TestCase;
use pocketmine\block\VanillaBlocks;
use pocketmine\math\Vector3;
use pocketmine\world\format\Chunk;
use pocketmine\world\format\PalettedBlockArray;
use pocketmine\world\format\SubChunk;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

final class StructureTemplateTest extends TestCase{

	public function testStructureTemplateSetAndGetBlock() : void{
		$template = new StructureTemplate(new Vector3(3, 3, 3), new Vector3(0, 0, 0));
		$sandstoneId = VanillaBlocks::SANDSTONE()->getStateId();

		$template->setBlockStateId(1, 1, 1, $sandstoneId);
		self::assertSame($sandstoneId, $template->getBlockStateId(1, 1, 1));
		self::assertSame(\pocketmine\block\Block::EMPTY_STATE_ID, $template->getBlockStateId(0, 0, 0));
	}

	public function testStructureNbtSerializationRoundtrip() : void{
		$template = new StructureTemplate(new Vector3(4, 5, 6), new Vector3(1, 2, 3));
		$stoneId = VanillaBlocks::STONE()->getStateId();
		$glassId = VanillaBlocks::GLASS()->getStateId();

		$template->setBlockStateId(0, 0, 0, $stoneId);
		$template->setBlockStateId(3, 4, 5, $glassId);

		$nbt = $template->toNbt();
		$restored = StructureTemplate::fromNbt($nbt);

		self::assertSame(4, (int) $restored->getSize()->x);
		self::assertSame(5, (int) $restored->getSize()->y);
		self::assertSame(6, (int) $restored->getSize()->z);
		self::assertSame(1, (int) $restored->getOrigin()->x);
		self::assertSame(2, (int) $restored->getOrigin()->y);
		self::assertSame(3, (int) $restored->getOrigin()->z);

		self::assertSame($stoneId, $restored->getBlockStateId(0, 0, 0));
		self::assertSame($glassId, $restored->getBlockStateId(3, 4, 5));
		self::assertSame(\pocketmine\block\Block::EMPTY_STATE_ID, $restored->getBlockStateId(1, 1, 1));
	}

	public function testStructureSaveAndLoadFile() : void{
		$template = new StructureTemplate(new Vector3(2, 2, 2), new Vector3(0, 0, 0));
		$obsidianId = VanillaBlocks::OBSIDIAN()->getStateId();
		$template->setBlockStateId(1, 0, 1, $obsidianId);

		$tempFile = tempnam(sys_get_temp_dir(), "amber_struct_");
		try{
			$template->saveToFile($tempFile);
			$loaded = StructureTemplate::loadFromFile($tempFile);

			self::assertSame(2, (int) $loaded->getSize()->x);
			self::assertSame($obsidianId, $loaded->getBlockStateId(1, 0, 1));
		}finally{
			@unlink($tempFile);
		}
	}

	public function testStructurePlacementIntoChunkWithRotation() : void{
		$template = new StructureTemplate(new Vector3(3, 1, 2), new Vector3(0, 0, 0));
		$goldId = VanillaBlocks::GOLD()->getStateId();
		// Place block at local (2, 0, 0)
		$template->setBlockStateId(2, 0, 0, $goldId);

		$chunk = new Chunk([], false);
		for($sy = Chunk::MIN_SUBCHUNK_INDEX; $sy <= Chunk::MAX_SUBCHUNK_INDEX; ++$sy){
			/** @phpstan-ignore class.notFound */
			$chunk->setSubChunk($sy, new SubChunk(0, [], new PalettedBlockArray(0)));
		}

		// Rotation: 90 degrees clockwise of (3, 1, 2):
		// New size is (2, 1, 3)
		// Local (x=2, z=0) rotates: rotX = sizeZ - 1 - z = 2 - 1 - 0 = 1; rotZ = x = 2.
		$settings = new StructurePlacementSettings(rotation: StructureRotation::CLOCKWISE_90);
		$template->placeIntoChunk($chunk, 0, 64, 0, $settings);

		self::assertSame($goldId, $chunk->getBlockStateId(1, 64, 2));
	}

	public function testStructureIntegrityDecay() : void{
		$template = new StructureTemplate(new Vector3(10, 1, 10), new Vector3(0, 0, 0));
		$brickId = VanillaBlocks::BRICKS()->getStateId();
		for($x = 0; $x < 10; ++$x){
			for($z = 0; $z < 10; ++$z){
				$template->setBlockStateId($x, 0, $z, $brickId);
			}
		}

		$chunk = new Chunk([], false);
		for($sy = Chunk::MIN_SUBCHUNK_INDEX; $sy <= Chunk::MAX_SUBCHUNK_INDEX; ++$sy){
			/** @phpstan-ignore class.notFound */
			$chunk->setSubChunk($sy, new SubChunk(0, [], new PalettedBlockArray(0)));
		}

		$settings = new StructurePlacementSettings(integrity: 0.5, seed: 12345);
		$template->placeIntoChunk($chunk, 0, 64, 0, $settings);

		$placedCount = 0;
		for($x = 0; $x < 10; ++$x){
			for($z = 0; $z < 10; ++$z){
				if($chunk->getBlockStateId($x, 64, $z) === $brickId){
					++$placedCount;
				}
			}
		}

		// With integrity 0.5 across 100 blocks, expect roughly 30-70 blocks placed, neither 0 nor 100
		self::assertGreaterThan(20, $placedCount);
		self::assertLessThan(80, $placedCount);
	}

	public function testStructureVoidSkippedDuringPlacement() : void{
		$template = new StructureTemplate(new Vector3(2, 1, 2), new Vector3(0, 0, 0));
		$voidId = VanillaBlocks::STRUCTURE_VOID()->getStateId();
		$template->setBlockStateId(0, 0, 0, $voidId);

		$chunk = new Chunk([], false);
		for($sy = Chunk::MIN_SUBCHUNK_INDEX; $sy <= Chunk::MAX_SUBCHUNK_INDEX; ++$sy){
			/** @phpstan-ignore class.notFound */
			$chunk->setSubChunk($sy, new SubChunk(0, [], new PalettedBlockArray(0)));
		}

		$stoneId = VanillaBlocks::STONE()->getStateId();
		$chunk->setBlockStateId(5, 64, 5, $stoneId);

		$settings = new StructurePlacementSettings();
		$template->placeIntoChunk($chunk, 5, 64, 5, $settings);

		// Stone must NOT be overwritten by structure void
		self::assertSame($stoneId, $chunk->getBlockStateId(5, 64, 5));
	}
}
