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

use pocketmine\block\BlockTypeIds;
use pocketmine\block\NetherPortal;
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\Entity;
use pocketmine\math\Axis;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\types\DimensionIds;
use pocketmine\world\Position;
use pocketmine\world\World;
use function floor;
use function max;
use function min;

class NetherPortalRouter{

	public const OVERWORLD_SEARCH_RADIUS = 128;
	public const NETHER_SEARCH_RADIUS = 16;
	public const COORDINATE_SCALE = 8.0;

	public static function translateCoordinates(Vector3 $sourcePos, int $fromDimension, int $toDimension) : Vector3{
		if($fromDimension === DimensionIds::OVERWORLD && $toDimension === DimensionIds::NETHER){
			return new Vector3(
				floor($sourcePos->x / self::COORDINATE_SCALE),
				max(32.0, min(120.0, $sourcePos->y)),
				floor($sourcePos->z / self::COORDINATE_SCALE)
			);
		}

		if($fromDimension === DimensionIds::NETHER && $toDimension === DimensionIds::OVERWORLD){
			return new Vector3(
				$sourcePos->x * self::COORDINATE_SCALE,
				max(64.0, min(250.0, $sourcePos->y)),
				$sourcePos->z * self::COORDINATE_SCALE
			);
		}

		return clone $sourcePos;
	}

	public static function findPortal(World $world, Vector3 $center, int $radius) : ?Position{
		$minX = (int) floor($center->x - $radius);
		$maxX = (int) floor($center->x + $radius);
		$minZ = (int) floor($center->z - $radius);
		$maxZ = (int) floor($center->z + $radius);
		$minY = max($world->getMinY(), (int) floor($center->y - $radius));
		$maxY = min($world->getMaxY() - 1, (int) floor($center->y + $radius));

		for($x = $minX; $x <= $maxX; $x += 4){
			for($z = $minZ; $z <= $maxZ; $z += 4){
				for($y = $minY; $y <= $maxY; $y += 2){
					if($world->isInWorld($x, $y, $z) && $world->getBlockAt($x, $y, $z)->getTypeId() === BlockTypeIds::NETHER_PORTAL){
						return new Position($x + 0.5, $y, $z + 0.5, $world);
					}
				}
			}
		}

		return null;
	}

	public static function generatePortal(World $world, Vector3 $pos, int $axis = Axis::X) : Position{
		$baseX = (int) floor($pos->x);
		$baseY = (int) floor(max((float) $world->getMinY() + 5, min((float) $world->getMaxY() - 10, $pos->y)));
		$baseZ = (int) floor($pos->z);

		$obsidian = VanillaBlocks::OBSIDIAN();
		$portal = VanillaBlocks::NETHER_PORTAL()->setAxis($axis);
		$air = VanillaBlocks::AIR();

		// Clear 4x5x3 air box around portal
		for($x = -1; $x <= 3; ++$x){
			for($z = -1; $z <= 1; ++$z){
				for($y = 0; $y <= 4; ++$y){
					$world->setBlockAt($baseX + ($axis === Axis::X ? $x : $z), $baseY + $y, $baseZ + ($axis === Axis::X ? $z : $x), $air);
				}
			}
		}

		// Build obsidian frame (4 wide x 5 high)
		for($w = 0; $w < 4; ++$w){
			// Bottom and top
			$world->setBlockAt($baseX + ($axis === Axis::X ? $w : 0), $baseY, $baseZ + ($axis === Axis::X ? 0 : $w), $obsidian);
			$world->setBlockAt($baseX + ($axis === Axis::X ? $w : 0), $baseY + 4, $baseZ + ($axis === Axis::X ? 0 : $w), $obsidian);
		}

		for($h = 1; $h < 4; ++$h){
			// Sides (column 0 and column 3)
			$world->setBlockAt($baseX, $baseY + $h, $baseZ, $obsidian);
			$world->setBlockAt($baseX + ($axis === Axis::X ? 3 : 0), $baseY + $h, $baseZ + ($axis === Axis::X ? 0 : 3), $obsidian);

			// Interior portal blocks (columns 1 and 2)
			$world->setBlockAt($baseX + ($axis === Axis::X ? 1 : 0), $baseY + $h, $baseZ + ($axis === Axis::X ? 0 : 1), $portal);
			$world->setBlockAt($baseX + ($axis === Axis::X ? 2 : 0), $baseY + $h, $baseZ + ($axis === Axis::X ? 0 : 2), $portal);
		}

		return new Position(
			$baseX + ($axis === Axis::X ? 1.5 : 0.5),
			$baseY + 1.0,
			$baseZ + ($axis === Axis::X ? 0.5 : 1.5),
			$world
		);
	}

	public static function route(Entity $entity, World $targetWorld, int $fromDimension, int $toDimension) : Position{
		$translated = self::translateCoordinates($entity->getPosition(), $fromDimension, $toDimension);
		$radius = $toDimension === DimensionIds::NETHER ? self::NETHER_SEARCH_RADIUS : self::OVERWORLD_SEARCH_RADIUS;

		$existing = self::findPortal($targetWorld, $translated, $radius);
		if($existing !== null){
			return $existing;
		}

		return self::generatePortal($targetWorld, $translated, Axis::X);
	}
}
