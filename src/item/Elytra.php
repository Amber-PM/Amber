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

namespace pocketmine\item;

use pocketmine\inventory\ArmorInventory;
use pocketmine\item\enchantment\ItemEnchantmentTags;
use pocketmine\item\VanillaArmorMaterials as ArmorMaterials;
use function min;

class Elytra extends Armor{

	public function __construct(ItemIdentifier $identifier, string $name = "Elytra"){
		parent::__construct(
			$identifier,
			$name,
			new ArmorTypeInfo(0, 432, ArmorInventory::SLOT_CHEST, material: ArmorMaterials::LEATHER()),
			[ItemEnchantmentTags::ELYTRA]
		);
	}

	public function applyDamage(int $amount) : bool{
		if($this->isUnbreakable() || $this->isBroken()){
			return false;
		}

		$amount -= $this->getUnbreakingDamageReduction($amount);
		if($amount <= 0){
			return false;
		}

		// In vanilla, Elytra stops at 1 durability remaining (damage = 431) and does not break or pop
		$maxDamage = $this->getMaxDurability() - 1;
		$this->damage = min($this->damage + $amount, $maxDamage);
		return true;
	}

	public function isBroken() : bool{
		return $this->damage >= ($this->getMaxDurability() - 1) || $this->isNull();
	}
}
