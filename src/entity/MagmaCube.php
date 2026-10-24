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

use pocketmine\entity\Location;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\VanillaItems;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use function mt_rand;

class MagmaCube extends Slime{

	public static function getNetworkTypeId() : string{
		return EntityIds::MAGMA_CUBE;
	}

	public function getName() : string{
		return "Magma Cube";
	}

	public function attack(EntityDamageEvent $source) : void{
		if($source->getCause() === EntityDamageEvent::CAUSE_FIRE
			|| $source->getCause() === EntityDamageEvent::CAUSE_FIRE_TICK
			|| $source->getCause() === EntityDamageEvent::CAUSE_LAVA
		){
			$source->cancel();
			return;
		}

		parent::attack($source);
	}

	protected function createBabySlime(Location $location, int $size) : Slime{
		$magma = new MagmaCube($location);
		$magma->setSlimeSize($size);
		return $magma;
	}

	public function getDrops() : array{
		if($this->slimeSize === 1){
			return [
				VanillaItems::MAGMA_CREAM()->setCount(mt_rand(0, 1))
			];
		}
		return [];
	}

	public function getXpDropAmount() : int{
		return $this->slimeSize === 1 ? 1 : 0;
	}
}
