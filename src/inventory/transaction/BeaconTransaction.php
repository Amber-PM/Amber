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

use pocketmine\block\tile\Beacon as TileBeacon;
use pocketmine\item\Item;
use pocketmine\player\Player;
use pocketmine\world\sound\XpLevelUpSound;

class BeaconTransaction extends InventoryTransaction{

	/** @var Item[] */
	private array $consumed = [];

	public function __construct(
		Player $source,
		protected TileBeacon $holder,
		protected Item $paymentItem,
		protected int $primaryEffectId,
		protected int $secondaryEffectId,
		array $actions = []
	){
		if(!$paymentItem->isNull()){
			$this->consumed[] = (clone $paymentItem)->setCount(1);
		}
		parent::__construct($source, $actions);
		$this->validatePayment();
	}

	private function validatePayment() : void{
		if($this->paymentItem->isNull() || !TileBeacon::isValidPaymentItem($this->paymentItem)){
			throw new TransactionValidationException("Invalid beacon payment item");
		}

		$pyramidLevel = $this->holder->calculatePyramid();
		if($pyramidLevel < 1){
			throw new TransactionValidationException("Beacon pyramid is not built");
		}

		// Validate effect choices based on tier
		if($this->primaryEffectId > 0){
			$validPrimary = match($pyramidLevel){
				1 => in_array($this->primaryEffectId, [TileBeacon::EFFECT_SPEED, TileBeacon::EFFECT_HASTE], true),
				2 => in_array($this->primaryEffectId, [TileBeacon::EFFECT_SPEED, TileBeacon::EFFECT_HASTE, TileBeacon::EFFECT_RESISTANCE, TileBeacon::EFFECT_JUMP_BOOST], true),
				3, 4 => in_array($this->primaryEffectId, [TileBeacon::EFFECT_SPEED, TileBeacon::EFFECT_HASTE, TileBeacon::EFFECT_RESISTANCE, TileBeacon::EFFECT_JUMP_BOOST, TileBeacon::EFFECT_STRENGTH], true),
				default => false
			};
			if(!$validPrimary){
				throw new TransactionValidationException("Primary effect is not unlocked at pyramid tier $pyramidLevel");
			}
		}

		if($this->secondaryEffectId > 0){
			if($pyramidLevel < 4){
				throw new TransactionValidationException("Secondary effect requires tier 4 pyramid");
			}
			$validSecondary = ($this->secondaryEffectId === $this->primaryEffectId) || ($this->secondaryEffectId === TileBeacon::EFFECT_REGENERATION);
			if(!$validSecondary){
				throw new TransactionValidationException("Invalid secondary effect");
			}
		}
	}

	public function execute() : void{
		parent::execute();
		$this->holder->setPrimaryEffect($this->primaryEffectId);
		$this->holder->setSecondaryEffect($this->secondaryEffectId);
		$this->holder->applyEffects();
		$this->holder->getPosition()->getWorld()->addSound($this->holder->getPosition(), new XpLevelUpSound(100));
	}

	public function validate() : void{
		$this->squashDuplicateSlotChanges();
		if(count($this->actions) < 1){
			throw new TransactionValidationException("Transaction must have at least one action to be executable");
		}
		$createdItems = [];
		$deletedItems = [];
		$this->matchItems($createdItems, $deletedItems);
		if(count($createdItems) > 0){
			throw new TransactionValidationException("Beacon payment should not produce created items");
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
				throw new TransactionValidationException("Transaction consumed items the beacon does not use");
			}
		}
		if(count($remaining) > 0){
			throw new TransactionValidationException("Transaction did not consume the items the beacon uses");
		}
	}

	/**
	 * @return Item[]
	 */
	public function getConsumed() : array{
		return $this->consumed;
	}
}
