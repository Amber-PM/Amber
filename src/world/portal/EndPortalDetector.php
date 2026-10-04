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

use pocketmine\block\EndPortalFrame;
use pocketmine\block\VanillaBlocks;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\world\sound\EndPortalSpawnSound;
use pocketmine\world\World;

final class EndPortalDetector{

	private function __construct(){
		// NOOP
	}

	/**
	 * Attempts to find and activate a 3x3 End Portal given any position near or on the frame.
	 */
	public static function tryActivate(World $world, Vector3 $triggerPos) : bool{
		$center = self::findPortalCenter($world, $triggerPos);
		if($center === null){
			return false;
		}

		return self::activatePortal($world, $center);
	}

	/**
	 * Checks if a given center (Vector3) is surrounded by a complete 3x3 End Portal frame.
	 */
	public static function isFrameComplete(World $world, Vector3 $center) : bool{
		$y = $center->getFloorY();
		$cx = $center->getFloorX();
		$cz = $center->getFloorZ();

		// North side (Z = cz - 2): X from cx - 1 to cx + 1, facing SOUTH
		for($x = $cx - 1; $x <= $cx + 1; ++$x){
			$block = $world->getBlockAt($x, $y, $cz - 2);
			if(!$block instanceof EndPortalFrame || !$block->hasEye() || $block->getFacing() !== Facing::SOUTH){
				return false;
			}
		}

		// South side (Z = cz + 2): X from cx - 1 to cx + 1, facing NORTH
		for($x = $cx - 1; $x <= $cx + 1; ++$x){
			$block = $world->getBlockAt($x, $y, $cz + 2);
			if(!$block instanceof EndPortalFrame || !$block->hasEye() || $block->getFacing() !== Facing::NORTH){
				return false;
			}
		}

		// West side (X = cx - 2): Z from cz - 1 to cz + 1, facing EAST
		for($z = $cz - 1; $z <= $cz + 1; ++$z){
			$block = $world->getBlockAt($cx - 2, $y, $z);
			if(!$block instanceof EndPortalFrame || !$block->hasEye() || $block->getFacing() !== Facing::EAST){
				return false;
			}
		}

		// East side (X = cx + 2): Z from cz - 1 to cz + 1, facing WEST
		for($z = $cz - 1; $z <= $cz + 1; ++$z){
			$block = $world->getBlockAt($cx + 2, $y, $z);
			if(!$block instanceof EndPortalFrame || !$block->hasEye() || $block->getFacing() !== Facing::WEST){
				return false;
			}
		}

		return true;
	}

	/**
	 * Fills the 3x3 center with EndPortal blocks.
	 */
	public static function activatePortal(World $world, Vector3 $center) : bool{
		if(!self::isFrameComplete($world, $center)){
			return false;
		}

		$y = $center->getFloorY();
		$cx = $center->getFloorX();
		$cz = $center->getFloorZ();

		for($x = $cx - 1; $x <= $cx + 1; ++$x){
			for($z = $cz - 1; $z <= $cz + 1; ++$z){
				$world->setBlockAt($x, $y, $z, VanillaBlocks::END_PORTAL());
			}
		}

		$world->addSound($center->add(0.5, 0.5, 0.5), new EndPortalSpawnSound());
		return true;
	}

	/**
	 * Searches surrounding potential center coordinates for a candidate 3x3 frame.
	 */
	public static function findPortalCenter(World $world, Vector3 $framePos) : ?Vector3{
		$fx = $framePos->getFloorX();
		$fy = $framePos->getFloorY();
		$fz = $framePos->getFloorZ();

		// The center can be offset by -2 to +2 in X and Z
		for($dx = -2; $dx <= 2; ++$dx){
			for($dz = -2; $dz <= 2; ++$dz){
				if($dx === 0 && $dz === 0){
					continue;
				}
				$candidate = new Vector3($fx + $dx, $fy, $fz + $dz);
				if(self::isFrameComplete($world, $candidate)){
					return $candidate;
				}
			}
		}

		return null;
	}
}
