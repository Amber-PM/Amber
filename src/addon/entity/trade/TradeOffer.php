<?php

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketMine Team
 * @link http://www.pocketmine.net/
 *
 *
 */

declare(strict_types=1);

namespace pocketmine\addon\entity\trade;

use pocketmine\item\Item;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\convert\TypeConverter;

/**
 * One trade a mob offers: what it wants (one or two stacks), what it gives, and how often it can be used.
 */
final class TradeOffer{
	public function __construct(
		public readonly int $netId,
		public readonly Item $wantsA,
		public readonly ?Item $wantsB,
		public readonly Item $gives,
		public readonly int $tier,
		public readonly int $maxUses,
		public readonly int $traderExp,
		public readonly bool $rewardExp,
		public int $uses = 0
	){}

	public function isAvailable() : bool{
		return $this->uses < $this->maxUses;
	}

	/** The offer as the trade UI reads it (UpdateTradePacket), with item names for this client's protocol. */
	public function toNbt(TypeConverter $converter) : CompoundTag{
		$tag = CompoundTag::create()
			->setTag("buyA", self::itemNbt($this->wantsA, $converter))
			->setInt("buyCountA", $this->wantsA->getCount())
			->setInt("buyCountB", $this->wantsB?->getCount() ?? 0)
			->setTag("sell", self::itemNbt($this->gives, $converter))
			->setInt("maxUses", $this->maxUses)
			->setInt("uses", $this->uses)
			->setInt("tier", $this->tier)
			->setInt("traderExp", $this->traderExp)
			->setByte("rewardExp", $this->rewardExp ? 1 : 0)
			->setInt("demand", 0)
			->setFloat("priceMultiplierA", 0.0)
			->setFloat("priceMultiplierB", 0.0)
			->setInt("netId", $this->netId);
		if($this->wantsB !== null){
			$tag->setTag("buyB", self::itemNbt($this->wantsB, $converter));
		}
		return $tag;
	}

	private static function itemNbt(Item $item, TypeConverter $converter) : CompoundTag{
		$stack = $converter->coreItemStackToNet($item);
		$tag = CompoundTag::create()
			->setString("Name", $converter->getItemTypeDictionary()->fromIntId($stack->getId()))
			->setByte("Count", $item->getCount())
			->setShort("Damage", $stack->getMeta())
			->setByte("WasPickedUp", 0);
		$nbt = $item->getNamedTag();
		if($nbt->count() > 0){
			$tag->setTag("tag", $nbt);
		}
		return $tag;
	}

	/** Saved with the mob, so its offers (and their uses) survive restarts. */
	public function save() : CompoundTag{
		$tag = CompoundTag::create()
			->setTag("A", $this->wantsA->nbtSerialize())
			->setTag("G", $this->gives->nbtSerialize())
			->setInt("Tier", $this->tier)
			->setInt("Max", $this->maxUses)
			->setInt("Exp", $this->traderExp)
			->setByte("Reward", $this->rewardExp ? 1 : 0)
			->setInt("Uses", $this->uses);
		if($this->wantsB !== null){
			$tag->setTag("B", $this->wantsB->nbtSerialize());
		}
		return $tag;
	}

	public static function load(CompoundTag $tag, int $netId) : ?self{
		$a = $tag->getCompoundTag("A");
		$g = $tag->getCompoundTag("G");
		if($a === null || $g === null){
			return null;
		}
		try{
			$b = $tag->getCompoundTag("B");
			return new self($netId, Item::nbtDeserialize($a), $b !== null ? Item::nbtDeserialize($b) : null, Item::nbtDeserialize($g),
				$tag->getInt("Tier", 0), $tag->getInt("Max", 1), $tag->getInt("Exp", 0), $tag->getByte("Reward", 1) === 1, $tag->getInt("Uses", 0));
		}catch(\Throwable){
			return null;
		}
	}
}
