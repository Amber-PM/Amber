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

namespace pocketmine\block\utils;

use pocketmine\data\runtime\RuntimeDataDescriber;
use pocketmine\world\redstone\RedstoneEngine;

trait RailPoweredByRedstoneTrait{
	use PoweredByRedstoneTrait;

	protected function describeBlockOnlyState(RuntimeDataDescriber $w) : void{
		parent::describeBlockOnlyState($w);
		$w->bool($this->powered);
	}

	public function onRedstoneUpdate(RedstoneEngine $engine) : void{
		$powered = $engine->getReceivedPower($this->position) > 0;
		if($powered !== $this->powered){
			$engine->getWorld()->setBlock($this->position, $this->setPowered($powered));
		}
	}
}
