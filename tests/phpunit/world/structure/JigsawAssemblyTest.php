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

		$poolB = new JigsawPool("pool_b", pieces: [$pieceB]);
		$registry->registerPool($poolB);

		$placed = JigsawAssembler::assemble("pool_a", new Vector3(0, 64, 0), maxDepth: 2, seed: 100);

		self::assertCount(2, $placed);
		self::assertSame($pieceA, $placed[0]->piece);
		self::assertSame($pieceB, $placed[1]->piece);

		// Check bounding boxes do not intersect
		self::assertFalse($placed[0]->boundingBox->intersectsWith($placed[1]->boundingBox));
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
}
