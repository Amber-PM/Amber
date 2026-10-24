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

namespace pocketmine\inventory\villager;

use pocketmine\entity\villager\Trader;
use pocketmine\inventory\SimpleInventory;
use pocketmine\inventory\TemporaryInventory;

class TradeInventory extends SimpleInventory implements TemporaryInventory{
	public const SLOT_BUY_A = 0;
	public const SLOT_BUY_B = 1;

	public function __construct(
		private Trader $trader
	){
		parent::__construct(2);
	}

	public function getTrader() : Trader{
		return $this->trader;
	}

	public function getVillager() : Trader{
		return $this->trader;
	}

	public function getHolder() : Trader{
		return $this->trader;
	}
}
