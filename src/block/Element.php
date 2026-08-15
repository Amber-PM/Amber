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

class Element extends Opaque{
	public function __construct(
		BlockIdentifier $idInfo,
		string $name,
		BlockTypeInfo $typeInfo,
		private string $symbol,
		private int $atomicWeight,
		private int $group
	){
		parent::__construct($idInfo, $name, $typeInfo);
	}

	public function getAtomicWeight() : int{
		return $this->atomicWeight;
	}

	public function getGroup() : int{
		return $this->group;
	}

	public function getSymbol() : string{
		return $this->symbol;
	}
}
