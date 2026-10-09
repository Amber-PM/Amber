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

use pocketmine\block\BlockTypeIds;
use pocketmine\block\tile\Dispenser;
use pocketmine\entity\Entity;
use pocketmine\entity\Living;
use pocketmine\inventory\ArmorInventory;
use pocketmine\item\Armor;
use pocketmine\item\Item;
use pocketmine\item\ItemBlock;
use pocketmine\math\AxisAlignedBB;
use pocketmine\world\sound\ArmorEquipGenericSound;

class ArmorDispenseBehavior implements DispenseBehavior{

	public function dispense(BlockSource $source, Item $item) : Item{
		if(!$source->isTargetAvailable()){
			$tile = $source->getTile();
			if($tile instanceof Dispenser){
				$tile->clearPendingDispense();
				$source->getWorld()->getRedstoneEngine()?->cancelEntityScan($source->getPos());
			}
			$source->getWorld()->addSound($source->getPos(), new \pocketmine\world\sound\ClickFailSound());
			return $item;
		}
		$world = $source->getWorld();
		$targetPos = $source->getPos()->getSide($source->getFacing());

		$bb = new AxisAlignedBB(
			$targetPos->x,
			$targetPos->y,
			$targetPos->z,
			$targetPos->x + 1.0,
			$targetPos->y + 1.0,
			$targetPos->z + 1.0
		);

		$slot = $this->resolveSlot($item);
		if($slot === null){
			return DispenseBehaviorRegistry::getInstance()->getDefault()->dispense($source, $item);
		}

		$engine = $world->getRedstoneEngine();
		$tile = $source->getTile();
		$inventorySlot = $source->getInventorySlot();
		if($engine !== null && $engine->isTicking() && $tile instanceof Dispenser && $inventorySlot !== null){
			$entities = $engine->collectEntities($source->getBlock(), $bb, 1,
				fn(Entity $entity) : bool => $entity instanceof Living && $entity->isAlive() && $entity->canBeCollidedWith() && $entity->getArmorInventory()->getItem($slot)->isNull()
			);
			if($entities === null){
				$tile->deferDispense($inventorySlot, $item);
				$engine->schedule($source->getPos(), 1, $source->getBlock()->getStateId());
				return $item;
			}
			$tile->clearPendingDispense();
		}else{
			if($tile instanceof Dispenser){
				$tile->clearPendingDispense();
			}
			$entities = $world->iterateEntityCandidates($bb);
		}
		foreach($entities as $entity){
			if($entity instanceof Living && !$entity->isClosed() && $entity->getWorld() === $world && $entity->canBeCollidedWith() && $entity->getBoundingBox()->intersectsWith($bb) && $entity->isAlive() && !$entity->isFlaggedForDespawn()){
				$armorInv = $entity->getArmorInventory();
				if($armorInv->getItem($slot)->isNull()){
					$armorInv->setItem($slot, (clone $item)->setCount(1));
					$item->pop();
					$world->addSound($entity->getLocation(), new ArmorEquipGenericSound());
					return $item;
				}
			}
		}

		return DispenseBehaviorRegistry::getInstance()->getDefault()->dispense($source, $item);
	}

	private function resolveSlot(Item $item) : ?int{
		if($item instanceof Armor){
			return $item->getArmorSlot();
		}

		if($item instanceof ItemBlock){
			$blockId = $item->getBlock()->getTypeId();
			if($blockId === BlockTypeIds::CARVED_PUMPKIN || $blockId === BlockTypeIds::MOB_HEAD){
				return ArmorInventory::SLOT_HEAD;
			}
		}

		return null;
	}
}
