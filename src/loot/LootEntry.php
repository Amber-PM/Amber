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
use pocketmine\item\StringToItemParser;
use pocketmine\world\format\io\GlobalItemDataHandlers;
use function max;

class LootEntry{

	/**
	 * @param LootCondition[] $conditions
	 * @param LootFunction[]  $functions
	 */
	public function __construct(
		public readonly string $type,
		public readonly string $name,
		public readonly int $weight,
		public readonly float $quality,
		public readonly array $conditions,
		public readonly array $functions
	){}

	public function passesConditions(LootContext $context) : bool{
		foreach($this->conditions as $cond){
			if(!$cond->test($context)){
				return false;
			}
		}
		return true;
	}

	public function testConditions(LootContext $context) : bool{
		return $this->passesConditions($context);
	}

	public function getEffectiveWeight(float $luck) : int{
		return (int) max(0, (int) ($this->weight + ($this->quality * $luck)));
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

		$requestedData = null;
		foreach($this->functions as $func){
			if($func instanceof SetDataFunction){
				$requestedData = $func->data;
				break;
			}
		}

		$item = null;
		if($requestedData !== null && $requestedData !== 0){
			try{
				$upgrader = GlobalItemDataHandlers::getUpgrader();
				$upgraded = $upgrader->upgradeItemTypeDataString($this->name, $requestedData, 0, null);
				$item = GlobalItemDataHandlers::getDeserializer()->deserializeStack($upgraded);
			}catch(\Throwable){
				$item = null;
			}
		}

		if($item === null){
			$item = StringToItemParser::getInstance()->parse($this->name);
		}

		if($item === null){
			// Skip unsupported items to avoid saving unregistered item entities
			return [];
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
