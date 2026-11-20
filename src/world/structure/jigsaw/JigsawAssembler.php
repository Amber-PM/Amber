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

namespace pocketmine\world\structure\jigsaw;

use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\utils\Random;
use pocketmine\world\ChunkManager;
use pocketmine\world\structure\StructureRotation;
use function array_shift;

final class JigsawAssembler{

	/**
	 * Assembles a multi-piece procedural structure using Jigsaw logic.
	 *
	 * @return PlacedStructurePiece[]
	 */
	public static function assemble(
		string $startPoolId,
		Vector3 $startPos,
		int $maxDepth = 7,
		?int $seed = null,
		?JigsawPoolRegistry $registry = null
	) : array{
		$registry ??= JigsawPoolRegistry::getInstance();
		$startPool = $registry->getPool($startPoolId);
		if($startPool === null){
			return [];
		}

		$random = new Random(0);
		if($seed !== null){
			$random->setSeed($seed);
		}

		$rootPiece = $startPool->selectPiece($random);
		if($rootPiece === null){
			return [];
		}

		$startPos = $startPos->floor();
		$rootRotation = StructureRotation::NONE;
		$rootBB = $rootPiece->getBoundingBox($startPos, $rootRotation);
		$rootPlaced = new PlacedStructurePiece($rootPiece, $startPos, $rootRotation, $rootBB, 0);

		/** @var PlacedStructurePiece[] $placedPieces */
		$placedPieces = [$rootPlaced];
		/** @var PlacedStructurePiece[] $openQueue */
		$openQueue = [$rootPlaced];

		while(!empty($openQueue)){
			$parent = array_shift($openQueue);
			if($parent->depth >= $maxDepth){
				continue;
			}

			$parentSize = $parent->piece->getTemplate()->getSize();
			$parentSx = (int) $parentSize->x;
			$parentSz = (int) $parentSize->z;

			foreach($parent->piece->getConnections() as $conn){
				// 1. Calculate parent joint's world position & facing
				[$prx, $prz] = $parent->rotation->rotateCoordinate((int) $conn->position->x, (int) $conn->position->z, $parentSx, $parentSz);
				$parentJointWorld = $parent->worldPos->add($prx, $conn->position->getFloorY(), $prz);
				$parentFacing = $parent->rotation->rotateFacing($conn->facing);
				$requiredChildFacing = Facing::opposite($parentFacing);

				// 2. Fetch candidate pool
				$targetPool = $registry->getPool($conn->targetPool);
				if($targetPool === null && $conn->targetPool !== "minecraft:empty"){
					$targetPool = $registry->getPool($startPool->getFallbackPool());
				}
				if($targetPool === null){
					continue;
				}

				$matingWorldPos = $parentJointWorld->getSide($parentFacing);
				$placedChild = self::tryPlacePiece($targetPool, $random, $matingWorldPos, $requiredChildFacing, $parent->depth + 1, $placedPieces);
				if($placedChild === null && $targetPool->getId() === $conn->targetPool && $targetPool->getFallbackPool() !== $targetPool->getId()){
					$fallbackPool = $registry->getPool($targetPool->getFallbackPool());
					if($fallbackPool !== null){
						$placedChild = self::tryPlacePiece($fallbackPool, $random, $matingWorldPos, $requiredChildFacing, $parent->depth + 1, $placedPieces);
					}
				}

				if($placedChild !== null){
					$placedPieces[] = $placedChild;
					$openQueue[] = $placedChild;
				}
			}
		}

		return $placedPieces;
	}

	/** @param PlacedStructurePiece[] $placedPieces */
	private static function tryPlacePiece(JigsawPool $pool, Random $random, Vector3 $matingWorldPos, int $requiredFacing, int $depth, array $placedPieces) : ?PlacedStructurePiece{
		$childPiece = $pool->selectPiece($random);
		if($childPiece === null){
			return null;
		}

		$childSize = $childPiece->getTemplate()->getSize();
		$childSx = (int) $childSize->x;
		$childSz = (int) $childSize->z;

		// Find a matching joint in the child piece.
		foreach($childPiece->getConnections() as $childConn){
			// Try rotations to align child joint opposing parent joint
			foreach([StructureRotation::NONE, StructureRotation::CLOCKWISE_90, StructureRotation::CLOCKWISE_180, StructureRotation::COUNTERCLOCKWISE_90] as $rot){
				if($rot->rotateFacing($childConn->facing) !== $requiredFacing){
					continue;
				}

				[$crx, $crz] = $rot->rotateCoordinate((int) $childConn->position->x, (int) $childConn->position->z, $childSx, $childSz);

				$childWorldOrigin = $matingWorldPos->subtract($crx, $childConn->position->getFloorY(), $crz);
				$childBB = $childPiece->getBoundingBox($childWorldOrigin, $rot);

				// Collision check against all existing placed pieces
				$intersects = false;
				foreach($placedPieces as $existing){
					if($existing->boundingBox->intersectsWith($childBB)){
						$intersects = true;
						break;
					}
				}

				if(!$intersects){
					return new PlacedStructurePiece($childPiece, $childWorldOrigin, $rot, $childBB, $depth);
				}
			}
		}

		return null;
	}

	/**
	 * @param PlacedStructurePiece[] $pieces
	 */
	public static function placeAll(ChunkManager $world, array $pieces) : void{
		foreach($pieces as $p){
			$p->place($world);
		}
	}
}
