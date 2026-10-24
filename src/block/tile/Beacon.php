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

namespace pocketmine\block\tile;

use pocketmine\block\BlockTypeIds;
use pocketmine\data\bedrock\EffectIdMap;
use pocketmine\entity\effect\EffectInstance;
use pocketmine\item\Item;
use pocketmine\item\ItemTypeIds;
use pocketmine\math\AxisAlignedBB;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\player\Player;

final class Beacon extends Spawnable{
	private const TAG_PRIMARY = "primary"; //TAG_Int
	private const TAG_SECONDARY = "secondary"; //TAG_Int

	public const EFFECT_SPEED = 1;
	public const EFFECT_HASTE = 3;
	public const EFFECT_RESISTANCE = 11;
	public const EFFECT_JUMP_BOOST = 8;
	public const EFFECT_STRENGTH = 5;
	public const EFFECT_REGENERATION = 10;

	private int $primaryEffect = 0;
	private int $secondaryEffect = 0;
	private int $pyramidLevel = 0;

	protected function addAdditionalSpawnData(CompoundTag $nbt, TypeConverter $typeConverter) : void{
		$nbt->setInt(self::TAG_PRIMARY, $this->primaryEffect);
		$nbt->setInt(self::TAG_SECONDARY, $this->secondaryEffect);
	}

	public function readSaveData(CompoundTag $nbt) : void{
		$this->primaryEffect = $nbt->getInt(self::TAG_PRIMARY, 0);
		$this->secondaryEffect = $nbt->getInt(self::TAG_SECONDARY, 0);
	}

	protected function writeSaveData(CompoundTag $nbt) : void{
		$nbt->setInt(self::TAG_PRIMARY, $this->primaryEffect);
		$nbt->setInt(self::TAG_SECONDARY, $this->secondaryEffect);
	}

	public function getPrimaryEffect() : int{ return $this->primaryEffect; }

	public function setPrimaryEffect(int $primaryEffect) : void{ $this->primaryEffect = $primaryEffect; }

	public function getSecondaryEffect() : int{ return $this->secondaryEffect; }

	public function setSecondaryEffect(int $secondaryEffect) : void{ $this->secondaryEffect = $secondaryEffect; }

	public function getPyramidLevel() : int{ return $this->pyramidLevel; }

	public function setPyramidLevel(int $level) : void{ $this->pyramidLevel = $level; }

	public static function isValidBaseBlock(int $blockTypeId) : bool{
		return match($blockTypeId){
			BlockTypeIds::IRON,
			BlockTypeIds::GOLD,
			BlockTypeIds::DIAMOND,
			BlockTypeIds::EMERALD,
			BlockTypeIds::NETHERITE => true,
			default => false,
		};
	}

	public static function isValidPaymentItem(Item $item) : bool{
		return match($item->getTypeId()){
			ItemTypeIds::IRON_INGOT,
			ItemTypeIds::GOLD_INGOT,
			ItemTypeIds::DIAMOND,
			ItemTypeIds::EMERALD,
			ItemTypeIds::NETHERITE_INGOT => true,
			default => false,
		};
	}

	public function isSkyClear() : bool{
		$world = $this->position->getWorld();
		$x = $this->position->getFloorX();
		$z = $this->position->getFloorZ();
		$maxY = $world->getMaxY();
		for($y = $this->position->getFloorY() + 1; $y < $maxY; ++$y){
			$block = $world->getBlockAt($x, $y, $z);
			if(!$block->isTransparent()){
				return false;
			}
		}
		return true;
	}

	public function calculatePyramid() : int{
		$world = $this->position->getWorld();
		$bx = $this->position->getFloorX();
		$by = $this->position->getFloorY();
		$bz = $this->position->getFloorZ();

		$currentLevel = 0;
		for($layer = 1; $layer <= 4; ++$layer){
			$y = $by - $layer;
			if($y < $world->getMinY()){
				break;
			}
			$radius = $layer;
			for($x = $bx - $radius; $x <= $bx + $radius; ++$x){
				for($z = $bz - $radius; $z <= $bz + $radius; ++$z){
					$blockId = $world->getBlockAt($x, $y, $z)->getTypeId();
					if(!self::isValidBaseBlock($blockId)){
						$this->pyramidLevel = $currentLevel;
						return $currentLevel;
					}
				}
			}
			$currentLevel = $layer;
		}

		$this->pyramidLevel = $currentLevel;
		return $currentLevel;
	}

	public function applyEffects() : void{
		$level = $this->calculatePyramid();
		if($level < 1 || !$this->isSkyClear()){
			return;
		}

		$primaryEffectType = $this->primaryEffect > 0 ? EffectIdMap::getInstance()->fromId($this->primaryEffect) : null;
		if($primaryEffectType === null){
			return;
		}

		$duration = (9 + $level * 2) * 20; // in ticks
		$range = 10 + $level * 10; // 20, 30, 40, 50 blocks

		$pos = $this->position;
		$bb = new AxisAlignedBB(
			$pos->x - $range,
			$pos->getWorld()->getMinY(),
			$pos->z - $range,
			$pos->x + $range + 1,
			$pos->getWorld()->getMaxY(),
			$pos->z + $range + 1
		);

		$primaryAmplifier = 0;
		$hasRegenSecondary = false;

		if($level >= 4){
			if($this->secondaryEffect === $this->primaryEffect){
				$primaryAmplifier = 1;
			}elseif($this->secondaryEffect === self::EFFECT_REGENERATION){
				$hasRegenSecondary = true;
			}
		}

		$regenEffectType = $hasRegenSecondary ? EffectIdMap::getInstance()->fromId(self::EFFECT_REGENERATION) : null;

		foreach($pos->getWorld()->getCollidingEntities($bb) as $entity){
			if($entity instanceof Player && $entity->isAlive() && $entity->isConnected()){
				$entity->getEffects()->add(new EffectInstance($primaryEffectType, $duration, $primaryAmplifier, visible: true));
				if($regenEffectType !== null){
					$entity->getEffects()->add(new EffectInstance($regenEffectType, $duration, 0, visible: true));
				}
			}
		}
	}
}
