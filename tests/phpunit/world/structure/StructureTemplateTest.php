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
use pocketmine\block\RuntimeBlockStateRegistry;
use pocketmine\block\tile\Chest;
use pocketmine\block\VanillaBlocks;
use pocketmine\data\bedrock\block\BlockLegacyMetadata;
use pocketmine\math\Axis;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\world\ChunkManager;
use pocketmine\world\format\Chunk;
use pocketmine\world\format\io\FastChunkSerializer;
use pocketmine\world\format\PalettedBlockArray;
use pocketmine\world\format\SubChunk;
use pocketmine\world\SimpleChunkManager;
use pocketmine\world\World;
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
	public function testBlockEntityDataSurvivesFileAndWorkerRoundTrips() : void{
		$payload = CompoundTag::create()->setString("CustomName", "Workshop");
		$template = StructureTemplate::fromBlockMap(new Vector3(2, 1, 2), [
			new StructureBlock(0, 0, 0, VanillaBlocks::CHEST()->getStateId(), $payload),
			new StructureBlock(1, 0, 0, VanillaBlocks::STONE()->getStateId()),
		]);
		$file = tempnam(sys_get_temp_dir(), "amber_struct_");
		try{
			$template->saveToFile($file);
			$restored = StructureTemplate::loadFromFile($file);
		}finally{
			unlink($file);
		}

		$chunk = new Chunk([], false);
		$restored->placeIntoChunk($chunk, 4, 64, 6, new StructurePlacementSettings(rotation: StructureRotation::CLOCKWISE_90));
		$workerResult = FastChunkSerializer::deserializeTerrain(FastChunkSerializer::serializeTerrain($chunk));
		$pending = $workerResult->getPendingBlockEntityData();
		self::assertCount(1, $pending);
		$placed = $pending[Chunk::blockHash(5, 64, 6)];
		self::assertSame(5, $placed->getInt("x"));
		self::assertSame(64, $placed->getInt("y"));
		self::assertSame(6, $placed->getInt("z"));
		self::assertSame("Workshop", $placed->getString("CustomName"));
		self::assertSame(VanillaBlocks::STONE()->getStateId(), $workerResult->getBlockStateId(5, 64, 7));
	}

	public function testLiveWorldAppliesBlockEntityDataAtDestination() : void{
		$template = StructureTemplate::fromBlockMap(new Vector3(1, 1, 1), [
			new StructureBlock(0, 0, 0, VanillaBlocks::CHEST()->getStateId(),
				CompoundTag::create()->setString("CustomName", "Workshop")->setInt("x", 999)),
		]);
		$chunk = new Chunk([], false);
		$world = $this->getMockBuilder(World::class)->disableOriginalConstructor()
			->onlyMethods(["getChunk", "isInWorld", "isLoaded", "addTile", "removeTile", "invalidateBlockCache"])->getMock();
		(new \ReflectionProperty(World::class, "blockStateRegistry"))->setValue($world, RuntimeBlockStateRegistry::getInstance());
		$world->method("getChunk")->with(-1, 2)->willReturn($chunk);
		$world->method("isInWorld")->with(-2, 65, 35)->willReturn(true);
		$world->method("isLoaded")->willReturn(true);
		$world->expects(self::once())->method("addTile")->willReturnCallback(static function(Chest $tile) use ($chunk) : void{
			self::assertSame("Workshop", $tile->getName());
			self::assertTrue($tile->getPosition()->equals(new Vector3(-2, 65, 35)));
			$chunk->addTile($tile);
		});

		$template->placeIntoWorld($world, -2, 65, 35, new StructurePlacementSettings());
		self::assertInstanceOf(Chest::class, $chunk->getTile(14, 65, 3));
	}

	public function testDirectionalStateTransformsAndSecondaryLayerPlacement() : void{
		$north = VanillaBlocks::CHEST()->setFacing(Facing::NORTH);
		$rail = VanillaBlocks::RAIL();
		$log = VanillaBlocks::OAK_LOG()->setAxis(Axis::X);
		$template = new StructureTemplate(new Vector3(3, 1, 1), new Vector3(0, 0, 0),
			[$north->getStateId(), $rail->getStateId(), $log->getStateId()], [0, -1, 2], [-1, 1, -1]);

		$chunk = new Chunk([], false);
		$template->placeIntoChunk($chunk, 0, 64, 0, new StructurePlacementSettings(mirror: StructureMirror::LEFT_RIGHT));
		self::assertSame(VanillaBlocks::CHEST()->setFacing(Facing::SOUTH)->getStateId(), $chunk->getBlockStateId(0, 64, 0));

		$secondaryOnly = new Chunk([], false);
		$template->placeIntoChunk($secondaryOnly, 14, 64, 0, new StructurePlacementSettings());
		$secondaryOnly->setBlockStateId(14, 64, 0, VanillaBlocks::AIR()->getStateId());
		$subChunk = $secondaryOnly->getSubChunk(4);
		$subChunk->collectGarbage();
		self::assertSame(VanillaBlocks::AIR()->getStateId(), $subChunk->getBlockStateId(15, 0, 0));
		self::assertSame($rail->getStateId(), $subChunk->getBlockLayers()[1]->get(15, 0, 0));

		$world = new SimpleChunkManager(-64, 320);
		$rotatedChunk = new Chunk([], false);
		$world->setChunk(1, -1, $rotatedChunk);
		$template->placeIntoWorld($world, 16, 64, -16, new StructurePlacementSettings(
			rotation: StructureRotation::CLOCKWISE_90, mirror: StructureMirror::LEFT_RIGHT
		));
		self::assertSame(VanillaBlocks::CHEST()->setFacing(Facing::WEST)->getStateId(), $rotatedChunk->getBlockStateId(0, 64, 0));
		self::assertSame(VanillaBlocks::OAK_LOG()->setAxis(Axis::Z)->getStateId(), $rotatedChunk->getBlockStateId(0, 64, 2));
		$secondary = $rotatedChunk->getSubChunk(4)->getBlockLayers()[1];
		self::assertSame($rail->setShape(BlockLegacyMetadata::RAIL_STRAIGHT_EAST_WEST)->getStateId(), $secondary->get(0, 0, 1));
		self::assertSame(VanillaBlocks::AIR()->getStateId(), $secondary->get(0, 0, 0));
	}

	public function testWorldPlacementClipsBeforeEitherWritePath() : void{
		$stone = VanillaBlocks::STONE()->getStateId();
		$template = new StructureTemplate(new Vector3(1, 2, 1), new Vector3(0, 0, 0), [$stone], [0, 0]);
		$chunk = new Chunk([], false);
		$loaded = new SimpleChunkManager(-64, 320);
		$loaded->setChunk(0, 0, $chunk);
		$template->placeIntoWorld($loaded, 0, 319, 0, new StructurePlacementSettings());
		self::assertSame($stone, $chunk->getBlockStateId(0, 319, 0));

		$unloaded = $this->createMock(ChunkManager::class);
		$unloaded->expects(self::once())->method("getChunk")->with(0, 0)->willReturn(null);
		$unloaded->expects(self::once())->method("setBlockAt")->with(0, -64, 0);
		$template->placeIntoWorld($unloaded, 0, -65, 0, new StructurePlacementSettings());
	}

}
