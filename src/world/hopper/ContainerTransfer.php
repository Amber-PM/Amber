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

namespace pocketmine\world\hopper;

use pocketmine\block\tile\Container;
use pocketmine\item\Item;
use function min;

final class ContainerTransfer{
	public function __construct(private ContainerTransferPolicy $policy){}

	public function insertOne(Container $target, Item $item, int $side) : bool{
		if(!$target instanceof \pocketmine\block\tile\Tile || $target->isClosed() || !$this->policy->isSupported($target)){
			return false;
		}
		$slots = $this->policy->getInsertableSlots($target, $item, $side);
		$inventory = $target->getInventory();
		$one = (clone $item)->setCount(1);
		$empty = null;
		foreach($slots as $slot){
			$existing = $inventory->getItem($slot);
			if($existing->isNull()){ $empty ??= $slot; continue; }
			if($existing->canStackWith($one) && $existing->getCount() < min($inventory->getMaxStackSize(), $existing->getMaxStackSize())){
				$inventory->setItem($slot, $existing->setCount($existing->getCount() + 1));
				return true;
			}
		}
		if($empty !== null && min($inventory->getMaxStackSize(), $one->getMaxStackSize()) >= 1){ $inventory->setItem($empty, $one); return true; }
		return false;
	}
}
