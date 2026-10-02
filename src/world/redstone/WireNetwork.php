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

namespace pocketmine\world\redstone;

use pocketmine\block\Block;
use pocketmine\block\RedstoneRepeater;
use pocketmine\block\RedstoneWire;
use pocketmine\block\utils\RedstoneSource;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\world\World;
use function array_pop;
use function count;
use function max;

/**
 * Power along redstone wire: a change to one wire recomputes its whole connected network at once, so a long line
 * settles in one step instead of one wire per tick.
 */
final class WireNetwork{
	private const MAX_WIRES = 4096;
	private const HORIZONTAL = [Facing::NORTH, Facing::SOUTH, Facing::WEST, Facing::EAST];

	/** @var array<int, true> wires whose network was already recomputed this tick */
	private array $done = [];

	public function __construct(
		private RedstoneEngine $engine,
		private World $world
	){}

	public function startTick() : void{
		$this->done = [];
	}

	/**
	 * Recomputes the power of every wire connected to this one: each wire has the strongest of the power it gets
	 * from non-wire sources and its connected wires' power minus one.
	 */
	public function update(RedstoneWire $start) : void{
		$pos = $start->getPosition();
		$x = $pos->getFloorX();
		$y = $pos->getFloorY();
		$z = $pos->getFloorZ();
		$startHash = World::blockHash($x, $y, $z);
		if(isset($this->done[$startHash])){
			return;
		}
		/** @var array<int, array{int, int, int}> $wires */
		$wires = [$startHash => [$x, $y, $z]];
		$pending = [$startHash];
		while($pending !== [] && count($wires) < self::MAX_WIRES){
			$hash = array_pop($pending);
			[$wx, $wy, $wz] = $wires[$hash];
			foreach($this->connectedWires($wx, $wy, $wz) as [$nx, $ny, $nz]){
				$next = World::blockHash($nx, $ny, $nz);
				if(!isset($wires[$next])){
					$wires[$next] = [$nx, $ny, $nz];
					$pending[] = $next;
				}
			}
		}

		//max-plus propagation, strongest first
		$power = [];
		/** @var array<int, list<int>> $buckets */
		$buckets = [];
		foreach($wires as $hash => [$wx, $wy, $wz]){
			$power[$hash] = $this->sourcePower($wx, $wy, $wz);
			if($power[$hash] > 0){
				$buckets[$power[$hash]][] = $hash;
			}
		}
		for($level = 15; $level > 1; --$level){
			foreach($buckets[$level] ?? [] as $hash){
				if($power[$hash] !== $level){
					continue;
				}
				[$wx, $wy, $wz] = $wires[$hash];
				foreach($this->connectedWires($wx, $wy, $wz) as [$nx, $ny, $nz]){
					$next = World::blockHash($nx, $ny, $nz);
					if(isset($power[$next]) && $power[$next] < $level - 1){
						$power[$next] = $level - 1;
						$buckets[$level - 1][] = $next;
					}
				}
			}
		}

		foreach($wires as $hash => [$wx, $wy, $wz]){
			$wire = $this->world->getBlockAt($wx, $wy, $wz);
			if($wire instanceof RedstoneWire && $wire->getOutputSignalStrength() !== $power[$hash]){
				$wirePos = new Vector3($wx, $wy, $wz);
				$this->world->setBlockAt($wx, $wy, $wz, $wire->setOutputSignalStrength($power[$hash]), false);
				$this->world->notifyNeighbourBlockUpdate($wirePos);
				$this->engine->requestAround($wirePos);
			}
			$this->done[$hash] = true;
		}
	}

	/**
	 * The horizontal directions a wire powers blocks in: towards what it connects to, straight through when it only
	 * connects on one side, and every direction when it connects to nothing.
	 *
	 * @return array<int, true>
	 */
	public function getDirections(RedstoneWire $wire) : array{
		$pos = $wire->getPosition();
		$x = $pos->getFloorX();
		$y = $pos->getFloorY();
		$z = $pos->getFloorZ();
		$openAbove = !RedstoneEngine::isConductor($this->world->getBlockAt($x, $y + 1, $z));
		$directions = [];
		foreach(self::HORIZONTAL as $face){
			[$dx, , $dz] = Facing::OFFSET[$face];
			$side = $this->world->getBlockAt($x + $dx, $y, $z + $dz);
			if(
				$side instanceof RedstoneWire || self::connectsToWire($side, $face) ||
				($openAbove && RedstoneEngine::isConductor($side) && $this->world->getBlockAt($x + $dx, $y + 1, $z + $dz) instanceof RedstoneWire) ||
				(!RedstoneEngine::isConductor($side) && $this->world->getBlockAt($x + $dx, $y - 1, $z + $dz) instanceof RedstoneWire)
			){
				$directions[$face] = true;
			}
		}
		if($directions === []){
			return [Facing::NORTH => true, Facing::SOUTH => true, Facing::WEST => true, Facing::EAST => true];
		}
		if(count($directions) === 1){
			foreach($directions as $face => $_){
				$directions[Facing::opposite($face)] = true;
			}
		}
		return $directions;
	}

	/**
	 * Wires connected to the wire at the position: beside it, one block up (if nothing solid is above this wire)
	 * and one block down (if the block beside it is not solid).
	 *
	 * @return list<array{int, int, int}>
	 */
	private function connectedWires(int $x, int $y, int $z) : array{
		$connected = [];
		$openAbove = !RedstoneEngine::isConductor($this->world->getBlockAt($x, $y + 1, $z));
		foreach(self::HORIZONTAL as $face){
			[$dx, , $dz] = Facing::OFFSET[$face];
			$side = $this->world->getBlockAt($x + $dx, $y, $z + $dz);
			if($side instanceof RedstoneWire){
				$connected[] = [$x + $dx, $y, $z + $dz];
				continue;
			}
			if($openAbove && RedstoneEngine::isConductor($side) && $this->world->getBlockAt($x + $dx, $y + 1, $z + $dz) instanceof RedstoneWire){
				$connected[] = [$x + $dx, $y + 1, $z + $dz];
			}
			if(!RedstoneEngine::isConductor($side) && $this->world->getBlockAt($x + $dx, $y - 1, $z + $dz) instanceof RedstoneWire){
				$connected[] = [$x + $dx, $y - 1, $z + $dz];
			}
		}
		return $connected;
	}

	/** Power a wire gets from anything but other wire: sources beside it, and strongly powered blocks. */
	private function sourcePower(int $x, int $y, int $z) : int{
		$power = 0;
		foreach(Facing::ALL as $face){
			[$dx, $dy, $dz] = Facing::OFFSET[$face];
			$neighbour = $this->world->getBlockAt($x + $dx, $y + $dy, $z + $dz);
			if($neighbour instanceof RedstoneWire){
				continue;
			}
			$power = max($power, $this->engine->getOutput($neighbour, Facing::opposite($face), false));
			if(RedstoneEngine::isConductor($neighbour)){
				$power = max($power, $this->engine->getPowerIntoBlock(new Vector3($x + $dx, $y + $dy, $z + $dz), true));
			}
			if($power >= 15){
				break;
			}
		}
		return $power;
	}

	/** Components a wire visually connects to, seen from a wire in direction $face. */
	private static function connectsToWire(Block $block, int $face) : bool{
		if($block instanceof RedstoneRepeater){
			return Facing::axis($block->getFacing()) === Facing::axis($face);
		}
		return $block instanceof RedstoneSource;
	}
}
