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

use pocketmine\item\Item;
use pocketmine\item\Potion;
use pocketmine\item\PotionType;
use pocketmine\item\SplashPotion;
use function preg_replace;
use function str_replace;
use function strtoupper;

final class SetPotionFunction implements LootFunction{

	public readonly ?PotionType $potionType;

	public function __construct(
		public readonly string $id
	){
		$this->potionType = self::resolve($id);
	}

	private static function resolve(string $id) : ?PotionType{
		$name = (string) preg_replace('/^minecraft:/', '', $id);
		// bedrock spells night vision without an underscore in loot tables
		$name = strtoupper(str_replace("nightvision", "night_vision", $name));
		foreach(PotionType::cases() as $case){
			if($case->name === $name){
				return $case;
			}
		}
		return null;
	}

	public function discards(Item $item) : bool{
		return $this->potionType === null && ($item instanceof Potion || $item instanceof SplashPotion);
	}

	public function apply(Item $item, LootContext $context) : Item{
		if(!$item instanceof Potion && !$item instanceof SplashPotion){
			// e.g. tipped arrows are not supported yet js keep the base item
			return $item;
		}
		if($this->potionType === null){
			return $item->setCount(0);
		}
		return $item->setType($this->potionType);
	}
}
