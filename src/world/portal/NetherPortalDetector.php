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

namespace pocketmine\world\portal;

use pocketmine\block\Block;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\VanillaBlocks;
use pocketmine\math\Axis;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\world\sound\NetherPortalSpawnSound;
use pocketmine\world\World;

final class NetherPortalDetector{

	public const MIN_WIDTH = 2;
	public const MAX_WIDTH = 21;
	public const MIN_HEIGHT = 3;
	public const MAX_HEIGHT = 21;

	private function __construct(){
		// NOOP
	}

	public static function tryActivate(World $world, Vector3 $triggerPos) : bool{
		$detection = self::detect($world, $triggerPos);
		if($detection === null){
			return false;
		}

		return self::activate($world, $detection);
	}

	public static function detect(World $world, Vector3 $pos) : ?NetherPortalDetection{
		$x = $pos->getFloorX();
		$y = $pos->getFloorY();
		$z = $pos->getFloorZ();

		$block = $world->getBlockAt($x, $y, $z);
		if($block->getTypeId() === BlockTypeIds::OBSIDIAN){
			// When triggered on the frame (e.g. flint & steel clicked on obsidian), check adjacent inner candidate blocks
			foreach(Facing::ALL as $face){
				$side = $pos->getSide($face);
				$sx = $side->getFloorX();
				$sy = $side->getFloorY();
				$sz = $side->getFloorZ();
				$sideBlock = $world->getBlockAt($sx, $sy, $sz);
				if(self::isInnerCandidate($sideBlock)){
					$detection = self::detectFromInnerCandidate($world, $sx, $sy, $sz);
					if($detection !== null){
						return $detection;
					}
				}
			}
			return null;
		}

		if(self::isInnerCandidate($block)){
			return self::detectFromInnerCandidate($world, $x, $y, $z);
		}

		return null;
	}

	private static function isInnerCandidate(Block $block) : bool{
		$id = $block->getTypeId();
		return $id === BlockTypeIds::AIR || $id === BlockTypeIds::FIRE || $id === BlockTypeIds::NETHER_PORTAL;
	}

	private static function isObsidian(Block $block) : bool{
		return $block->getTypeId() === BlockTypeIds::OBSIDIAN;
	}

	private static function detectFromInnerCandidate(World $world, int $x, int $y, int $z) : ?NetherPortalDetection{
		// Try Axis::X first (width along X, constant Z)
		$xDetection = self::detectForAxis($world, $x, $y, $z, Axis::X);
		if($xDetection !== null){
			return $xDetection;
		}

		// Try Axis::Z next (width along Z, constant X)
		return self::detectForAxis($world, $x, $y, $z, Axis::Z);
	}

	public static function detectForAxis(World $world, int $x0, int $y0, int $z0, int $axis) : ?NetherPortalDetection{
		$h0 = $axis === Axis::X ? $x0 : $z0;
		$c = $axis === Axis::X ? $z0 : $x0;

		$getBlock = function(int $h, int $y) use ($world, $c, $axis) : Block{
			return $axis === Axis::X ? $world->getBlockAt($h, $y, $c) : $world->getBlockAt($c, $y, $h);
		};

		// 1. Initial candidate check
		if(!self::isInnerCandidate($getBlock($h0, $y0))){
			return null;
		}

		// 2. Find bottom row ($yMin)
		$y = $y0;
		$downSteps = 0;
		while($y >= World::Y_MIN && self::isInnerCandidate($getBlock($h0, $y))){
			$downSteps++;
			if($downSteps > self::MAX_HEIGHT){
				return null;
			}
			$y--;
		}

		if($y < World::Y_MIN || !self::isObsidian($getBlock($h0, $y))){
			return null;
		}
		$yMin = $y + 1;

		// 3. Find horizontal span [$hMin, $hMax] at $yMin
		// Negative direction
		$h = $h0;
		$hMin = null;
		while(true){
			$b = $getBlock($h, $yMin);
			if(self::isObsidian($b)){
				$hMin = $h + 1;
				break;
			}
			if(!self::isInnerCandidate($b)){
				return null;
			}
			if(!self::isObsidian($getBlock($h, $yMin - 1))){
				return null;
			}
			if(($h0 - $h) > self::MAX_WIDTH){
				return null;
			}
			$h--;
		}

		// Positive direction
		$h = $h0;
		$hMax = null;
		while(true){
			$b = $getBlock($h, $yMin);
			if(self::isObsidian($b)){
				$hMax = $h - 1;
				break;
			}
			if(!self::isInnerCandidate($b)){
				return null;
			}
			if(!self::isObsidian($getBlock($h, $yMin - 1))){
				return null;
			}
			if(($h - $h0) > self::MAX_WIDTH){
				return null;
			}
			$h++;
		}

		// 4. Validate width
		$width = $hMax - $hMin + 1;
		if($width < self::MIN_WIDTH || $width > self::MAX_WIDTH){
			return null;
		}

		// 5. Scan upwards to find $yMax and verify side frames and top frame
		$yMax = null;
		for($scanY = $yMin; $scanY <= $yMin + self::MAX_HEIGHT; ++$scanY){
			if($scanY > World::Y_MAX){
				return null;
			}

			$leftSide = $getBlock($hMin - 1, $scanY);
			$rightSide = $getBlock($hMax + 1, $scanY);

			$allInnerAreCandidate = true;
			for($scanH = $hMin; $scanH <= $hMax; ++$scanH){
				if(!self::isInnerCandidate($getBlock($scanH, $scanY))){
					$allInnerAreCandidate = false;
					break;
				}
			}

			if($allInnerAreCandidate && self::isObsidian($leftSide) && self::isObsidian($rightSide)){
				// Valid inner row, continue upward
				continue;
			}

			// Check if this row is the top obsidian frame
			$allObsidianTop = true;
			for($scanH = $hMin; $scanH <= $hMax; ++$scanH){
				if(!self::isObsidian($getBlock($scanH, $scanY))){
					$allObsidianTop = false;
					break;
				}
			}

			if($allObsidianTop){
				$yMax = $scanY - 1;
				break;
			}

			return null;
		}

		if($yMax === null){
			return null;
		}

		// 6. Validate height
		$height = $yMax - $yMin + 1;
		if($height < self::MIN_HEIGHT || $height > self::MAX_HEIGHT){
			return null;
		}

		return new NetherPortalDetection($axis, $hMin, $hMax, $yMin, $yMax, $c);
	}

	public static function activate(World $world, NetherPortalDetection $detection) : bool{
		$axis = $detection->getAxis();
		$portalBlock = VanillaBlocks::NETHER_PORTAL()->setAxis($axis);

		for($y = $detection->getYMin(); $y <= $detection->getYMax(); ++$y){
			for($h = $detection->getHMin(); $h <= $detection->getHMax(); ++$h){
				$x = $axis === Axis::X ? $h : $detection->getC();
				$z = $axis === Axis::X ? $detection->getC() : $h;
				$world->setBlockAt($x, $y, $z, $portalBlock, false);
			}
		}

		$soundPos = $detection->getCenter();
		$world->addSound($soundPos, new NetherPortalSpawnSound());

		return true;
	}
}
