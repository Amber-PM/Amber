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

namespace pocketmine\inventory\transaction\action\validator;

use pocketmine\inventory\Inventory;
use pocketmine\inventory\transaction\TransactionValidationException;
use pocketmine\item\Item;
use pocketmine\utils\Utils;

class CallbackSlotValidator implements SlotValidator{
	/**
	 * @phpstan-param \Closure(Inventory, Item, int) : ?TransactionValidationException $validate
	 */
	public function __construct(
		private \Closure $validate
	){
		Utils::validateCallableSignature(function(Inventory $inventory, Item $item, int $slot) : ?TransactionValidationException{ return null; }, $validate);
	}

	public function validate(Inventory $inventory, Item $item, int $slot) : ?TransactionValidationException{
		return ($this->validate)($inventory, $item, $slot);
	}
}
