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

namespace pocketmine\world\generator\structure;

use pocketmine\block\Block;
use pocketmine\block\Leaves;
use pocketmine\block\Liquid;
use pocketmine\block\VanillaBlocks;
use pocketmine\block\Wood;
use pocketmine\utils\Random;
use pocketmine\world\ChunkManager;
use pocketmine\world\format\Chunk;
use function max;
use function min;

/**
 * A small structure placed during world population. Structures must stay within 16 blocks of the position they are
 * placed at: population can only write to the chunk being populated and the chunks around it.
 */
abstract class Structure{
	public const MAX_RADIUS = 16;

	abstract public function getName() : string;

	/**
	 * Places the structure around the column at x/z, if the ground there suits it.
	 *
	 * @return bool whether it was placed
	 */
	abstract public function place(ChunkManager $world, int $x, int $z, Random $random) : bool;

	/**
	 * The Y of the ground block at x/z (ignoring plants, snow layers, leaves and logs), or null when there is no
	 * loaded ground there, or it is under liquid.
	 */
	public static function groundY(ChunkManager $world, int $x, int $z) : ?int{
		$highest = $world->getChunk($x >> Chunk::COORD_BIT_SIZE, $z >> Chunk::COORD_BIT_SIZE)?->getHighestBlockAt($x & Chunk::COORD_MASK, $z & Chunk::COORD_MASK);
		if($highest === null){
			return null;
		}
		for($y = $highest; $y > $world->getMinY(); --$y){
			$block = $world->getBlockAt($x, $y, $z);
			if($block instanceof Liquid){
				return null;
			}
			if($block->isSolid() && !$block instanceof Leaves && !$block instanceof Wood){
				return $y;
			}
		}
		return null;
	}

	/**
	 * The ground Y at x/z when the ground within $radius of it varies by at most $maxDifference (checked at the centre,
	 * corners and edge midpoints), or null.
	 */
	public static function flatGroundY(ChunkManager $world, int $x, int $z, int $radius, int $maxDifference) : ?int{
		$center = self::groundY($world, $x, $z);
		if($center === null){
			return null;
		}
		$low = $high = $center;
		foreach([-$radius, 0, $radius] as $dx){
			foreach([-$radius, 0, $radius] as $dz){
				$y = self::groundY($world, $x + $dx, $z + $dz);
				if($y === null){
					return null;
				}
				$low = min($low, $y);
				$high = max($high, $y);
			}
		}
		return $high - $low <= $maxDifference ? $center : null;
	}

	protected static function set(ChunkManager $world, int $x, int $y, int $z, Block $block) : void{
		if($world->isInWorld($x, $y, $z)){
			$world->setBlockAt($x, $y, $z, $block);
		}
	}

	/** Sets the block only where the current one is solid, so the structure does not float over caves. */
	protected static function replaceSolid(ChunkManager $world, int $x, int $y, int $z, Block $block) : void{
		if($world->isInWorld($x, $y, $z) && $world->getBlockAt($x, $y, $z)->isSolid()){
			$world->setBlockAt($x, $y, $z, $block);
		}
	}

	/** Fills the column below a floor block with $block, down to the ground (at most $maxDepth blocks). */
	protected static function support(ChunkManager $world, int $x, int $y, int $z, Block $block, int $maxDepth = 4) : void{
		for($yy = $y - 1; $yy >= $y - $maxDepth && $world->isInWorld($x, $yy, $z); --$yy){
			$existing = $world->getBlockAt($x, $yy, $z);
			if($existing->isSolid() && !$existing instanceof Leaves){
				return;
			}
			$world->setBlockAt($x, $yy, $z, $block);
		}
	}

	protected static function air() : Block{
		return VanillaBlocks::AIR();
	}
}
