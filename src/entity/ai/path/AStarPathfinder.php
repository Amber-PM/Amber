<?php

declare(strict_types=1);

namespace pocketmine\entity\ai\path;

use pocketmine\block\Air;
use pocketmine\block\Block;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\Cactus;
use pocketmine\block\Fire;
use pocketmine\block\Flowable;
use pocketmine\block\Lava;
use pocketmine\block\Liquid;
use pocketmine\block\SweetBerryBush;
use pocketmine\block\Water;
use pocketmine\math\Vector3;
use pocketmine\world\World;
use SplPriorityQueue;
use function abs;
use function array_reverse;
use function floor;
use function sqrt;

final class AStarPathfinder{

	public function __construct(
		private World $world,
		private int $maxNodes = 400,
		private int $maxDrop = 3,
		private int $maxClimb = 1
	){}

	public function findPath(
		Vector3 $start,
		Vector3 $target,
		float $entityHeight = 2.0,
		float $entityWidth = 0.6,
		bool $canSwim = true
	) : ?Path{
		$startX = (int) floor($start->x);
		$startY = (int) floor($start->y);
		$startZ = (int) floor($start->z);

		$targetX = (int) floor($target->x);
		$targetY = (int) floor($target->y);
		$targetZ = (int) floor($target->z);

		// Snap start to standing level if feet are not on solid
		$startY = $this->findStandingY($startX, $startY, $startZ, $canSwim);
		if($startY === null){
			return null;
		}

		$startNode = new Node($startX, $startY, $startZ);
		$startNode->gCost = 0.0;
		$startNode->hCost = $this->calculateHeuristic($startX, $startY, $startZ, $targetX, $targetY, $targetZ);
		$startNode->fCost = $startNode->hCost;

		/** @var SplPriorityQueue<int, Node> $openSet */
		$openSet = new SplPriorityQueue();
		// In PHP SplPriorityQueue, higher values are extracted first. Negate fCost for min-heap behavior.
		$openSet->insert($startNode, (int) (-$startNode->fCost * 1000));

		/** @var array<string, Node> $allNodes */
		$allNodes = [Node::hash($startX, $startY, $startZ) => $startNode];
		/** @var array<string, bool> $closedSet */
		$closedSet = [];

		$iterations = 0;
		$closestNode = $startNode;
		$closestDist = $startNode->hCost;

		while(!$openSet->isEmpty() && $iterations < $this->maxNodes){
			$iterations++;
			/** @var Node $current */
			$current = $openSet->extract();
			$currentHash = Node::hash($current->x, $current->y, $current->z);

			if(isset($closedSet[$currentHash])){
				continue;
			}
			$closedSet[$currentHash] = true;

			// Check if target reached (or adjacent target)
			if($current->x === $targetX && $current->y === $targetY && $current->z === $targetZ){
				return $this->buildPath($current, $target);
			}

			if($current->hCost < $closestDist){
				$closestDist = $current->hCost;
				$closestNode = $current;
			}

			// Explore neighbors
			$neighbors = $this->getValidNeighbors($current, $entityHeight, $canSwim);
			foreach($neighbors as $neighbor){
				$neighborHash = Node::hash($neighbor->x, $neighbor->y, $neighbor->z);
				if(isset($closedSet[$neighborHash])){
					continue;
				}

				$tentativeG = $current->gCost + $this->getStepCost($current, $neighbor);

				$existing = $allNodes[$neighborHash] ?? null;
				if($existing === null || $tentativeG < $existing->gCost){
					$nodeToUse = $existing ?? $neighbor;
					$nodeToUse->parent = $current;
					$nodeToUse->gCost = $tentativeG;
					$nodeToUse->hCost = $this->calculateHeuristic($nodeToUse->x, $nodeToUse->y, $nodeToUse->z, $targetX, $targetY, $targetZ);
					$nodeToUse->fCost = $nodeToUse->gCost + $nodeToUse->hCost;

					$allNodes[$neighborHash] = $nodeToUse;
					$openSet->insert($nodeToUse, (int) (-$nodeToUse->fCost * 1000));
				}
			}
		}

		// If exact target unreachable, check if we got close enough (within 1.5 blocks)
		if($closestNode !== $startNode && $closestDist <= 1.5){
			return $this->buildPath($closestNode, $target);
		}

		return null;
	}

	private function calculateHeuristic(int $x1, int $y1, int $z1, int $x2, int $y2, int $z2) : float{
		$dx = $x1 - $x2;
		$dy = $y1 - $y2;
		$dz = $z1 - $z2;
		return sqrt($dx * $dx + $dy * $dy + $dz * $dz);
	}

	private function getStepCost(Node $from, Node $to) : float{
		$dx = abs($from->x - $to->x);
		$dy = abs($from->y - $to->y);
		$dz = abs($from->z - $to->z);

		$base = ($dx > 0 && $dz > 0) ? 1.414 : 1.0;
		if($dy > 0){
			$base += 0.5 * $dy; // Jumping/climbing penalty
		}
		return $base;
	}

	/**
	 * @return Node[]
	 */
	private function getValidNeighbors(Node $current, float $entityHeight, bool $canSwim) : array{
		$neighbors = [];
		$directions = [
			[1, 0],
			[-1, 0],
			[0, 1],
			[0, -1]
		];

		foreach($directions as [$dx, $dz]){
			$nx = $current->x + $dx;
			$nz = $current->z + $dz;

			// 1. Same level
			if($this->isWalkablePosition($nx, $current->y, $nz, $entityHeight, $canSwim)){
				$neighbors[] = new Node($nx, $current->y, $nz);
				continue;
			}

			// 2. Step up (Jump)
			for($step = 1; $step <= $this->maxClimb; ++$step){
				$ny = $current->y + $step;
				// Must have headroom above current position to jump
				if($this->isPassableSpace($current->x, $current->y + (int) $entityHeight, $current->z)){
					if($this->isWalkablePosition($nx, $ny, $nz, $entityHeight, $canSwim)){
						$neighbors[] = new Node($nx, $ny, $nz);
						break;
					}
				}
			}

			// 3. Step down (Drop)
			for($drop = 1; $drop <= $this->maxDrop; ++$drop){
				$ny = $current->y - $drop;
				if($this->isWalkablePosition($nx, $ny, $nz, $entityHeight, $canSwim)){
					$neighbors[] = new Node($nx, $ny, $nz);
					break;
				}
			}
		}

		return $neighbors;
	}

	private function isWalkablePosition(int $x, int $y, int $z, float $entityHeight, bool $canSwim) : bool{
		// Block under feet must be solid/supporting (or water if swimming)
		$ground = $this->world->getBlockAt($x, $y - 1, $z);
		$inGround = $this->world->getBlockAt($x, $y, $z);

		if($this->isHazard($inGround) || $this->isHazard($ground)){
			return false;
		}

		$isSwimming = $canSwim && ($inGround instanceof Water || $ground instanceof Water);
		if(!$isSwimming && !$ground->isSolid()){
			return false;
		}

		// Check headroom
		$requiredHeadroom = (int) $entityHeight;
		for($h = 0; $h < $requiredHeadroom; ++$h){
			if(!$this->isPassableSpace($x, $y + $h, $z)){
				return false;
			}
		}

		return true;
	}

	private function isPassableSpace(int $x, int $y, int $z) : bool{
		$block = $this->world->getBlockAt($x, $y, $z);
		if($this->isHazard($block)){
			return false;
		}
		if($block instanceof Air || $block instanceof Flowable || $block instanceof Liquid){
			return true;
		}
		return !$block->isSolid();
	}

	private function isHazard(Block $block) : bool{
		if($block instanceof Lava || $block instanceof Fire){
			return true;
		}
		$typeId = $block->getTypeId();
		return $typeId === BlockTypeIds::CACTUS || $typeId === BlockTypeIds::SWEET_BERRY_BUSH;
	}

	private function findStandingY(int $x, int $y, int $z, bool $canSwim) : ?int{
		for($offset = 0; $offset <= 3; ++$offset){
			$testY = $y - $offset;
			if($this->isWalkablePosition($x, $testY, $z, 1.0, $canSwim)){
				return $testY;
			}
			$testYUp = $y + $offset;
			if($this->isWalkablePosition($x, $testYUp, $z, 1.0, $canSwim)){
				return $testYUp;
			}
		}
		return null;
	}

	private function buildPath(Node $endNode, Vector3 $target) : Path{
		$nodes = [];
		$curr = $endNode;
		while($curr !== null){
			$nodes[] = $curr;
			$curr = $curr->parent;
		}
		$nodes = array_reverse($nodes);
		return new Path($nodes, $target);
	}
}
