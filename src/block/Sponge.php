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

namespace pocketmine\block;

use pocketmine\data\runtime\RuntimeDataDescriber;

class Sponge extends Opaque{
	protected bool $wet = false;

	public function describeBlockItemState(RuntimeDataDescriber $w) : void{
		$w->bool($this->wet);
	}

	public function isWet() : bool{ return $this->wet; }

	/** @return $this */
	public function setWet(bool $wet) : self{
		$this->wet = $wet;
		return $this;
	}
}
