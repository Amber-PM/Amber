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

namespace pocketmine\addon\item;

use pocketmine\inventory\ArmorInventory;
use pocketmine\item\Armor;
use pocketmine\item\ArmorTypeInfo;
use pocketmine\item\ItemIdentifier;

/** A custom item with "minecraft:wearable" in an armour slot. */
class AddonArmorItem extends Armor{
	use AddonItemTrait;

	public function __construct(ItemIdentifier $identifier, AddonItemDefinition $definition){
		$this->addonDefinition = $definition;
		$slot = match($definition->getArmorSlot()){
			"slot.armor.head" => ArmorInventory::SLOT_HEAD,
			"slot.armor.legs" => ArmorInventory::SLOT_LEGS,
			"slot.armor.feet" => ArmorInventory::SLOT_FEET,
			default => ArmorInventory::SLOT_CHEST,
		};
		$durability = $definition->getMaxDurability();
		parent::__construct($identifier, $definition->getDisplayName(), new ArmorTypeInfo(
			$definition->getArmorProtection(),
			$durability > 0 ? $durability : 1,
			$slot
		));
	}

	public function getMaxStackSize() : int{
		return 1;
	}
}
