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
use pocketmine\block\Redstone;
use pocketmine\block\utils\RedstoneSource;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\world\World;
use function max;

final class RedstonePower{
	public function __construct(private RedstoneEngine $engine, private World $world){}

	public static function isConductor(Block $block) : bool{
		return $block->isSolid() && !$block->isTransparent() && $block->isFullCube() && !$block instanceof Redstone;
	}

	public function getOutput(Block $block, int $face, bool $strongOnly) : int{
		return $block instanceof RedstoneSource ? $block->getRedstoneOutput($face, $strongOnly, $this->engine) : 0;
	}

	public function getPowerIntoBlock(Vector3 $pos, bool $strongOnly) : int{
		$x = $pos->getFloorX();
		$y = $pos->getFloorY();
		$z = $pos->getFloorZ();
		$power = 0;
		foreach(Facing::ALL as $face){
			[$dx, $dy, $dz] = Facing::OFFSET[$face];
			if(!$this->world->isInWorld($x + $dx, $y + $dy, $z + $dz) || !$this->world->isChunkLoaded(($x + $dx) >> 4, ($z + $dz) >> 4)){
				continue;
			}
			$power = max($power, $this->getOutput($this->world->getBlockAt($x + $dx, $y + $dy, $z + $dz), Facing::opposite($face), $strongOnly));
			if($power >= 15){
				break;
			}
		}
		return $power;
	}

	public function getReceivedPower(Vector3 $pos) : int{
		$power = 0;
		foreach(Facing::ALL as $face){
			$power = max($power, $this->getPowerFrom($pos->getSide($face), Facing::opposite($face)));
			if($power >= 15){
				break;
			}
		}
		return $power;
	}

	public function getPowerFrom(Vector3 $from, int $face) : int{
		if(!$this->world->isInWorld($from->getFloorX(), $from->getFloorY(), $from->getFloorZ()) || !$this->world->isChunkLoaded($from->getFloorX() >> 4, $from->getFloorZ() >> 4)){
			return 0;
		}
		$block = $this->world->getBlockAt($from->getFloorX(), $from->getFloorY(), $from->getFloorZ());
		$power = $this->getOutput($block, $face, false);
		if($power < 15 && self::isConductor($block)){
			$power = max($power, $this->getPowerIntoBlock($from, false));
		}
		return $power;
	}
}
