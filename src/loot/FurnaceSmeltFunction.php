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

namespace pocketmine\loot;

use pocketmine\crafting\FurnaceType;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\Server;

final class FurnaceSmeltFunction implements LootFunction{

	public function apply(Item $item, LootContext $context) : Item{
		$victim = $context->victim;
		$isOnFire = $victim !== null && $victim->isOnFire();

		if(!$isOnFire){
			return $item;
		}

		// Check furnace recipe manager if available in server context
		try{
			$server = Server::getInstance();
			$recipe = $server->getCraftingManager()->getFurnaceRecipeManager(FurnaceType::FURNACE)->match($item);
			if($recipe !== null){
				$result = clone $recipe->getResult();
				$result->setCount($item->getCount());
				return $result;
			}
		}catch(\Throwable){
			// Standalone or offline testing fallback
		}

		// Built-in vanilla smelting fallbacks
		$cooked = match($item->getTypeId()){
			VanillaItems::RAW_BEEF()->getTypeId() => VanillaItems::STEAK(),
			VanillaItems::RAW_PORKCHOP()->getTypeId() => VanillaItems::COOKED_PORKCHOP(),
			VanillaItems::RAW_CHICKEN()->getTypeId() => VanillaItems::COOKED_CHICKEN(),
			VanillaItems::RAW_MUTTON()->getTypeId() => VanillaItems::COOKED_MUTTON(),
			VanillaItems::RAW_RABBIT()->getTypeId() => VanillaItems::COOKED_RABBIT(),
			VanillaItems::RAW_SALMON()->getTypeId() => VanillaItems::COOKED_SALMON(),
			VanillaItems::RAW_COPPER()->getTypeId() => VanillaItems::COPPER_INGOT(),
			VanillaItems::RAW_IRON()->getTypeId() => VanillaItems::IRON_INGOT(),
			VanillaItems::RAW_GOLD()->getTypeId() => VanillaItems::GOLD_INGOT(),
			VanillaItems::POTATO()->getTypeId() => VanillaItems::BAKED_POTATO(),
			default => null
		};

		if($cooked !== null){
			$cooked->setCount($item->getCount());
			return $cooked;
		}

		return $item;
	}
}
