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

namespace pocketmine\addon\entity\ai\goal;

use pocketmine\addon\entity\AddonEntity;
use pocketmine\addon\entity\ai\Goal;
use pocketmine\addon\script\ScriptHost;
use pocketmine\block\Block;
use pocketmine\block\Lava;
use pocketmine\block\Liquid;
use pocketmine\block\Water;
use pocketmine\math\Vector3;
use function array_is_list;
use function count;
use function floor;
use function in_array;
use function is_array;
use function is_string;
use function max;
use function min;
use function mt_rand;
use function str_contains;

/**
 * minecraft:behavior.move_to_block (walk to the nearest or a random listed block, fire on_reach, stay, fire
 * on_stay_completed), and the liquid seekers move_to_water, move_to_land, move_to_lava and move_to_liquid.
 */
final class MoveToBlockGoal extends Goal{
	public const BLOCKS = "block";
	public const WATER = "water";
	public const LAND = "land";
	public const LAVA = "lava";
	public const LIQUID = "liquid";

	private ?Vector3 $goal = null;
	private int $nextSearch = 0;
	private ?int $reachedAt = null;

	/** @param mixed[] $data */
	public function __construct(AddonEntity $mob, array $data, int $priority, private string $mode){
		parent::__construct($mob, $data, $priority);
	}

	public function getFlags() : int{ return self::FLAG_MOVE; }

	private function inLiquid() : bool{
		$pos = $this->mob->getPosition();
		return $this->mob->getWorld()->getBlockAt((int) floor($pos->x), (int) floor($pos->y + 0.1), (int) floor($pos->z)) instanceof Liquid;
	}

	/** @return list<string> */
	private function targetBlocks() : array{
		$list = $this->data["target_blocks"] ?? [];
		$out = [];
		foreach(is_array($list) ? (array_is_list($list) ? $list : [$list]) : [$list] as $entry){
			$name = is_array($entry) ? ($entry["name"] ?? null) : $entry;
			if(is_string($name)){
				$out[] = str_contains($name, ":") ? $name : "minecraft:$name";
			}
		}
		return $out;
	}

	private function matches(Block $block, Block $above, Block $below) : bool{
		return match($this->mode){
			self::WATER => $block instanceof Water,
			self::LAVA => $block instanceof Lava,
			self::LIQUID => $block instanceof Liquid,
			self::LAND => !$block->isSolid() && !$block instanceof Liquid && $below->isSolid() && !$below instanceof Liquid && !$above->isSolid(),
			default => in_array(ScriptHost::blockTypeId($block), $this->targetBlocks(), true),
		};
	}

	public function canStart() : bool{
		if($this->mob->isSitting() || $this->now() < $this->nextSearch){
			return false;
		}
		$this->nextSearch = $this->now() + max(1, (int) $this->float("tick_interval", 20));
		if($this->mode === self::LAND ? !$this->inLiquid() : ($this->mode !== self::BLOCKS && $this->inLiquid())){
			return false;
		}
		if($this->mode === self::BLOCKS && !$this->chance($this->float("start_chance", 1.0) / 20)){
			return false;
		}
		$this->goal = $this->search();
		return $this->goal !== null && $this->navigator()->moveTo($this->goal, $this->speedMultiplier(), $this->now());
	}

	private function search() : ?Vector3{
		$range = (int) min(16, max(1, $this->float("search_range", $this->mode === self::BLOCKS ? 8 : 16)));
		$height = (int) min(8, max(0, $this->float("search_height", $this->mode === self::BLOCKS ? 1 : 5)));
		$world = $this->mob->getWorld();
		$pos = $this->mob->getPosition()->floor();
		$random = ($this->data["target_selection_method"] ?? "nearest") === "random";
		//search_count: how many random blocks to look at per check, as in the game (0 = all of them)
		$samples = (int) $this->float("search_count", $this->mode === self::BLOCKS ? 0 : 10);
		if($samples > 0){
			for($i = 0; $i < $samples; $i++){
				$x = (int) $pos->x + mt_rand(-$range, $range);
				$y = (int) $pos->y + mt_rand(-$height, $height);
				$z = (int) $pos->z + mt_rand(-$range, $range);
				if($world->isInWorld($x, $y, $z) && $this->matches($world->getBlockAt($x, $y, $z), $world->getBlockAt($x, $y + 1, $z), $world->getBlockAt($x, $y - 1, $z))){
					return $this->standOn($x, $y, $z);
				}
			}
			return null;
		}
		$range = min($range, 8);
		$height = min($height, 2);
		$found = [];
		$best = null;
		$bestDist = \PHP_INT_MAX;
		for($dy = -$height; $dy <= $height; $dy++){
			for($dx = -$range; $dx <= $range; $dx++){
				for($dz = -$range; $dz <= $range; $dz++){
					$x = (int) $pos->x + $dx;
					$y = (int) $pos->y + $dy;
					$z = (int) $pos->z + $dz;
					if(!$world->isInWorld($x, $y, $z)){
						continue;
					}
					$block = $world->getBlockAt($x, $y, $z);
					if(!$this->matches($block, $world->getBlockAt($x, $y + 1, $z), $world->getBlockAt($x, $y - 1, $z))){
						continue;
					}
					$candidate = $this->standOn($x, $y, $z);
					if($random){
						$found[] = $candidate;
						continue;
					}
					$dist = $dx * $dx + $dy * $dy + $dz * $dz;
					if($dist < $bestDist){
						$best = $candidate;
						$bestDist = $dist;
					}
				}
			}
		}
		return $random ? ($found === [] ? null : $found[mt_rand(0, count($found) - 1)]) : $best;
	}

	/** Where the mob goes for a block: into it (water, flowers), or on top of it when it is solid. */
	private function standOn(int $x, int $y, int $z) : Vector3{
		return new Vector3($x + 0.5, $this->mob->getWorld()->getBlockAt($x, $y, $z)->isSolid() ? $y + 1 : $y, $z + 0.5);
	}

	public function start() : void{
		$this->reachedAt = null;
	}

	public function canContinue() : bool{
		if($this->goal === null){
			return false;
		}
		if($this->reachedAt !== null){
			return $this->now() < $this->reachedAt + (int) ($this->float("stay_duration", 0) * 20);
		}
		return $this->navigator()->isMoving() || $this->reached();
	}

	private function reached() : bool{
		$goal = $this->goal;
		return $goal !== null && $goal->distance($this->mob->getPosition()) <= $this->float("goal_radius", 0.5) + 1.0;
	}

	public function tick() : void{
		if($this->reachedAt === null && $this->reached()){
			$this->reachedAt = $this->now();
			$this->navigator()->stop();
			$reach = $this->data["on_reach"] ?? null;
			foreach(is_array($reach) && array_is_list($reach) ? $reach : [$reach] as $event){
				$this->mob->triggerEventDefinition($event);
			}
		}
	}

	public function stop() : void{
		if($this->reachedAt !== null && ($this->float("stay_duration", 0) > 0)){
			$this->mob->triggerEventDefinition($this->data["on_stay_completed"] ?? null);
		}
		$this->goal = null;
		$this->navigator()->stop();
	}
}
