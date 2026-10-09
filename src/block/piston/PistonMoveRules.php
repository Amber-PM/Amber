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
use pocketmine\block\Piston;
use pocketmine\block\PistonHead;
use pocketmine\block\ShulkerBox;
use pocketmine\block\tile\Tile;
use function get_class;
use function in_array;

final class PistonMoveRules{

	/**
	 * @phpstan-param class-string<Tile> $tileClass
	 */
	public static function isTileClassSupported(string $tileClass) : bool{
		return in_array($tileClass, [
			\pocketmine\block\tile\Barrel::class,
			\pocketmine\block\tile\BrewingStand::class,
			\pocketmine\block\tile\Chest::class,
			\pocketmine\block\tile\Dispenser::class,
			\pocketmine\block\tile\Dropper::class,
			\pocketmine\block\tile\Hopper::class,
			\pocketmine\block\tile\NormalFurnace::class,
			\pocketmine\block\tile\BlastFurnace::class,
			\pocketmine\block\tile\Smoker::class,
			\pocketmine\block\tile\Note::class,
			\pocketmine\block\tile\PistonArm::class,
			\pocketmine\block\tile\ShulkerBox::class
		], true);
	}

	 //returns true if the tile entity is supported for movement by pistons
	public static function isTileSupported(Tile $tile) : bool{
		if($tile->isClosed()){
			return false;
		}

		return self::isTileClassSupported(get_class($tile));
	}

	public static function isImmovable(Block $block) : bool{
		if($block instanceof Piston){
			$position = $block->getPosition();
			if($position->isValid()){
				$head = $position->getSide($block->getFacing());
				$world = $position->getWorld();
				if($world->isInWorld($head->getFloorX(), $head->getFloorY(), $head->getFloorZ()) && !$world->isChunkLoaded($head->getFloorX() >> 4, $head->getFloorZ() >> 4)){
					return true;
				}
			}
			if($block->isExtended() || ($position->isValid() && ($tile = $position->getWorld()->getTile($position)) instanceof \pocketmine\block\tile\PistonArm && $tile->isMoving())){
				return true;
			}
		}
		$typeId = $block->getTypeId();
		if(
			$typeId === BlockTypeIds::BEDROCK ||
			$typeId === BlockTypeIds::BEACON ||
			$typeId === BlockTypeIds::JUKEBOX ||
			$typeId === BlockTypeIds::REINFORCED_DEEPSLATE ||
			$typeId === BlockTypeIds::LIGHT ||
			$typeId === BlockTypeIds::STRUCTURE_VOID ||
			$typeId === BlockTypeIds::END_PORTAL ||
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
			$typeId === BlockTypeIds::PISTON_HEAD ||
			$block instanceof PistonHead
		){
			return true;
		}

		if(!$block->getBreakInfo()->isBreakable() && !$block->canBeReplaced()){
			return true;
		}

		if(self::isBreakableOnPush($block)){
			return false;
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

	 //returns true if the block breaks and drops items when pushed, instead of being displaced
	public static function isBreakableOnPush(Block $block) : bool{
		if($block->getTypeId() === BlockTypeIds::AIR || $block instanceof \pocketmine\block\Fire || $block instanceof \pocketmine\block\Liquid){
			return false;
		}
		return $block instanceof Flowable ||
			$block instanceof ShulkerBox ||
			$block instanceof \pocketmine\block\Door ||
			$block instanceof \pocketmine\block\Bed ||
			$block instanceof \pocketmine\block\BaseCake ||
			$block instanceof \pocketmine\block\BaseSign ||
			$block instanceof \pocketmine\block\BaseBanner ||
			$block instanceof \pocketmine\block\BaseOminousBanner ||
			$block instanceof \pocketmine\block\PressurePlate ||
			$block instanceof \pocketmine\block\Ladder ||
			$block instanceof \pocketmine\block\Bamboo ||
			$block instanceof \pocketmine\block\Cactus ||
			$block instanceof \pocketmine\block\Candle ||
			$block instanceof \pocketmine\block\Lantern ||
			$block instanceof \pocketmine\block\AmethystCluster ||
			$block instanceof \pocketmine\block\BaseBigDripleaf ||
			$block instanceof \pocketmine\block\SmallDripleaf ||
			$block instanceof \pocketmine\block\SeaPickle ||
			$block instanceof \pocketmine\block\BaseCoral ||
			$block instanceof \pocketmine\block\GlowLichen ||
			$block instanceof \pocketmine\block\ResinClump ||
			$block instanceof \pocketmine\block\DragonEgg ||
			$block instanceof \pocketmine\block\BuddingAmethyst ||
			$block instanceof \pocketmine\block\Campfire;
	}

	public static function isPushOnly(Block $block) : bool{
		return $block instanceof \pocketmine\block\GlazedTerracotta;
	}

	public static function isAdhesive(Block $block) : bool{
		return $block->getTypeId() === BlockTypeIds::SLIME || $block->getTypeId() === BlockTypeIds::HONEY_BLOCK;
	}

	public static function canStickTogether(Block $a, Block $b) : bool{
		if(self::isImmovable($a) || self::isImmovable($b) || self::isPushOnly($a) || self::isPushOnly($b) || self::isBreakableOnPush($a) || self::isBreakableOnPush($b)){
			return false;
		}

		if($a->canBeReplaced() || $b->canBeReplaced()){
			return false;
		}

		$aSlime = $a->getTypeId() === BlockTypeIds::SLIME;
		$bSlime = $b->getTypeId() === BlockTypeIds::SLIME;
		$aHoney = $a->getTypeId() === BlockTypeIds::HONEY_BLOCK;
		$bHoney = $b->getTypeId() === BlockTypeIds::HONEY_BLOCK;

		if(($aSlime && $bHoney) || ($aHoney && $bSlime)){
			return false;
		}

		return $aSlime || $bSlime || $aHoney || $bHoney;
	}
}
