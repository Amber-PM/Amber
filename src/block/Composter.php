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

namespace pocketmine\block;

use pocketmine\data\runtime\RuntimeDataDescriber;
use pocketmine\item\Item;
use pocketmine\item\ItemBlock;
use pocketmine\item\ItemTypeIds;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\sound\ClickSound;
use pocketmine\world\sound\XpCollectSound;
use function mt_rand;

final class Composter extends Transparent{

	public const MIN_FILL_LEVEL = 0;
	public const MAX_FILL_LEVEL = 8;
	public const READY_FILL_LEVEL = 8;

	private int $fillLevel = 0;

	protected function describeBlockOnlyState(RuntimeDataDescriber $w) : void{
		$w->boundedIntAuto(self::MIN_FILL_LEVEL, self::MAX_FILL_LEVEL, $this->fillLevel);
	}

	public function getFillLevel() : int{
		return $this->fillLevel;
	}

	public function setFillLevel(int $fillLevel) : self{
		if($fillLevel < self::MIN_FILL_LEVEL || $fillLevel > self::MAX_FILL_LEVEL){
			throw new \InvalidArgumentException("Fill level must be between " . self::MIN_FILL_LEVEL . " and " . self::MAX_FILL_LEVEL);
		}
		$this->fillLevel = $fillLevel;
		return $this;
	}

	public static function getCompostChance(Item $item) : ?int{
		$itemId = $item->getTypeId();
		$blockId = $item instanceof ItemBlock ? $item->getBlock()->getTypeId() : null;

		// 100% chance
		if(
			$itemId === ItemTypeIds::PUMPKIN_PIE ||
			$blockId === BlockTypeIds::CAKE
		){
			return 100;
		}

		// 85% chance
		if(
			$itemId === ItemTypeIds::BAKED_POTATO ||
			$itemId === ItemTypeIds::BREAD ||
			$itemId === ItemTypeIds::COOKIE ||
			$blockId === BlockTypeIds::HAY_BALE
		){
			return 85;
		}

		// 65% chance
		if(
			$itemId === ItemTypeIds::APPLE ||
			$itemId === ItemTypeIds::BEETROOT ||
			$itemId === ItemTypeIds::CARROT ||
			$itemId === ItemTypeIds::COCOA_BEANS ||
			$itemId === ItemTypeIds::POTATO ||
			$blockId === BlockTypeIds::NETHER_WART ||
			$blockId === BlockTypeIds::MELON ||
			$blockId === BlockTypeIds::PUMPKIN ||
			$blockId === BlockTypeIds::CARVED_PUMPKIN ||
			$blockId === BlockTypeIds::DANDELION ||
			$blockId === BlockTypeIds::POPPY ||
			$blockId === BlockTypeIds::LILY_PAD ||
			$blockId === BlockTypeIds::SEA_PICKLE
		){
			return 65;
		}

		// 50% chance
		if(
			$itemId === ItemTypeIds::MELON ||
			$blockId === BlockTypeIds::SUGARCANE ||
			$blockId === BlockTypeIds::CACTUS ||
			$blockId === BlockTypeIds::DRIED_KELP ||
			$blockId === BlockTypeIds::FLOWERING_AZALEA_LEAVES
		){
			return 50;
		}

		// 30% chance
		if(
			$itemId === ItemTypeIds::WHEAT_SEEDS ||
			$itemId === ItemTypeIds::BEETROOT_SEEDS ||
			$itemId === ItemTypeIds::MELON_SEEDS ||
			$itemId === ItemTypeIds::PUMPKIN_SEEDS ||
			$itemId === ItemTypeIds::SWEET_BERRIES ||
			$itemId === ItemTypeIds::GLOW_BERRIES ||
			$itemId === ItemTypeIds::DRIED_KELP ||
			$blockId === BlockTypeIds::OAK_LEAVES ||
			$blockId === BlockTypeIds::SPRUCE_LEAVES ||
			$blockId === BlockTypeIds::BIRCH_LEAVES ||
			$blockId === BlockTypeIds::JUNGLE_LEAVES ||
			$blockId === BlockTypeIds::ACACIA_LEAVES ||
			$blockId === BlockTypeIds::DARK_OAK_LEAVES ||
			$blockId === BlockTypeIds::MANGROVE_LEAVES ||
			$blockId === BlockTypeIds::AZALEA_LEAVES ||
			$blockId === BlockTypeIds::CHERRY_LEAVES ||
			$blockId === BlockTypeIds::OAK_SAPLING ||
			$blockId === BlockTypeIds::SPRUCE_SAPLING ||
			$blockId === BlockTypeIds::BIRCH_SAPLING ||
			$blockId === BlockTypeIds::JUNGLE_SAPLING ||
			$blockId === BlockTypeIds::ACACIA_SAPLING ||
			$blockId === BlockTypeIds::DARK_OAK_SAPLING ||
			$blockId === BlockTypeIds::CHERRY_SAPLING
		){
			return 30;
		}

		return null;
	}

	public function onInteract(Item $item, int $face, Vector3 $clickVector, ?Player $player = null, array &$returnedItems = []) : bool{
		$world = $this->position->getWorld();
		if($this->fillLevel === self::READY_FILL_LEVEL){
			$world->dropItem($this->position->add(0.5, 0.7, 0.5), VanillaItems::BONE_MEAL());
			$world->setBlock($this->position, (clone $this)->setFillLevel(self::MIN_FILL_LEVEL));
			$world->addSound($this->position, new XpCollectSound());
			return true;
		}

		if($this->fillLevel < 7){
			$chance = self::getCompostChance($item);
			if($chance !== null){
				if($player !== null && !$player->isCreative()){
					$item->pop();
				}
				if(mt_rand(1, 100) <= $chance){
					$newLevel = $this->fillLevel + 1;
					$world->setBlock($this->position, (clone $this)->setFillLevel($newLevel));
					if($newLevel === 7){
						$world->scheduleDelayedBlockUpdate($this->position, 20);
					}
					$world->addSound($this->position, new XpCollectSound());
				}else{
					$world->addSound($this->position, new ClickSound());
				}
				return true;
			}
		}

		return false;
	}

	public function onScheduledUpdate() : void{
		if($this->fillLevel === 7){
			$this->position->getWorld()->setBlock($this->position, (clone $this)->setFillLevel(self::READY_FILL_LEVEL));
			$this->position->getWorld()->addSound($this->position, new XpCollectSound());
		}
	}
}
