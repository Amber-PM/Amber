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
use function implode;
use function mt_rand;
use function preg_match;

final class SetDataFunction implements LootFunction{

	private const COLOR_PREFIXES = [
		"white", "orange", "magenta", "light_blue", "yellow", "lime", "pink", "gray",
		"light_gray", "cyan", "purple", "blue", "brown", "green", "red", "black"
	];

	public readonly int $minData;
	public readonly int $maxData;
	public readonly int $data;

	public function __construct(
		int $minData,
		?int $maxData = null
	){
		$this->minData = $minData;
		$this->maxData = $maxData ?? $minData;
		$this->data = $minData;
	}

	public function apply(Item $item, LootContext $context) : Item{
		$data = ($this->maxData > $this->minData) ? mt_rand($this->minData, $this->maxData) : $this->minData;

		if($item instanceof Durable){
			$item->setDamage($data);
			return $item;
		}

		$serializer = GlobalItemDataHandlers::getSerializer();
		$deserializer = GlobalItemDataHandlers::getDeserializer();
		$upgrader = GlobalItemDataHandlers::getUpgrader();

		try{
			$serialized = $serializer->serializeType($item);
			$name = $serialized->getName();

			$colorsPattern = implode('|', self::COLOR_PREFIXES);
			if(preg_match('/^minecraft:(?:' . $colorsPattern . ')_(wool|carpet|concrete|concrete_powder|shulker_box|stained_glass|stained_glass_pane|terracotta|glazed_terracotta)$/', $name, $matches) === 1){
				$family = $matches[1];
				$name = ($family === "terracotta") ? "minecraft:stained_hardened_clay" : ("minecraft:" . $family);
			}

			$upgraded = $upgrader->upgradeItemTypeDataString($name, $data, 0, $serialized->getTag());
			$newItem = $deserializer->deserializeStack($upgraded);
			$newItem->setCount($item->getCount());
			return $newItem;
		}catch(\Throwable){
			return $item;
		}
	}
}
