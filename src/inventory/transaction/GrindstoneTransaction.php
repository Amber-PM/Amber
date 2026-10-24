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
use pocketmine\item\Durable;
use pocketmine\item\EnchantedBook;
use pocketmine\item\enchantment\Enchantment;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\enchantment\Rarity;
use pocketmine\item\enchantment\VanillaEnchantments;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\player\Player;
use pocketmine\world\sound\XpCollectSound;
use function ceil;
use function floor;
use function min;
use function mt_rand;
use function str_contains;
use function strtolower;

class GrindstoneTransaction extends InventoryTransaction{

	private Item $result;
	private int $xp = 0;
	/** @var Item[] */
	private array $consumed = [];

	public function __construct(
		Player $source,
		protected Block $holder,
		protected Item $firstItem,
		protected Item $secondItem,
		array $actions = []
	){
		if(!$firstItem->isNull()){
			$this->consumed[] = clone $firstItem;
		}
		if(!$secondItem->isNull()){
			$this->consumed[] = clone $secondItem;
		}
		parent::__construct($source, $actions);
		$this->calculateResult();
	}

	public static function isCurse(Enchantment $enchantment) : bool{
		if($enchantment === VanillaEnchantments::VANISHING()){
			return true;
		}
		$name = $enchantment->getName();
		$str = $name instanceof \pocketmine\lang\Translatable ? $name->getText() : $name;
		return str_contains(strtolower($str), "curse");
	}

	private static function calculateXpForEnchantment(EnchantmentInstance $instance) : int{
		$type = $instance->getType();
		$multiplier = match($type->getRarity()){
			Rarity::COMMON => 1,
			Rarity::UNCOMMON => 2,
			Rarity::RARE => 4,
			Rarity::MYTHIC => 8,
			default => 1,
		};
		$value = $multiplier * $instance->getLevel();
		$min = (int) ceil($value / 2);
		return mt_rand($min, $value);
	}

	private function calculateResult() : void{
		$item1 = $this->firstItem;
		$item2 = $this->secondItem;

		if($item1->isNull() && $item2->isNull()){
			throw new TransactionValidationException("Both grindstone input slots are empty");
		}

		// Handle two items
		if(!$item1->isNull() && !$item2->isNull()){
			if($item1->getTypeId() !== $item2->getTypeId()){
				throw new TransactionValidationException("Grindstone cannot combine different item types");
			}

			if(!$item1 instanceof Durable || !$item2 instanceof Durable){
				throw new TransactionValidationException("Grindstone can only combine durable items");
			}

			$maxDurability = $item1->getMaxDurability();
			$uses1 = $maxDurability - $item1->getDamage();
			$uses2 = $maxDurability - $item2->getDamage();
			$bonus = (int) floor($maxDurability * 0.05);
			$newUses = min($maxDurability, $uses1 + $uses2 + $bonus);
			$newDamage = $maxDurability - $newUses;

			$result = clone $item1;
			$result->setDamage($newDamage);
			$result->setCount(1);
			$result->clearCustomName();

			// Strip non-curse enchantments and collect XP
			$curses = [];
			foreach([$item1, $item2] as $item){
				foreach($item->getEnchantments() as $ench){
					if(self::isCurse($ench->getType())){
						$curses[] = $ench;
					}else{
						$this->xp += self::calculateXpForEnchantment($ench);
					}
				}
			}

			// Re-apply curses only
			$result->removeEnchantments();
			foreach($curses as $curse){
				$result->addEnchantment($curse);
			}

			$this->result = $result;
			return;
		}

		// Single item
		$single = !$item1->isNull() ? $item1 : $item2;
		if($single instanceof EnchantedBook){
			$result = VanillaItems::BOOK();
			$hasNonCurse = false;
			foreach($single->getEnchantments() as $ench){
				if(self::isCurse($ench->getType())){
					$result->addEnchantment($ench);
				}else{
					$this->xp += self::calculateXpForEnchantment($ench);
					$hasNonCurse = true;
				}
			}
			if(!$hasNonCurse){
				throw new TransactionValidationException("Enchanted book has no non-curse enchantments to remove");
			}
			$this->result = $result;
			return;
		}

		if($single->hasEnchantments()){
			$result = clone $single;
			$result->setCount(1);
			$result->clearCustomName();

			$curses = [];
			$hasNonCurse = false;
			foreach($single->getEnchantments() as $ench){
				if(self::isCurse($ench->getType())){
					$curses[] = $ench;
				}else{
					$this->xp += self::calculateXpForEnchantment($ench);
					$hasNonCurse = true;
				}
			}
			if(!$hasNonCurse){
				throw new TransactionValidationException("Item has no non-curse enchantments to remove");
			}
			$result->removeEnchantments();
			foreach($curses as $curse){
				$result->addEnchantment($curse);
			}
			$this->result = $result;
			return;
		}

		throw new TransactionValidationException("Item cannot be disenchanted or repaired in a grindstone");
	}

	public function getResult() : Item{
		return clone $this->result;
	}

	public function getXp() : int{
		return $this->xp;
	}

	/**
	 * @return Item[]
	 */
	public function getConsumed() : array{
		return $this->consumed;
	}

	public function execute() : void{
		parent::execute();
		if($this->xp > 0){
			$this->holder->getPosition()->getWorld()->dropExperience($this->holder->getPosition()->add(0.5, 0.5, 0.5), $this->xp);
			$this->holder->getPosition()->getWorld()->addSound($this->holder->getPosition(), new XpCollectSound());
		}
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
			throw new TransactionValidationException("Grindstone transaction did not result into any item");
		}
		if(count($createdItems) > 1){
			throw new TransactionValidationException("Grindstone transaction resulted into more than 1 item stack");
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
				throw new TransactionValidationException("Transaction consumed items the grindstone does not use");
			}
		}
		if(count($remaining) > 0){
			throw new TransactionValidationException("Transaction did not consume the items the grindstone uses");
		}
	}
}
