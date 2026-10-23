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
use function max;
use function mt_rand;
use function round;

final class LootPool{

	/**
	 * @param LootCondition[] $conditions
	 * @param LootEntry[]     $entries
	 */
	public function __construct(
		public readonly int $minRolls,
		public readonly int $maxRolls,
		public readonly float $minBonusRolls = 0.0,
		public readonly float $maxBonusRolls = 0.0,
		public readonly array $conditions = [],
		public readonly array $entries = []
	){}

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
		if(!$this->testConditions($context)){
			return [];
		}

		$rolls = ($this->minRolls === $this->maxRolls) ? $this->minRolls : mt_rand($this->minRolls, $this->maxRolls);
		if($context->luck !== 0.0 && ($this->minBonusRolls !== 0.0 || $this->maxBonusRolls !== 0.0)){
			$bonus = ($this->minBonusRolls === $this->maxBonusRolls)
				? $this->minBonusRolls
				: ($this->minBonusRolls + (lcg_value() * ($this->maxBonusRolls - $this->minBonusRolls)));
			$rolls = (int) round($rolls + ($bonus * $context->luck));
		}
		$rolls = max(0, $rolls);

		$validEntries = [];
		$totalWeight = 0;
		foreach($this->entries as $entry){
			if($entry->testConditions($context)){
				$w = $entry->getEffectiveWeight($context->luck);
				if($w > 0){
					$validEntries[] = ["entry" => $entry, "weight" => $w];
					$totalWeight += $w;
				}
			}
		}

		if($totalWeight <= 0 || empty($validEntries)){
			return [];
		}

		$results = [];
		for($r = 0; $r < $rolls; ++$r){
			$rand = mt_rand(1, $totalWeight);
			$current = 0;
			foreach($validEntries as $candidate){
				$current += $candidate["weight"];
				if($rand <= $current){
					foreach($candidate["entry"]->roll($context) as $item){
						$results[] = $item;
					}
					break;
				}
			}
		}

		return $results;
	}
}
