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

namespace pocketmine\block\inventory;

use pocketmine\inventory\SimpleInventory;
use pocketmine\world\Position;

class CrafterInventory extends SimpleInventory implements BlockInventory{
	use BlockInventoryTrait;

	private int $disabledSlots = 0;

	public function __construct(Position $holder, int $size = 9){
		$this->holder = $holder;
		parent::__construct($size);
	}

	public function isSlotDisabled(int $slot) : bool{
		return ($this->disabledSlots & (1 << $slot)) !== 0;
	}

	public function setSlotDisabled(int $slot, bool $disabled) : void{
		if($disabled){
			$this->disabledSlots |= (1 << $slot);
		}else{
			$this->disabledSlots &= ~(1 << $slot);
		}
	}

	public function getDisabledSlots() : int{
		return $this->disabledSlots;
	}

	public function setDisabledSlots(int $disabledSlots) : void{
		$this->disabledSlots = $disabledSlots & 0x1ff; // 9 bits
	}
}
