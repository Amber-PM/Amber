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
use pocketmine\world\sound\ArmorEquipElytraSound;
use function min;

class Elytra extends Armor{

	public function __construct(ItemIdentifier $identifier, string $name = "Elytra"){
		parent::__construct(
			$identifier,
			$name,
			new ArmorTypeInfo(0, 432, ArmorInventory::SLOT_CHEST, material: new ArmorMaterial(0, new ArmorEquipElytraSound())),
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

		$maxDamage = $this->getMaxDurability() - 1;
		$this->damage = min($this->damage + $amount, $maxDamage);
		return true;
	}

	protected function getUnbreakingDamageReduction(int $amount) : int{
		return Durable::getUnbreakingDamageReduction($amount);
	}

	public function takesDamageFromAttack() : bool{
		return false;
	}

	public function isBroken() : bool{
		return $this->damage >= ($this->getMaxDurability() - 1) || $this->isNull();
	}
}
