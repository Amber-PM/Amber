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

use pocketmine\block\Block;
use pocketmine\block\BlockTypeIds;
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

	 //calcs the list of blocks to move and destroy. Returns false if the piston push/pull is blocked
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
		$startBlock = $this->getAvailableBlock($headPos);
		if($startBlock === null){
			return false;
		}

		if(PistonMoveRules::isImmovable($startBlock)){
			return false;
		}

		if(PistonMoveRules::isBreakableOnPush($startBlock)){
			$this->toDestroy[] = $headPos;
			return true;
		}

		if($startBlock->canBeReplaced()){
			return true;
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
			$currentBlock = $this->getAvailableBlock($current);
			if($currentBlock === null){
				return false;
			}

			// check block directly in front in push direction
			$next = $current->getSide($dir);
			if($next->equals($this->pistonPos)){
				return false; // cant push into piston base
			}

			$nextKey = self::posKey($next);
			if(!isset($toMoveMap[$nextKey]) && !isset($toDestroyMap[$nextKey])){
				$nextBlock = $this->getAvailableBlock($next);
				if($nextBlock === null){
					return false;
				}
				if(PistonMoveRules::isImmovable($nextBlock)){
					return false;
				}
				if(!$nextBlock->canBeReplaced() || PistonMoveRules::isBreakableOnPush($nextBlock)){
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

			//check adhesion for Slime and Honey
			foreach(PistonMoveRules::isAdhesive($currentBlock) ? Facing::ALL : [] as $side){
				$neighbor = $current->getSide($side);
				if($neighbor->equals($this->pistonPos)){
					continue;
				}
				$neighborKey = self::posKey($neighbor);
				if(isset($toMoveMap[$neighborKey]) || isset($toDestroyMap[$neighborKey])){
					continue;
				}

				$neighborBlock = $this->getAvailableBlock($neighbor);
				if($neighborBlock === null){
					if($this->world->isInWorld($neighbor->getFloorX(), $neighbor->getFloorY(), $neighbor->getFloorZ()) && ($currentBlock->getTypeId() === BlockTypeIds::SLIME || $currentBlock->getTypeId() === BlockTypeIds::HONEY_BLOCK)){
						return false;
					}
					continue;
				}
				if(PistonMoveRules::canStickTogether($currentBlock, $neighborBlock)){
					$toMoveMap[$neighborKey] = $neighbor;
					$queue[] = $neighbor;
					if(count($toMoveMap) > self::MAX_BLOCK_PUSH_LIMIT){
						return false;
					}
				}
			}
		}

		// ensure every block in toMoveMap has valid space at its destination
		foreach($toMoveMap as $pos){
			$dest = $pos->getSide($dir);
			$destKey = self::posKey($dest);
			if(!isset($toMoveMap[$destKey]) && !isset($toDestroyMap[$destKey])){
				$destBlock = $this->getAvailableBlock($dest);
				if($destBlock === null){
					return false;
				}
				if(PistonMoveRules::isImmovable($destBlock)){
					return false;
				}
				if(!$destBlock->canBeReplaced() || PistonMoveRules::isBreakableOnPush($destBlock)){
					if(PistonMoveRules::isBreakableOnPush($destBlock)){
						$toDestroyMap[$destKey] = $dest;
					}else{
						return false;
					}
				}
			}
		}

		$this->toDestroy = array_values($toDestroyMap);

		// sorts toMove in reverse topological order (highest dot product with push direction vector moves first)
		$toMoveList = array_values($toMoveMap);
		usort($toMoveList, static function(Vector3 $a, Vector3 $b) use ($dir) : int{
			$dotA = self::dotFacing($a, $dir);
			$dotB = self::dotFacing($b, $dir);
			return $dotB <=> $dotA; // descending
		});

		$this->toMove = $toMoveList;
		return true;
	}

	private function calculateRetraction() : bool{
		$pullDir = Facing::opposite($this->pistonFacing);
		$attachedPos = $this->pistonPos->getSide($this->pistonFacing, 2);
		$headPos = $this->pistonPos->getSide($this->pistonFacing);

		$attachedBlock = $this->getAvailableBlock($attachedPos);
		if($attachedBlock === null){
			return true;
		}
		if($attachedBlock->canBeReplaced() || PistonMoveRules::isImmovable($attachedBlock) || PistonMoveRules::isBreakableOnPush($attachedBlock) || PistonMoveRules::isPushOnly($attachedBlock)){
			return true; // nothing pulled, arm retracts freely
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
			$currentBlock = $this->getAvailableBlock($current);
			if($currentBlock === null){
				return true;
			}

			foreach(PistonMoveRules::isAdhesive($currentBlock) ? Facing::ALL : [] as $side){
				$neighbor = $current->getSide($side);
				if($neighbor->equals($headPos) || $neighbor->equals($this->pistonPos)){
					continue;
				}
				$neighborKey = self::posKey($neighbor);
				if(isset($toMoveMap[$neighborKey])){
					continue;
				}

				$neighborBlock = $this->getAvailableBlock($neighbor);
				if($neighborBlock === null){
					if($this->world->isInWorld($neighbor->getFloorX(), $neighbor->getFloorY(), $neighbor->getFloorZ()) && ($currentBlock->getTypeId() === BlockTypeIds::SLIME || $currentBlock->getTypeId() === BlockTypeIds::HONEY_BLOCK)){
						return true;
					}
					continue;
				}
				if(PistonMoveRules::canStickTogether($currentBlock, $neighborBlock)){
					$toMoveMap[$neighborKey] = $neighbor;
					$queue[] = $neighbor;
					if(count($toMoveMap) > self::MAX_BLOCK_PUSH_LIMIT){
						// overloaded sticky piston leaves blocks behind
						$this->toMove = [];
						return true;
					}
				}
			}
		}

		foreach($toMoveMap as $pos){
			$dest = $pos->getSide($pullDir);
			if($dest->equals($headPos)){
				continue;
			}
			$destKey = self::posKey($dest);
			if(!isset($toMoveMap[$destKey])){
				$destBlock = $this->getAvailableBlock($dest);
				if($destBlock === null){
					return true;
				}
				if(!$destBlock->canBeReplaced() || PistonMoveRules::isImmovable($destBlock)){
					// blocked = cannot pull
					$this->toMove = [];
					return true;
				}
			}
		}

		//sorts in reverse order of pull (highest dot product with pull direction moves first)
		$toMoveList = array_values($toMoveMap);
		usort($toMoveList, static function(Vector3 $a, Vector3 $b) use ($pullDir) : int{
			$dotA = self::dotFacing($a, $pullDir);
			$dotB = self::dotFacing($b, $pullDir);
			return $dotB <=> $dotA; // descending
		});

		$this->toMove = $toMoveList;
		return true;
	}

	private function getAvailableBlock(Vector3 $pos) : ?Block{
		$x = $pos->getFloorX();
		$y = $pos->getFloorY();
		$z = $pos->getFloorZ();
		if(!$this->world->isInWorld($x, $y, $z) || !$this->world->isChunkLoaded($x >> 4, $z >> 4)){
			return null;
		}
		return $this->world->getBlockAt($x, $y, $z);
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
