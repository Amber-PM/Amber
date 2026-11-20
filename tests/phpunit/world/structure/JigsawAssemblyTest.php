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
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\utils\Random;
use pocketmine\world\ChunkManager;
use pocketmine\world\format\Chunk;
use pocketmine\world\SimpleChunkManager;
use pocketmine\world\structure\jigsaw\JigsawAssembler;
use pocketmine\world\structure\jigsaw\JigsawConnection;
use pocketmine\world\structure\jigsaw\JigsawJointType;
use pocketmine\world\structure\jigsaw\JigsawPiece;
use pocketmine\world\structure\jigsaw\JigsawPool;
use pocketmine\world\structure\jigsaw\JigsawPoolRegistry;
use pocketmine\world\structure\populator\StructurePopulator;

final class JigsawAssemblyTest extends TestCase{

	protected function setUp() : void{
		JigsawPoolRegistry::getInstance()->clear();
	}

	public function testJigsawPoolWeightedSelection() : void{
		$pool = new JigsawPool("test_pool");

		$template1 = new StructureTemplate(new Vector3(2, 2, 2), new Vector3(0, 0, 0));
		$template2 = new StructureTemplate(new Vector3(4, 4, 4), new Vector3(0, 0, 0));

		$piece1 = new JigsawPiece($template1, weight: 1);
		$piece2 = new JigsawPiece($template2, weight: 99);

		$pool->addPiece($piece1);
		$pool->addPiece($piece2);

		$counts = [1 => 0, 2 => 0];
		$rand = new Random(42);
		for($i = 0; $i < 500; ++$i){
			$selected = $pool->selectPiece($rand);
			if($selected === $piece1){
				$counts[1]++;
			}elseif($selected === $piece2){
				$counts[2]++;
			}
		}

		self::assertGreaterThan($counts[1] * 10, $counts[2]);
	}

	public function testJigsawAssemblyAssemblesConnectedPieces() : void{
		$registry = JigsawPoolRegistry::getInstance();

		// Piece A: 4x2x4 with a connection facing NORTH at (2, 0, 0)
		$templateA = new StructureTemplate(new Vector3(4, 2, 4), new Vector3(0, 0, 0));
		$connA = new JigsawConnection(new Vector3(2, 0, 0), Facing::NORTH, "pool_b");
		$pieceA = new JigsawPiece($templateA, weight: 1, connections: [$connA]);

		$poolA = new JigsawPool("pool_a", pieces: [$pieceA]);
		$registry->registerPool($poolA);

		// Piece B: 4x2x4 with a connection facing SOUTH at (2, 0, 3)
		$templateB = new StructureTemplate(new Vector3(4, 2, 4), new Vector3(0, 0, 0));
		$connB = new JigsawConnection(new Vector3(2, 0, 3), Facing::SOUTH, "minecraft:empty");
		$pieceB = new JigsawPiece($templateB, weight: 1, connections: [$connB]);

		$fallback = new JigsawPiece($templateA, connections: [$connB]);
		$registry->registerPool(new JigsawPool("backup_b", pieces: [$fallback]));
		$poolB = new JigsawPool("pool_b", "backup_b", [$pieceB]);
		$registry->registerPool($poolB);

		$templateB->setBlockStateId(0, 0, 0, VanillaBlocks::STONE()->getStateId());
		$placed = JigsawAssembler::assemble("pool_a", new Vector3(-0.25, 64.75, -0.5), maxDepth: 2, seed: 100);

		self::assertCount(2, $placed);
		self::assertSame($pieceA, $placed[0]->piece);
		self::assertSame($pieceB, $placed[1]->piece);
		self::assertTrue($placed[0]->worldPos->equals(new Vector3(-1, 64, -1)));
		self::assertTrue($placed[1]->worldPos->equals(new Vector3(-1, 64, -5)));

		$world = new SimpleChunkManager(-64, 320);
		$world->setChunk(-1, -1, new Chunk([], false));
		JigsawAssembler::placeAll($world, $placed);
		self::assertSame(VanillaBlocks::STONE()->getStateId(), $world->getBlockAt(-1, 64, -5)->getStateId());

		// Check bounding boxes do not intersect
		self::assertFalse($placed[0]->boundingBox->intersectsWith($placed[1]->boundingBox));
	}

	public function testFailedPlacementUsesTargetPoolFallback() : void{
		$registry = JigsawPoolRegistry::getInstance();
		$template = new StructureTemplate(new Vector3(3, 1, 3), new Vector3(0, 0, 0));
		$root = new JigsawPiece($template, connections: [
			new JigsawConnection(new Vector3(1, 0, 0), Facing::NORTH, "target"),
		]);
		$colliding = new JigsawPiece($template, connections: [
			new JigsawConnection(new Vector3(1, 0, 0), Facing::SOUTH, "minecraft:empty"),
		]);
		$fallback = new JigsawPiece($template, connections: [
			new JigsawConnection(new Vector3(1, 0, 2), Facing::SOUTH, "minecraft:empty"),
		]);
		$registry->registerPool(new JigsawPool("root", "root_backup", [$root]));
		$registry->registerPool(new JigsawPool("root_backup", pieces: [clone $fallback]));
		$registry->registerPool(new JigsawPool("target", "target_backup", [$colliding]));
		$registry->registerPool(new JigsawPool("target_backup", pieces: [$fallback]));

		$pieces = JigsawAssembler::assemble("root", new Vector3(0, 64, 0), maxDepth: 1, seed: 100);
		self::assertCount(2, $pieces);
		self::assertSame($fallback, $pieces[1]->piece);
	}

	public function testCollidingPrimaryCandidateDoesNotSkipOtherPrimaryCandidates() : void{
		$registry = JigsawPoolRegistry::getInstance();
		$template = new StructureTemplate(new Vector3(3, 1, 3), new Vector3(0, 0, 0));
		$root = new JigsawPiece($template, connections: [
			new JigsawConnection(new Vector3(1, 0, 0), Facing::NORTH, "target"),
		]);
		$colliding = new JigsawPiece($template, weight: 1000, connections: [
			new JigsawConnection(new Vector3(1, 0, 0), Facing::SOUTH, "minecraft:empty"),
		]);
		$compatible = new JigsawPiece($template, connections: [
			new JigsawConnection(new Vector3(1, 0, 2), Facing::SOUTH, "minecraft:empty"),
		]);
		$start = new JigsawPool("root", pieces: [$root]);
		$primary = new JigsawPool("target", "backup", [$colliding, $compatible]);
		$registry->registerPool($start);
		$registry->registerPool($primary);
		$registry->registerPool(new JigsawPool("backup", pieces: [clone $compatible]));

		$random = new Random(100);
		$start->selectPiece($random);
		self::assertSame($colliding, $primary->selectPiece($random));

		$pieces = JigsawAssembler::assemble("root", new Vector3(0, 64, 0), maxDepth: 1, seed: 100);
		self::assertCount(2, $pieces);
		self::assertSame($compatible, $pieces[1]->piece);
	}

	public function testJigsawAssemblyAvoidsCollidingPieces() : void{
		$registry = JigsawPoolRegistry::getInstance();

		// Piece that tries to branch in two opposite directions that would collide if looped
		$template = new StructureTemplate(new Vector3(5, 2, 5), new Vector3(0, 0, 0));
		$conn1 = new JigsawConnection(new Vector3(2, 0, 0), Facing::NORTH, "colliding_pool");
		$conn2 = new JigsawConnection(new Vector3(4, 0, 2), Facing::EAST, "colliding_pool");
		$piece = new JigsawPiece($template, weight: 1, connections: [$conn1, $conn2]);

		$pool = new JigsawPool("colliding_pool", pieces: [$piece]);
		$registry->registerPool($pool);

		$placed = JigsawAssembler::assemble("colliding_pool", new Vector3(0, 64, 0), maxDepth: 4, seed: 999);

		// None of the placed pieces should intersect each other
		$count = count($placed);
		for($i = 0; $i < $count; ++$i){
			for($j = $i + 1; $j < $count; ++$j){
				self::assertFalse(
					$placed[$i]->boundingBox->intersectsWith($placed[$j]->boundingBox),
					"Pieces $i and $j collided in jigsaw assembly"
				);
			}
		}
	}

	public function testStructurePopulatorDeterministicSpacing() : void{
		$populator = new StructurePopulator("village_pool", spacing: 32, separation: 8, salt: 123456);

		$seed = 777888;
		$candidateChunks = [];

		for($cx = 0; $cx < 64; ++$cx){
			for($cz = 0; $cz < 64; ++$cz){
				if($populator->shouldGenerateInChunk($cx, $cz, $seed)){
					$candidateChunks[] = [$cx, $cz];
				}
			}
		}

		// In a 64x64 chunk area with 32-chunk spacing, exactly 4 grid regions exist (2x2)
		self::assertCount(4, $candidateChunks);

		// Deterministic check: re-running with same seed produces identical chunks
		$reCandidateChunks = [];
		for($cx = 0; $cx < 64; ++$cx){
			for($cz = 0; $cz < 64; ++$cz){
				if($populator->shouldGenerateInChunk($cx, $cz, $seed)){
					$reCandidateChunks[] = [$cx, $cz];
				}
			}
		}
		self::assertSame($candidateChunks, $reCandidateChunks);
	}
	public function testPopulationOnlyRetrievesSelectedChunks() : void{
		// Separation leaves exactly one possible offset in every grid region.
		$populator = new StructurePopulator("missing", spacing: 4, separation: 3, worldSeed: 0);
		$world = $this->createMock(ChunkManager::class);
		$world->expects(self::once())->method("getChunk")->with(-4, 0)->willReturn(null);

		$populator->populate($world, -3, 1, new Random(0));
		$populator->populate($world, -4, 0, new Random(0));
	}

	public function testInvalidSeparationIsRejected() : void{
		$this->expectException(\InvalidArgumentException::class);
		new StructurePopulator("missing", spacing: 4, separation: 4);
	}

}
