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

namespace pocketmine\world\redstone;

use function max;
use function min;

final class RedstoneBudget{
	public function __construct(private int $remaining = 0){}
	public function remaining() : int{ return $this->remaining; }
	public function reset(int $limit) : void{ $this->remaining = max(0, $limit); }
	public function consume(int $amount) : int{
		$consumed = min($this->remaining, max(0, $amount));
		$this->remaining -= $consumed;
		return $consumed;
	}
}
