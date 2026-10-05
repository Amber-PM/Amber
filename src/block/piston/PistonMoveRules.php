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
use pocketmine\block\ShulkerBox;
use pocketmine\block\tile\EnchantTable;
use pocketmine\block\tile\EnderChest;
use pocketmine\block\tile\MonsterSpawner;
use pocketmine\block\tile\Tile;
use pocketmine\block\tile\TileFactory;
use function get_class;

final class PistonMoveRules{

	/**
	 * @phpstan-param class-string<Tile> $tileClass
	 */
	public static function isTileClassSupported(string $tileClass) : bool{
		if(
			$tileClass === MonsterSpawner::class ||
			$tileClass === EnchantTable::class ||
			$tileClass === EnderChest::class
		){
			return false;
		}

		return TileFactory::getInstance()->isRegistered($tileClass);
	}

	/**
	 * Returns true if the tile entity is supported for movement by pistons.
	 */
	public static function isTileSupported(Tile $tile) : bool{
		if($tile->isClosed()){
			return false;
		}

		return self::isTileClassSupported(get_class($tile));
	}

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

		$tileClass = $block->getIdInfo()->getTileClass();
		if($tileClass !== null){
			if(!self::isTileClassSupported($tileClass)){
				return true;
			}
			$pos = $block->getPosition();
			if($pos->isValid()){
				$tile = $pos->getWorld()->getTile($pos);
				if($tile !== null && !self::isTileSupported($tile)){
					return true;
				}
			}
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

		if($block instanceof ShulkerBox){
			return true;
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
