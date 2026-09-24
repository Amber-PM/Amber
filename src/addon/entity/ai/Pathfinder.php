<?php

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketMine Team
 * @link http://www.pocketmine.net/
 *
 *
 */

declare(strict_types=1);

namespace pocketmine\addon\entity\ai;

use pocketmine\block\Block;
use pocketmine\block\Cactus;
use pocketmine\block\Door;
use pocketmine\block\Fence;
use pocketmine\block\FenceGate;
use pocketmine\block\Fire;
use pocketmine\block\Lava;
use pocketmine\block\Liquid;
use pocketmine\block\Magma;
use pocketmine\block\RuntimeBlockStateRegistry;
use pocketmine\block\SweetBerryBush;
use pocketmine\block\Wall;
use pocketmine\block\Water;
use pocketmine\math\Vector3;
use pocketmine\world\format\Chunk;
use pocketmine\world\World;
use function abs;
use function ceil;
use function count;
use function floor;
use function max;
use function sqrt;

/**
 * A* over the block grid for walking (and swimming) mobs.
 *
 * A node is the block a mob's feet stand in. A node is walkable when the mob's height fits in passable
 * blocks and the block below holds it up (or it is water and the mob can swim). A mob can step up one block,
 * drop up to its max fall distance, and cut corners only when both sides are free. The search is bounded by a
 * node budget; when the goal is not reached the path to the closest node found is returned, which is what a
 * mob does in the game (walk as close as it can).
 *
 * Block lookups are memoised for the duration of one search, since each block is read by several neighbours.
 */
final class Pathfinder{
	private const NEIGHBOURS = [[1, 0], [-1, 0], [0, 1], [0, -1], [1, 1], [1, -1], [-1, 1], [-1, -1]];

	/** Path searches allowed per server tick, across all mobs; the rest wait a tick. Keeps crowds from spiking a tick. */
	public const SEARCHES_PER_TICK = 16;

	private static int $budgetTick = -1;
	private static int $budgetUsed = 0;

	/** Takes one search from this tick's budget; false when it is used up. */
	public static function takeBudget(int $currentTick) : bool{
		if($currentTick !== self::$budgetTick){
			self::$budgetTick = $currentTick;
			self::$budgetUsed = 0;
		}
		return ++self::$budgetUsed <= self::SEARCHES_PER_TICK;
	}

	/** @var array<int, int> block hash => 0 passable, 1 solid, 2 water, 3 danger */
	private array $cache = [];

	public function __construct(
		private World $world,
		private int $height,
		private bool $canSwim,
		private bool $avoidWater,
		private int $maxFall = 3,
		private int $maxNodes = 400
	){}

	/**
	 * @return list<Vector3>|null block-centre waypoints from (not including) the start to the goal, or null
	 *                            when the start itself is not walkable or nothing closer was found
	 */
	public function find(Vector3 $from, Vector3 $to, float $maxDistance = 24.0) : ?array{
		$this->cache = [];
		$sx = (int) floor($from->x);
		$sy = (int) floor($from->y + 0.01);
		$sz = (int) floor($from->z);
		$gx = (int) floor($to->x);
		$gy = (int) floor($to->y + 0.01);
		$gz = (int) floor($to->z);
		if(abs($gx - $sx) > $maxDistance || abs($gz - $sz) > $maxDistance || abs($gy - $sy) > $maxDistance){
			return null;
		}
		//a goal inside the ground or in the air is moved to the nearest spot a mob can stand on; with none near, the
		//goal is unreachable and searching would only burn the whole node budget
		$ground = $this->groundLevel($gx, $gy, $gz);
		if($ground === null){
			$this->cache = [];
			return null;
		}
		$gy = $ground;

		$start = World::blockHash($sx, $sy, $sz);
		/** @var array<int, array{int, int, int}> $pos */
		$pos = [$start => [$sx, $sy, $sz]];
		/** @var array<int, float> $g */
		$g = [$start => 0.0];
		/** @var array<int, int> $parent */
		$parent = [];
		$closed = [];
		$open = new \SplPriorityQueue();
		$open->setExtractFlags(\SplPriorityQueue::EXTR_DATA);
		$open->insert($start, 0.0);
		$best = $start;
		$bestH = $this->h($sx, $sy, $sz, $gx, $gy, $gz);
		$expanded = 0;

		while(!$open->isEmpty() && $expanded < $this->maxNodes){
			/** @var int $current */
			$current = $open->extract();
			if(isset($closed[$current])){
				continue;
			}
			$closed[$current] = true;
			$expanded++;
			[$cx, $cy, $cz] = $pos[$current];
			if($cx === $gx && $cz === $gz && abs($cy - $gy) <= 1){
				$best = $current;
				break;
			}
			$h = $this->h($cx, $cy, $cz, $gx, $gy, $gz);
			if($h < $bestH){
				$bestH = $h;
				$best = $current;
			}
			foreach(self::NEIGHBOURS as [$dx, $dz]){
				$nx = $cx + $dx;
				$nz = $cz + $dz;
				if($dx !== 0 && $dz !== 0 && (!$this->isOpen($cx + $dx, $cy, $cz) || !$this->isOpen($cx, $cy, $cz + $dz))){
					continue; //no corner cutting through walls
				}
				$ny = $this->stepTarget($nx, $cy, $nz);
				if($ny === null){
					continue;
				}
				$next = World::blockHash($nx, $ny, $nz);
				if(isset($closed[$next])){
					continue;
				}
				$cost = ($dx !== 0 && $dz !== 0 ? 1.4142 : 1.0) + ($ny > $cy ? 0.5 : 0.0) + ($this->block($nx, $ny, $nz) === 2 ? ($this->avoidWater ? 8.0 : 1.0) : 0.0);
				$ng = $g[$current] + $cost;
				if(!isset($g[$next]) || $ng < $g[$next]){
					$g[$next] = $ng;
					$pos[$next] = [$nx, $ny, $nz];
					$parent[$next] = $current;
					$open->insert($next, -($ng + $this->h($nx, $ny, $nz, $gx, $gy, $gz)));
				}
			}
		}

		if($best === $start){
			return null;
		}
		$path = [];
		for($node = $best; $node !== $start; $node = $parent[$node]){
			[$x, $y, $z] = $pos[$node];
			$path[] = new Vector3($x + 0.5, $y, $z + 0.5);
		}
		$this->cache = [];
		return self::reverse($path);
	}

	/**
	 * @param list<Vector3> $path
	 * @return list<Vector3>
	 */
	private static function reverse(array $path) : array{
		$out = [];
		for($i = count($path) - 1; $i >= 0; --$i){
			$out[] = $path[$i];
		}
		return $out;
	}

	private function h(int $x, int $y, int $z, int $gx, int $gy, int $gz) : float{
		return sqrt(($x - $gx) ** 2 + ($z - $gz) ** 2) + abs($y - $gy) * 0.5;
	}

	/** Where a mob moving into column (x, z) from feet level y ends up, or null if it cannot. */
	private function stepTarget(int $x, int $y, int $z) : ?int{
		if($this->isStandable($x, $y, $z)){
			return $y;
		}
		//step up one block, with head room at the old column too
		if($this->block($x, $y, $z) === 1 && $this->isStandable($x, $y + 1, $z) && $this->fits($x, $y + 1, $z)){
			return $y + 1;
		}
		if(!$this->fits($x, $y, $z)){
			return null;
		}
		for($drop = 1; $drop <= $this->maxFall; ++$drop){
			if(!$this->fits($x, $y - $drop, $z)){
				return null;
			}
			if($this->isStandable($x, $y - $drop, $z)){
				return $y - $drop;
			}
		}
		return null;
	}

	/** The feet level nearest y in column (x, z) that a mob can stand at: first below (or at) y, then above. */
	private function groundLevel(int $x, int $y, int $z) : ?int{
		for($dy = 0; $dy <= 4; ++$dy){
			if($this->isStandable($x, $y - $dy, $z)){
				return $y - $dy;
			}
		}
		for($dy = 1; $dy <= 4; ++$dy){
			if($this->isStandable($x, $y + $dy, $z)){
				return $y + $dy;
			}
		}
		return null;
	}

	private function isStandable(int $x, int $y, int $z) : bool{
		if(!$this->fits($x, $y, $z)){
			return false;
		}
		$below = $this->block($x, $y - 1, $z);
		return $below === 1 || ($this->canSwim && $this->block($x, $y, $z) === 2) || ($below === 2 && $this->canSwim);
	}

	/** The mob's full height is free at feet level y. */
	private function fits(int $x, int $y, int $z) : bool{
		for($i = 0; $i < $this->height; ++$i){
			$type = $this->block($x, $y + $i, $z);
			if($type === 1 || $type === 3 || ($type === 2 && !$this->canSwim && $i > 0)){
				return false;
			}
		}
		return true;
	}

	private function isOpen(int $x, int $y, int $z) : bool{
		return $this->block($x, $y, $z) !== 1 && $this->block($x, $y + 1, $z) !== 1;
	}

	private function block(int $x, int $y, int $z) : int{
		$hash = World::blockHash($x, $y, $z);
		if(isset($this->cache[$hash])){
			return $this->cache[$hash];
		}
		if($y < $this->world->getMinY() || $y >= $this->world->getMaxY()){
			return $this->cache[$hash] = 1;
		}
		//read the raw state from the chunk (no Block object per probe) and classify each state once per process
		$chunk = $this->world->getChunk($x >> Chunk::COORD_BIT_SIZE, $z >> Chunk::COORD_BIT_SIZE);
		if($chunk === null){
			return $this->cache[$hash] = 1;
		}
		$state = $chunk->getBlockStateId($x & Chunk::COORD_MASK, $y, $z & Chunk::COORD_MASK);
		return $this->cache[$hash] = self::$stateClasses[$state] ??= self::classifyState($state);
	}

	/** @var array<int, int> block state id => 0 passable, 1 solid, 2 water, 3 danger */
	private static array $stateClasses = [];

	private static function classifyState(int $state) : int{
		try{
			return self::classify(RuntimeBlockStateRegistry::getInstance()->fromStateId($state));
		}catch(\Throwable){
			return 1; //a block whose shape depends on its surroundings: treat it as solid
		}
	}

	private static function classify(Block $block) : int{
		if($block instanceof Lava || $block instanceof Fire || $block instanceof Cactus || $block instanceof Magma || $block instanceof SweetBerryBush){
			return 3;
		}
		if($block instanceof Water){
			return 2;
		}
		if($block instanceof Liquid){
			return 3;
		}
		if($block instanceof Fence || $block instanceof Wall || ($block instanceof FenceGate && !$block->isOpen())){
			return 1; //1.5 blocks tall: cannot be stepped onto
		}
		if($block instanceof Door){
			return $block->isOpen() ? 0 : 1;
		}
		foreach($block->getCollisionBoxes() as $box){
			if($box->maxY - $box->minY > 0.2){
				return 1;
			}
		}
		return 0;
	}

	/** Blocks tall the mob is, for the height check. */
	public static function heightInBlocks(float $height) : int{
		return (int) max(1, ceil($height - 0.05));
	}
}
