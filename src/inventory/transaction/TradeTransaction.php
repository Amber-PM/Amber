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

namespace pocketmine\inventory\transaction;

use pocketmine\entity\villager\TradeOffer;
use pocketmine\entity\villager\Trader;
use pocketmine\inventory\transaction\action\InventoryAction;
use pocketmine\item\Item;
use pocketmine\player\Player;
use pocketmine\world\sound\PopSound;
use function count;
use function min;
use function mt_rand;

class TradeTransaction extends InventoryTransaction{

	private Item $result;
	/** @var Item[] */
	private array $consumed = [];

	/**
	 * @param InventoryAction[] $actions
	 */
	public function __construct(
		Player $source,
		protected Trader $villager,
		protected TradeOffer $offer,
		protected Item $firstItem,
		protected ?Item $secondItem = null,
		array $actions = []
	){
		if($offer->isSoldOut()){
			throw new TransactionValidationException("Trade offer is sold out");
		}

		$buyA = $offer->getBuyA();
		$buyB = $offer->getBuyB();

		if($firstItem->getTypeId() !== $buyA->getTypeId() || $firstItem->getCount() < $buyA->getCount()){
			throw new TransactionValidationException("Primary input does not match trade offer requirements");
		}

		$consumedA = clone $buyA;
		$this->consumed[] = $consumedA;

		if($buyB !== null && !$buyB->isNull()){
			if($secondItem === null || $secondItem->getTypeId() !== $buyB->getTypeId() || $secondItem->getCount() < $buyB->getCount()){
				throw new TransactionValidationException("Secondary input does not match trade offer requirements");
			}
			$consumedB = clone $buyB;
			$this->consumed[] = $consumedB;
		}

		$this->result = $offer->getSell();

		parent::__construct($source, $actions);
	}

	public function getVillager() : Trader{
		return $this->villager;
	}

	public function getTrader() : Trader{
		return $this->villager;
	}

	public function getOffer() : TradeOffer{
		return $this->offer;
	}

	public function getResult() : Item{
		return clone $this->result;
	}

	/**
	 * @return Item[]
	 */
	public function getConsumed() : array{
		return $this->consumed;
	}

	public function execute() : void{
		parent::execute();

		$this->offer->incrementUses();
		$this->villager->addExperience($this->offer->getTraderExp());

		$world = $this->villager->getWorld();
		$pos = $this->villager->getPosition();

		if($this->offer->rewardsExp()){
			$world->dropExperience($pos->add(0.5, 0.5, 0.5), mt_rand(1, 4));
		}

		$world->addSound($pos, new PopSound());
		$this->villager->sendTradeData($this->source);
	}

	public function validate() : void{
		$this->squashDuplicateSlotChanges();
		if(count($this->actions) < 1){
			throw new TransactionValidationException("Transaction must have at least one action to be executable");
		}
		$createdItems = [];
		$deletedItems = [];
		$this->matchItems($createdItems, $deletedItems);
		if(count($createdItems) === 0){
			throw new TransactionValidationException("Trade transaction did not result into any item");
		}
		if(count($createdItems) > 1){
			throw new TransactionValidationException("Trade transaction resulted into more than 1 item stack");
		}
		if(!$createdItems[0]->equalsExact($this->result)){
			throw new TransactionValidationException("Transaction produced a different output item");
		}
		self::assertConsumed($deletedItems, $this->consumed);
	}

	/**
	 * @param Item[] $deleted
	 * @param Item[] $expected
	 */
	private static function assertConsumed(array $deleted, array $expected) : void{
		$remaining = [];
		foreach($expected as $item){
			if(!$item->isNull()){
				$remaining[] = clone $item;
			}
		}
		foreach($deleted as $item){
			$count = $item->getCount();
			foreach($remaining as $key => $want){
				if($count > 0 && $want->canStackWith($item)){
					$taken = min($count, $want->getCount());
					$count -= $taken;
					$want->setCount($want->getCount() - $taken);
					if($want->getCount() === 0){
						unset($remaining[$key]);
					}
				}
			}
			if($count > 0){
				throw new TransactionValidationException("Transaction consumed items the trade does not use");
			}
		}
		if(count($remaining) > 0){
			throw new TransactionValidationException("Transaction did not consume the items the trade uses");
		}
	}
}
