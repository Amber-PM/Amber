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

namespace pocketmine\entity\villager;

use pocketmine\item\Item;
use pocketmine\nbt\tag\CompoundTag;

class TradeOffer{

	public function __construct(
		private Item $buyA,
		private ?Item $buyB,
		private Item $sell,
		private int $maxUses = 16,
		private int $uses = 0,
		private int $traderExp = 1,
		private bool $rewardExp = true,
		private int $tier = 1,
		private float $priceMultiplierA = 0.05,
		private float $priceMultiplierB = 0.05,
		private int $demand = 0
	){}

	public function getBuyA() : Item{
		return clone $this->buyA;
	}

	public function getBuyB() : ?Item{
		return $this->buyB !== null ? clone $this->buyB : null;
	}

	public function getSell() : Item{
		return clone $this->sell;
	}

	public function getMaxUses() : int{
		return $this->maxUses;
	}

	public function getUses() : int{
		return $this->uses;
	}

	public function setUses(int $uses) : self{
		$this->uses = $uses;
		return $this;
	}

	public function resetUses() : void{
		$this->uses = 0;
	}

	public function incrementUses(int $amount = 1) : void{
		$this->uses += $amount;
	}

	public function isSoldOut() : bool{
		return $this->uses >= $this->maxUses;
	}

	public function getTraderExp() : int{
		return $this->traderExp;
	}

	public function rewardsExp() : bool{
		return $this->rewardExp;
	}

	public function getTier() : int{
		return $this->tier;
	}

	public function getPriceMultiplierA() : float{
		return $this->priceMultiplierA;
	}

	public function getPriceMultiplierB() : float{
		return $this->priceMultiplierB;
	}

	public function getDemand() : int{
		return $this->demand;
	}

	public function matchesInputs(Item $inputA, Item $inputB) : bool{
		if($inputA->getTypeId() !== $this->buyA->getTypeId() || $inputA->getCount() < $this->buyA->getCount()){
			return false;
		}

		if($this->buyB !== null && !$this->buyB->isNull()){
			if($inputB->getTypeId() !== $this->buyB->getTypeId() || $inputB->getCount() < $this->buyB->getCount()){
				return false;
			}
		}

		return true;
	}

	public function toNbt() : CompoundTag{
		$tag = CompoundTag::create()
			->setTag("buyA", $this->buyA->nbtSerialize())
			->setTag("sell", $this->sell->nbtSerialize())
			->setInt("buyCountA", $this->buyA->getCount())
			->setInt("maxUses", $this->maxUses)
			->setInt("uses", $this->uses)
			->setInt("tier", $this->tier - 1)
			->setInt("traderExp", $this->traderExp)
			->setByte("rewardExp", $this->rewardExp ? 1 : 0)
			->setInt("demand", $this->demand)
			->setFloat("priceMultiplierA", $this->priceMultiplierA)
			->setFloat("priceMultiplierB", $this->priceMultiplierB);

		if($this->buyB !== null && !$this->buyB->isNull()){
			$tag->setTag("buyB", $this->buyB->nbtSerialize());
			$tag->setInt("buyCountB", $this->buyB->getCount());
		}else{
			$tag->setInt("buyCountB", 0);
		}

		return $tag;
	}

	public static function fromNbt(CompoundTag $tag) : self{
		$buyA = Item::nbtDeserialize($tag->getCompoundTag("buyA"));
		$buyB = null;
		if($tag->getTag("buyB") instanceof CompoundTag){
			$buyB = Item::nbtDeserialize($tag->getCompoundTag("buyB"));
		}
		$sell = Item::nbtDeserialize($tag->getCompoundTag("sell"));

		$maxUses = $tag->getInt("maxUses", 16);
		$uses = $tag->getInt("uses", 0);
		$tier = $tag->getInt("tier", 0) + 1;
		$traderExp = $tag->getInt("traderExp", 1);
		$rewardExp = $tag->getByte("rewardExp", 1) !== 0;
		$demand = $tag->getInt("demand", 0);
		$priceMultiplierA = $tag->getFloat("priceMultiplierA", 0.05);
		$priceMultiplierB = $tag->getFloat("priceMultiplierB", 0.05);

		return new self(
			$buyA,
			$buyB,
			$sell,
			$maxUses,
			$uses,
			$traderExp,
			$rewardExp,
			$tier,
			$priceMultiplierA,
			$priceMultiplierB,
			$demand
		);
	}
}
