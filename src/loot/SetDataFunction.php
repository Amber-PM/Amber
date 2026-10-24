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

use pocketmine\item\Durable;
use pocketmine\item\Item;
use pocketmine\world\format\io\GlobalItemDataHandlers;
use function preg_match;

final class SetDataFunction implements LootFunction{

	public function __construct(
		public readonly int $data
	){}

	public function apply(Item $item, LootContext $context) : Item{
		if($item instanceof Durable){
			$item->setDamage($this->data);
			return $item;
		}

		$serializer = GlobalItemDataHandlers::getSerializer();
		$deserializer = GlobalItemDataHandlers::getDeserializer();
		$upgrader = GlobalItemDataHandlers::getUpgrader();

		try{
			$serialized = $serializer->serializeType($item);
			$name = $serialized->getName();
			if(preg_match('/^minecraft:([a-z]+)_(wool|carpet|concrete|concrete_powder|shulker_box|stained_glass|stained_glass_pane|terracotta|glazed_terracotta)$/', $name, $matches) === 1){
				$name = "minecraft:" . $matches[2];
			}
			$upgraded = $upgrader->upgradeItemTypeDataString($name, $this->data, 0, $serialized->getTag());
			$newItem = $deserializer->deserializeStack($upgraded);
			$newItem->setCount($item->getCount());
			return $newItem;
		}catch(\Throwable){
			return $item;
		}
	}
}
