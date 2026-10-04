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

namespace pocketmine\block\piston;

use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\world\World;
use function array_shift;
use function array_values;
use function count;
use function usort;

final class PistonStructureCalculator{
	public const MAX_BLOCK_PUSH_LIMIT = 12;

	/** @var list<Vector3> */
	private array $toMove = [];
	/** @var list<Vector3> */
	private array $toDestroy = [];

	public function __construct(
		private World $world,
		private Vector3 $pistonPos,
		private int $pistonFacing,
		private bool $extending
	){}

	/**
	 * Calculates the list of blocks to move and destroy. Returns false if the piston push/pull is blocked.
	 */
	public function calculate() : bool{
		$this->toMove = [];
		$this->toDestroy = [];

		if($this->extending){
			return $this->calculateExtension();
		}
		return $this->calculateRetraction();
	}

	private function calculateExtension() : bool{
		$headPos = $this->pistonPos->getSide($this->pistonFacing);
		$startBlock = $this->world->getBlockAt($headPos->getFloorX(), $headPos->getFloorY(), $headPos->getFloorZ());

		if($startBlock->canBeReplaced()){
			return true;
		}

		if(PistonMoveRules::isBreakableOnPush($startBlock)){
			$this->toDestroy[] = $headPos;
			return true;
		}

		if(PistonMoveRules::isImmovable($startBlock)){
			return false;
		}

		/** @var array<string, Vector3> $toMoveMap */
		$toMoveMap = [];
		/** @var array<string, Vector3> $toDestroyMap */
		$toDestroyMap = [];
		/** @var list<Vector3> $queue */
		$queue = [];

		$startKey = self::posKey($headPos);
		$toMoveMap[$startKey] = $headPos;
		$queue[] = $headPos;

		$dir = $this->pistonFacing;

		while(count($queue) > 0){
			$current = array_shift($queue);
			$currentBlock = $this->world->getBlockAt($current->getFloorX(), $current->getFloorY(), $current->getFloorZ());

			// Check block directly in front in push direction
			$next = $current->getSide($dir);
			if($next->equals($this->pistonPos)){
				return false; // Can't push into piston base
			}

			$nextKey = self::posKey($next);
			if(!isset($toMoveMap[$nextKey]) && !isset($toDestroyMap[$nextKey])){
				$nextBlock = $this->world->getBlockAt($next->getFloorX(), $next->getFloorY(), $next->getFloorZ());
				if(!$nextBlock->canBeReplaced()){
					if(PistonMoveRules::isBreakableOnPush($nextBlock)){
						$toDestroyMap[$nextKey] = $next;
					}elseif(PistonMoveRules::isImmovable($nextBlock)){
						return false;
					}else{
						$toMoveMap[$nextKey] = $next;
						$queue[] = $next;
						if(count($toMoveMap) > self::MAX_BLOCK_PUSH_LIMIT){
							return false;
						}
					}
				}
			}

			// Check adhesion for Slime and Honey
			foreach(Facing::ALL as $side){
				$neighbor = $current->getSide($side);
				if($neighbor->equals($this->pistonPos)){
					continue;
				}
				$neighborKey = self::posKey($neighbor);
				if(isset($toMoveMap[$neighborKey]) || isset($toDestroyMap[$neighborKey])){
					continue;
				}

				$neighborBlock = $this->world->getBlockAt($neighbor->getFloorX(), $neighbor->getFloorY(), $neighbor->getFloorZ());
				if(PistonMoveRules::canStickTogether($currentBlock, $neighborBlock)){
					$toMoveMap[$neighborKey] = $neighbor;
					$queue[] = $neighbor;
					if(count($toMoveMap) > self::MAX_BLOCK_PUSH_LIMIT){
						return false;
					}
				}
			}
		}

		// Ensure every block in toMoveMap has valid space at its destination
		foreach($toMoveMap as $pos){
			$dest = $pos->getSide($dir);
			$destKey = self::posKey($dest);
			if(!isset($toMoveMap[$destKey]) && !isset($toDestroyMap[$destKey])){
				$destBlock = $this->world->getBlockAt($dest->getFloorX(), $dest->getFloorY(), $dest->getFloorZ());
				if(!$destBlock->canBeReplaced()){
					if(PistonMoveRules::isBreakableOnPush($destBlock)){
						$toDestroyMap[$destKey] = $dest;
					}else{
						return false;
					}
				}
			}
		}

		$this->toDestroy = array_values($toDestroyMap);

		// Sort toMove in reverse topological order (highest dot product with push direction vector moves first)
		$toMoveList = array_values($toMoveMap);
		usort($toMoveList, static function(Vector3 $a, Vector3 $b) use ($dir) : int{
			$dotA = self::dotFacing($a, $dir);
			$dotB = self::dotFacing($b, $dir);
			return $dotB <=> $dotA; // Descending
		});

		$this->toMove = $toMoveList;
		return true;
	}

	private function calculateRetraction() : bool{
		$pullDir = Facing::opposite($this->pistonFacing);
		$attachedPos = $this->pistonPos->getSide($this->pistonFacing, 2);
		$headPos = $this->pistonPos->getSide($this->pistonFacing);

		$attachedBlock = $this->world->getBlockAt($attachedPos->getFloorX(), $attachedPos->getFloorY(), $attachedPos->getFloorZ());
		if($attachedBlock->canBeReplaced() || PistonMoveRules::isImmovable($attachedBlock) || PistonMoveRules::isBreakableOnPush($attachedBlock)){
			return true; // Nothing pulled, arm retracts freely
		}

		/** @var array<string, Vector3> $toMoveMap */
		$toMoveMap = [];
		/** @var list<Vector3> $queue */
		$queue = [];

		$startKey = self::posKey($attachedPos);
		$toMoveMap[$startKey] = $attachedPos;
		$queue[] = $attachedPos;

		while(count($queue) > 0){
			$current = array_shift($queue);
			$currentBlock = $this->world->getBlockAt($current->getFloorX(), $current->getFloorY(), $current->getFloorZ());

			foreach(Facing::ALL as $side){
				$neighbor = $current->getSide($side);
				if($neighbor->equals($headPos) || $neighbor->equals($this->pistonPos)){
					continue;
				}
				$neighborKey = self::posKey($neighbor);
				if(isset($toMoveMap[$neighborKey])){
					continue;
				}

				$neighborBlock = $this->world->getBlockAt($neighbor->getFloorX(), $neighbor->getFloorY(), $neighbor->getFloorZ());
				if(PistonMoveRules::canStickTogether($currentBlock, $neighborBlock)){
					$toMoveMap[$neighborKey] = $neighbor;
					$queue[] = $neighbor;
					if(count($toMoveMap) > self::MAX_BLOCK_PUSH_LIMIT){
						// Overloaded sticky piston leaves blocks behind
						$this->toMove = [];
						return true;
					}
				}
			}
		}

		// Ensure destinations for all pulled blocks are clear
		foreach($toMoveMap as $pos){
			$dest = $pos->getSide($pullDir);
			if($dest->equals($headPos)){
				// Moving into the space vacated by the piston head
				continue;
			}
			$destKey = self::posKey($dest);
			if(!isset($toMoveMap[$destKey])){
				$destBlock = $this->world->getBlockAt($dest->getFloorX(), $dest->getFloorY(), $dest->getFloorZ());
				if(!$destBlock->canBeReplaced()){
					// Blocked: cannot pull
					$this->toMove = [];
					return true;
				}
			}
		}

		// Sort in reverse order of pull (highest dot product with pull direction moves first)
		$toMoveList = array_values($toMoveMap);
		usort($toMoveList, static function(Vector3 $a, Vector3 $b) use ($pullDir) : int{
			$dotA = self::dotFacing($a, $pullDir);
			$dotB = self::dotFacing($b, $pullDir);
			return $dotB <=> $dotA; // Descending
		});

		$this->toMove = $toMoveList;
		return true;
	}

	private static function dotFacing(Vector3 $pos, int $facing) : int{
		return match($facing){
			Facing::DOWN => -$pos->getFloorY(),
			Facing::UP => $pos->getFloorY(),
			Facing::NORTH => -$pos->getFloorZ(),
			Facing::SOUTH => $pos->getFloorZ(),
			Facing::WEST => -$pos->getFloorX(),
			Facing::EAST => $pos->getFloorX(),
			default => 0
		};
	}

	private static function posKey(Vector3 $pos) : string{
		return $pos->getFloorX() . ":" . $pos->getFloorY() . ":" . $pos->getFloorZ();
	}

	/**
	 * @return list<Vector3>
	 */
	public function getBlocksToMove() : array{
		return $this->toMove;
	}

	/**
	 * @return list<Vector3>
	 */
	public function getBlocksToDestroy() : array{
		return $this->toDestroy;
	}
}
