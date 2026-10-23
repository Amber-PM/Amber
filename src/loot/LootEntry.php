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
use pocketmine\item\ItemIdentifier;
use pocketmine\item\ItemTypeIds;
use pocketmine\item\StringToItemParser;
use function floor;
use function max;

final class LootEntry{

	/**
	 * @param LootCondition[] $conditions
	 * @param LootFunction[]  $functions
	 */
	public function __construct(
		public readonly string $type,
		public readonly string $name,
		public readonly int $weight = 1,
		public readonly float $quality = 0.0,
		public readonly array $conditions = [],
		public readonly array $functions = []
	){}

	public function getEffectiveWeight(float $luck = 0.0) : int{
		$eff = (int) floor($this->weight + ($this->quality * $luck));
		return max(0, $eff);
	}

	public function testConditions(LootContext $context) : bool{
		foreach($this->conditions as $cond){
			if(!$cond->test($context)){
				return false;
			}
		}
		return true;
	}

	/**
	 * @return Item[]
	 */
	public function roll(LootContext $context) : array{
		if($this->type === "empty"){
			return [];
		}

		if($this->type === "loot_table"){
			$subTable = LootTableRegistry::getInstance()->get($this->name);
			return $subTable !== null ? $subTable->roll($context) : [];
		}

		$item = StringToItemParser::getInstance()->parse($this->name);
		if($item === null){
			$item = new Item(new ItemIdentifier(ItemTypeIds::newId()), $this->name);
		}

		$item = clone $item;
		$item->setCount(1);

		foreach($this->functions as $func){
			$item = $func->apply($item, $context);
		}

		if($item->getCount() <= 0 || $item->isNull()){
			return [];
		}

		return [$item];
	}
}
