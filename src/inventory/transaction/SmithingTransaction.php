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

use pocketmine\block\Block;
use pocketmine\crafting\CraftingRecipe;
use pocketmine\crafting\SmithingTransformRecipe;
use pocketmine\crafting\SmithingTrimRecipe;
use pocketmine\item\Item;
use pocketmine\player\Player;
use pocketmine\world\sound\AnvilUseSound;

class SmithingTransaction extends InventoryTransaction{

	private Item $result;
	/** @var Item[] */
	private array $consumed = [];

	public function __construct(
		Player $source,
		protected Block $holder,
		protected CraftingRecipe $recipe,
		protected Item $template,
		protected Item $input,
		protected Item $material,
		array $actions = []
	){
		if(!$template->isNull()){
			$this->consumed[] = clone $template;
		}
		if(!$input->isNull()){
			$this->consumed[] = clone $input;
		}
		if(!$material->isNull()){
			$this->consumed[] = clone $material;
		}
		parent::__construct($source, $actions);
		$this->calculateResult();
	}

	private function calculateResult() : void{
		if($this->recipe instanceof SmithingTransformRecipe){
			if(
				!$this->recipe->getTemplate()->accepts($this->template) ||
				!$this->recipe->getInput()->accepts($this->input) ||
				!$this->recipe->getAddition()->accepts($this->material)
			){
				throw new TransactionValidationException("Smithing ingredients do not match transform recipe");
			}
			$this->result = $this->recipe->transform($this->input);
			return;
		}

		if($this->recipe instanceof SmithingTrimRecipe){
			if(
				!$this->recipe->getTemplate()->accepts($this->template) ||
				!$this->recipe->getInput()->accepts($this->input) ||
				!$this->recipe->getAddition()->accepts($this->material)
			){
				throw new TransactionValidationException("Smithing ingredients do not match trim recipe");
			}
			$trimmed = $this->recipe->applyTrim($this->input, $this->template, $this->material);
			if($trimmed === null){
				throw new TransactionValidationException("Failed to apply armor trim to input item");
			}
			$this->result = $trimmed;
			return;
		}

		throw new TransactionValidationException("Invalid smithing recipe type");
	}

	public function getResult() : Item{
		return clone $this->result;
	}

	public function getRecipe() : CraftingRecipe{
		return $this->recipe;
	}

	/**
	 * @return Item[]
	 */
	public function getConsumed() : array{
		return $this->consumed;
	}

	public function execute() : void{
		parent::execute();
		$this->holder->getPosition()->getWorld()->addSound($this->holder->getPosition(), new AnvilUseSound());
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
			throw new TransactionValidationException("Smithing transaction did not result into any item");
		}
		if(count($createdItems) > 1){
			throw new TransactionValidationException("Smithing transaction resulted into more than 1 item stack");
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
				throw new TransactionValidationException("Transaction consumed items the smithing table does not use");
			}
		}
		if(count($remaining) > 0){
			throw new TransactionValidationException("Transaction did not consume the items the smithing table uses");
		}
	}
}
