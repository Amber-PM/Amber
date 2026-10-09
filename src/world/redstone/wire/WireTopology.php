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

namespace pocketmine\world\redstone\wire;

use pocketmine\block\Block;
use pocketmine\block\RedstoneRepeater;
use pocketmine\block\RedstoneTorch;
use pocketmine\block\RedstoneWire;
use pocketmine\block\Slab;
use pocketmine\block\utils\RedstoneSource;
use pocketmine\block\utils\SlabType;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\world\redstone\RedstoneEngine;
use pocketmine\world\World;
use function count;

final class WireTopology{
	private const HORIZONTAL = [Facing::NORTH, Facing::SOUTH, Facing::WEST, Facing::EAST];
	public function __construct(private RedstoneEngine $engine, private World $world){}

	public function getDirections(RedstoneWire $wire) : array{
		$pos = $wire->getPosition();
		$x = $pos->getFloorX();
		$y = $pos->getFloorY();
		$z = $pos->getFloorZ();
		$directions = [];
		foreach(self::HORIZONTAL as $face){
			[$dx, , $dz] = Facing::OFFSET[$face];
			$side = $this->world->getBlockAt($x + $dx, $y, $z + $dz);
			if(
				$side instanceof RedstoneWire || self::connectsToWire($side, $face) ||
				($this->world->getBlockAt($x + $dx, $y + 1, $z + $dz) instanceof RedstoneWire && ($this->canTransmit($x, $y, $z, $x + $dx, $y + 1, $z + $dz) || $this->canTransmit($x + $dx, $y + 1, $z + $dz, $x, $y, $z))) ||
				($this->world->getBlockAt($x + $dx, $y - 1, $z + $dz) instanceof RedstoneWire && $this->canTransmit($x, $y, $z, $x + $dx, $y - 1, $z + $dz))
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

	private static function isSlab(Block $block) : bool{
		return $block instanceof Slab && $block->getSlabType() !== SlabType::DOUBLE;
	}

	public function canTransmit(int $x1, int $y1, int $z1, int $x2, int $y2, int $z2) : bool{
		$dy = $y2 - $y1;
		if($dy === 0){
			return true;
		}
		if($dy === 1){
			return !RedstoneEngine::isConductor($this->world->getBlockAt($x1, $y1 + 1, $z1));
		}
		if($dy === -1){
			$support = $this->world->getBlockAt($x1, $y1 - 1, $z1);
			$side = $this->world->getBlockAt($x2, $y1, $z2);
			return !self::isSlab($support) && !self::isSlab($side) && !RedstoneEngine::isConductor($side);
		}
		return false;
	}

	public function connectedWires(int $x, int $y, int $z) : array{
		$connected = [];
		foreach(self::HORIZONTAL as $face){
			[$dx, , $dz] = Facing::OFFSET[$face];
			$side = $this->world->getBlockAt($x + $dx, $y, $z + $dz);
			if($side instanceof RedstoneWire){
				$connected[] = [$x + $dx, $y, $z + $dz];
				continue;
			}
			$upper = $this->world->getBlockAt($x + $dx, $y + 1, $z + $dz);
			if($upper instanceof RedstoneWire && ($this->canTransmit($x, $y, $z, $x + $dx, $y + 1, $z + $dz) || $this->canTransmit($x + $dx, $y + 1, $z + $dz, $x, $y, $z))){
				$connected[] = [$x + $dx, $y + 1, $z + $dz];
			}
			$lower = $this->world->getBlockAt($x + $dx, $y - 1, $z + $dz);
			if($lower instanceof RedstoneWire && ($this->canTransmit($x, $y, $z, $x + $dx, $y - 1, $z + $dz) || $this->canTransmit($x + $dx, $y - 1, $z + $dz, $x, $y, $z))){
				$connected[] = [$x + $dx, $y - 1, $z + $dz];
			}
		}
		return $connected;
	}

	public function getTransmittedWireNeighbours(Vector3 $pos) : array{
		$x = $pos->getFloorX();
		$y = $pos->getFloorY();
		$z = $pos->getFloorZ();
		$result = [];
		foreach($this->connectedWires($x, $y, $z) as [$nx, $ny, $nz]){
			if($this->canTransmit($x, $y, $z, $nx, $ny, $nz)){
				$result[] = new Vector3($nx, $ny, $nz);
			}
		}
		return $result;
	}

	public function isSettlingAreaLoaded(int $x, int $z, int $radius) : bool{
		foreach([$x - $radius, $x + $radius] as $nx){
			foreach([$z - $radius, $z + $radius] as $nz){
				if(!$this->world->isChunkLoaded($nx >> 4, $nz >> 4)){
					return false;
				}
			}
		}
		return true;
	}

	public function isWireAreaLoaded(Vector3 $pos) : bool{
		$x = $pos->getFloorX();
		$z = $pos->getFloorZ();
		foreach([[$x, $z], [$x - 1, $z], [$x + 1, $z], [$x, $z - 1], [$x, $z + 1]] as [$nx, $nz]){
			if(!$this->world->isChunkLoaded($nx >> 4, $nz >> 4)){
				return false;
			}
		}
		return true;
	}

	public function getTorchSeedWires(RedstoneTorch $torch) : array{
		$result = [];
		foreach(Facing::ALL as $face){
			$pos = $torch->getPosition()->getSide($face);
			$block = $this->world->getBlockAt($pos->getFloorX(), $pos->getFloorY(), $pos->getFloorZ());
			if($torch->getRedstoneOutput($face, false, $this->engine) === 0){
				continue;
			}
			if($block instanceof RedstoneWire){
				$result[] = $pos;
			}elseif(RedstoneEngine::isConductor($block) && $torch->getRedstoneOutput($face, true, $this->engine) > 0){
				foreach(Facing::ALL as $side){
					$next = $pos->getSide($side);
					if($this->world->getBlockAt($next->getFloorX(), $next->getFloorY(), $next->getFloorZ()) instanceof RedstoneWire){
						$result[] = $next;
					}
				}
			}
		}
		return $result;
	}

	private static function connectsToWire(Block $block, int $face) : bool{
		if($block instanceof RedstoneRepeater){
			return Facing::axis($block->getFacing()) === Facing::axis($face);
		}
		return $block instanceof RedstoneSource;
	}
}
