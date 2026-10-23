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

namespace pocketmine\world\hopper;

use pocketmine\block\inventory\BrewingStandInventory;
use pocketmine\block\ShulkerBox as ShulkerBoxBlock;
use pocketmine\block\tile\Barrel;
use pocketmine\block\tile\BrewingStand;
use pocketmine\block\tile\Chest;
use pocketmine\block\tile\Container;
use pocketmine\block\tile\Dispenser;
use pocketmine\block\tile\Dropper;
use pocketmine\block\tile\Furnace;
use pocketmine\block\tile\Hopper;
use pocketmine\block\tile\ShulkerBox;
use pocketmine\block\tile\Tile;
use pocketmine\item\Item;
use pocketmine\item\ItemTypeIds;
use pocketmine\item\Potion;
use pocketmine\item\SplashPotion;
use pocketmine\math\Facing;
use function array_filter;
use function array_values;
use function range;

final class ContainerTransferPolicy{

	public function isSupported(?Tile $tile) : bool{
		return $tile instanceof Chest || $tile instanceof Barrel || $tile instanceof ShulkerBox || $tile instanceof Hopper || $tile instanceof Furnace || $tile instanceof BrewingStand || $tile instanceof Dispenser || $tile instanceof Dropper;
	}

	public function getExtractableSlots(Container $container) : array{
		if($container instanceof Furnace){
			return [2, 1];
		}
		if($container instanceof BrewingStand){
			return [1, 2, 3, 0];
		}
		$size = $container->getInventory()->getSize();
		return $size > 0 ? range(0, $size - 1) : [];
	}

	public function canExtract(Container $container, int $slot, Item $item) : bool{
		if($container instanceof Furnace && $slot === 1){
			return $item->getTypeId() === ItemTypeIds::BUCKET;
		}
		if($container instanceof BrewingStand && $slot === BrewingStandInventory::SLOT_INGREDIENT){
			return $item->getTypeId() === ItemTypeIds::GLASS_BOTTLE;
		}
		return true;
	}

	public function getInsertableSlots(Container $container, Item $item, int $side) : array{
		if($container instanceof Furnace){
			return $side === Facing::UP ? [0] : ($side !== Facing::DOWN && $item->getFuelTime() > 0 ? [1] : []);
		}
		if($container instanceof BrewingStand){
			if($side === Facing::DOWN){
				return [];
			}
			if($side === Facing::UP){
				$manager = $container->getPosition()->getWorld()->getServer()->getCraftingManager();
				foreach($manager->getPotionTypeRecipes() as $recipe){
					if($recipe->getIngredient()->accepts($item)){
						return [BrewingStandInventory::SLOT_INGREDIENT];
					}
				}
				foreach($manager->getPotionContainerChangeRecipes() as $recipe){
					if($recipe->getIngredient()->accepts($item)){
						return [BrewingStandInventory::SLOT_INGREDIENT];
					}
				}
				return [];
			}
			if($item->getTypeId() === ItemTypeIds::BLAZE_POWDER){
				return [BrewingStandInventory::SLOT_FUEL];
			}
			if($item instanceof Potion || $item instanceof SplashPotion || $item->getTypeId() === ItemTypeIds::GLASS_BOTTLE){
				return array_values(array_filter([BrewingStandInventory::SLOT_BOTTLE_LEFT, BrewingStandInventory::SLOT_BOTTLE_MIDDLE, BrewingStandInventory::SLOT_BOTTLE_RIGHT], fn(int $slot) => $container->getInventory()->getItem($slot)->isNull()));
			}
			return [];
		}
		if($container instanceof ShulkerBox && $item->getBlock() instanceof ShulkerBoxBlock){
			return [];
		}
		$size = $container->getInventory()->getSize();
		return $size > 0 ? range(0, $size - 1) : [];
	}
}
