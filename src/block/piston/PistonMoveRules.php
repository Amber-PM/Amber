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

namespace pocketmine\block\piston;

use pocketmine\block\Block;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\Flowable;
use pocketmine\block\PistonHead;

final class PistonMoveRules{

	/**
	 * Returns true if the block cannot be moved by pistons (neither pushed nor pulled).
	 */
	public static function isImmovable(Block $block) : bool{
		$typeId = $block->getTypeId();
		if(
			$typeId === BlockTypeIds::BEDROCK ||
			$typeId === BlockTypeIds::OBSIDIAN ||
			$typeId === BlockTypeIds::CRYING_OBSIDIAN ||
			$typeId === BlockTypeIds::GLOWING_OBSIDIAN ||
			$typeId === BlockTypeIds::RESPAWN_ANCHOR ||
			$typeId === BlockTypeIds::MONSTER_SPAWNER ||
			$typeId === BlockTypeIds::ENCHANTING_TABLE ||
			$typeId === BlockTypeIds::ENDER_CHEST ||
			$typeId === BlockTypeIds::NETHER_PORTAL ||
			$typeId === BlockTypeIds::END_PORTAL_FRAME ||
			$typeId === BlockTypeIds::BARRIER ||
			$typeId === BlockTypeIds::MOVING_BLOCK ||
			$typeId === BlockTypeIds::PISTON_HEAD ||
			$block instanceof PistonHead
		){
			return true;
		}

		if(!$block->getBreakInfo()->isBreakable() && !$block->canBeReplaced()){
			return true;
		}

		return false;
	}

	/**
	 * Returns true if the block breaks and drops items when pushed, instead of being displaced.
	 */
	public static function isBreakableOnPush(Block $block) : bool{
		if($block->canBeReplaced()){
			return false;
		}

		return $block instanceof Flowable;
	}

	/**
	 * Returns true if two adjacent blocks stick together when moved by a piston.
	 */
	public static function canStickTogether(Block $a, Block $b) : bool{
		if(self::isImmovable($a) || self::isImmovable($b)){
			return false;
		}

		if($a->canBeReplaced() || $b->canBeReplaced()){
			return false;
		}

		$aSlime = $a->getTypeId() === BlockTypeIds::SLIME;
		$bSlime = $b->getTypeId() === BlockTypeIds::SLIME;
		$aHoney = $a->getTypeId() === BlockTypeIds::HONEY_BLOCK;
		$bHoney = $b->getTypeId() === BlockTypeIds::HONEY_BLOCK;

		// Honey and Slime DO NOT stick to each other
		if(($aSlime && $bHoney) || ($aHoney && $bSlime)){
			return false;
		}

		return $aSlime || $bSlime || $aHoney || $bHoney;
	}
}
