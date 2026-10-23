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

namespace pocketmine\item;

use pocketmine\data\runtime\RuntimeDataDescriber;
use pocketmine\entity\effect\EffectInstance;

class Arrow extends Item{

	private ?PotionType $tipType = null;

	protected function describeState(RuntimeDataDescriber $w) : void{
		$tipped = $this->tipType !== null;
		$w->bool($tipped);
		if($tipped){
			$tipType = $this->tipType ?? PotionType::WATER;
			$w->enum($tipType);
			$this->tipType = $tipType;
		}else{
			$this->tipType = null;
		}
	}

	public function getTipType() : ?PotionType{ return $this->tipType; }

	/**
	 * @return $this
	 */
	public function setTipType(?PotionType $type) : self{
		$this->tipType = $type;
		return $this;
	}

	/**
	 * @return EffectInstance[]
	 * @phpstan-return list<EffectInstance>
	 */
	public function getPotionEffects() : array{
		return $this->tipType?->getEffects() ?? [];
	}
}
