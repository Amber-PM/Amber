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

use pocketmine\item\Food;
use pocketmine\item\ItemIdentifier;

/** A custom item with "minecraft:food". */
class AddonFoodItem extends Food{
	use AddonItemTrait;

	public function __construct(ItemIdentifier $identifier, AddonItemDefinition $definition){
		$this->addonDefinition = $definition;
		parent::__construct($identifier, $definition->getDisplayName());
	}

	public function getFoodRestore() : int{
		return $this->addonDefinition->getFood()["nutrition"] ?? 0;
	}

	public function getSaturationRestore() : float{
		return $this->addonDefinition->getFood()["saturation"] ?? 0.0;
	}

	public function requiresHunger() : bool{
		return !($this->addonDefinition->getFood()["canAlwaysEat"] ?? false);
	}
}
