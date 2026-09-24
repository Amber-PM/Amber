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

use pocketmine\inventory\transaction\InventoryTransaction;
use pocketmine\inventory\transaction\TransactionValidationException;
use pocketmine\item\Item;
use pocketmine\player\Player;
use function count;

/**
 * One use (or several) of a trade offer: the player must pay the offer's ingredients and receive exactly what
 * it gives. On success the offer's uses and the trader's experience go up.
 */
final class TradeTransaction extends InventoryTransaction{
	public function __construct(
		Player $source,
		private TradeInventory $inventory,
		private TradeOffer $offer,
		private int $repetitions
	){
		parent::__construct($source);
	}

	/** The item the player receives. */
	public function getResult() : Item{
		return (clone $this->offer->gives)->setCount($this->offer->gives->getCount() * $this->repetitions);
	}

	/** @return array{Item, ?Item} what the player pays */
	public static function price(TradeOffer $offer, int $repetitions) : array{
		return [
			(clone $offer->wantsA)->setCount($offer->wantsA->getCount() * $repetitions),
			$offer->wantsB === null ? null : (clone $offer->wantsB)->setCount($offer->wantsB->getCount() * $repetitions),
		];
	}

	/**
	 * Checks payment and result. Kept static so the rule is testable on its own.
	 *
	 * @param Item[] $inputs  items the player gave up
	 * @param Item[] $outputs items the player received
	 * @throws TransactionValidationException
	 */
	public static function check(TradeOffer $offer, int $repetitions, array $inputs, array $outputs) : void{
		if(!$offer->isAvailable() || $offer->uses + $repetitions > $offer->maxUses){
			throw new TransactionValidationException("This trade is used up");
		}
		[$wantA, $wantB] = self::price($offer, $repetitions);
		foreach([$wantA, $wantB] as $want){
			if($want === null){
				continue;
			}
			$paid = 0;
			foreach($inputs as $input){
				if($input->canStackWith($want)){
					$paid += $input->getCount();
				}
			}
			if($paid < $want->getCount()){
				throw new TransactionValidationException("Not enough " . $want->getName() . " paid: $paid of " . $want->getCount());
			}
		}
		if(count($outputs) !== 1){
			throw new TransactionValidationException("Expected one traded item, got " . count($outputs));
		}
		$result = (clone $offer->gives)->setCount($offer->gives->getCount() * $repetitions);
		if(!$outputs[0]->equalsExact($result)){
			throw new TransactionValidationException("The traded item does not match the offer");
		}
	}

	public function validate() : void{
		if(count($this->actions) < 1){
			throw new TransactionValidationException("Transaction must have at least one action to be executable");
		}
		/** @var Item[] $inputs */
		$inputs = [];
		/** @var Item[] $outputs */
		$outputs = [];
		$this->matchItems($outputs, $inputs);
		self::check($this->offer, $this->repetitions, $inputs, $outputs);
	}

	public function execute() : void{
		parent::execute();
		$this->offer->uses += $this->repetitions;
		$this->inventory->getTrader()->onTraded($this->source, $this->offer, $this->repetitions);
	}
}
