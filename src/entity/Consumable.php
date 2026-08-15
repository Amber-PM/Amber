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

namespace pocketmine\entity;

use pocketmine\entity\effect\EffectInstance;

/**
 * Interface implemented by objects that can be consumed by mobs.
 */
interface Consumable{
	/**
	 * @return EffectInstance[]
	 */
	public function getAdditionalEffects() : array;

	/**
	 * Called when this Consumable is consumed by mob, after standard resulting effects have been applied.
	 */
	public function onConsume(Living $consumer) : void;
}
