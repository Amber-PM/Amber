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

namespace pocketmine\block\dispenser;

use pocketmine\block\Lava;
use pocketmine\block\tile\Container;
use pocketmine\block\VanillaBlocks;
use pocketmine\block\Water;
use pocketmine\item\Item;
use pocketmine\item\ItemTypeIds;
use pocketmine\item\VanillaItems;
use pocketmine\world\sound\BucketEmptyLavaSound;
use pocketmine\world\sound\BucketEmptyWaterSound;
use pocketmine\world\sound\BucketFillLavaSound;
use pocketmine\world\sound\BucketFillWaterSound;

class BucketDispenseBehavior implements DispenseBehavior{

	public function dispense(BlockSource $source, Item $item) : Item{
		$world = $source->getWorld();
		$targetPos = $source->getPos()->getSide($source->getFacing());
		$targetBlock = $world->getBlock($targetPos);

		if($item->getTypeId() === ItemTypeIds::BUCKET){
			// Empty bucket picking up water or lava
			$replacement = null;
			if($targetBlock instanceof Water && $targetBlock->isSource()){
				$world->setBlock($targetPos, VanillaBlocks::AIR());
				$world->addSound($source->getPos(), $targetBlock->getBucketFillSound());
				$replacement = VanillaItems::WATER_BUCKET();
			}elseif($targetBlock instanceof Lava && $targetBlock->isSource()){
				$world->setBlock($targetPos, VanillaBlocks::AIR());
				$world->addSound($source->getPos(), $targetBlock->getBucketFillSound());
				$replacement = VanillaItems::LAVA_BUCKET();
			}

			if($replacement !== null){
				if($item->getCount() === 1){
					return $replacement;
				}

				$item->pop();
				$this->depositItem($source, $replacement);
				return $item;
			}

			// Cannot pickup liquid: fall back to default behavior
			return DispenseBehaviorRegistry::getInstance()->getDefault()->dispense($source, $item);
		}

		// Placing water or lava
		$isWater = $item->getTypeId() === ItemTypeIds::WATER_BUCKET;
		$isLava = $item->getTypeId() === ItemTypeIds::LAVA_BUCKET;

		if($isWater || $isLava){
			if($targetBlock->canBeReplaced()){
				$newBlock = $isWater ? VanillaBlocks::WATER() : VanillaBlocks::LAVA();
				$world->setBlock($targetPos, $newBlock);
				$world->addSound($source->getPos(), $newBlock->getBucketEmptySound());

				$emptyBucket = VanillaItems::BUCKET();
				if($item->getCount() === 1){
					return $emptyBucket;
				}

				$item->pop();
				$this->depositItem($source, $emptyBucket);
				return $item;
			}

			return DispenseBehaviorRegistry::getInstance()->getDefault()->dispense($source, $item);
		}

		return DispenseBehaviorRegistry::getInstance()->getDefault()->dispense($source, $item);
	}

	private function depositItem(BlockSource $source, Item $newItem) : void{
		$tile = $source->getTile();
		if($tile instanceof Container){
			$inv = $tile->getInventory();
			if($inv->canAddItem($newItem)){
				$inv->addItem($newItem);
				return;
			}
		}

		$source->getWorld()->dropItem($source->getDispensePosition(), $newItem);
	}
}
