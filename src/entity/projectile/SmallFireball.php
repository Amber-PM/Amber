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

namespace pocketmine\entity\projectile;

use pocketmine\entity\Living;
use pocketmine\entity\NeverSavedWithChunkEntity;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

class SmallFireball extends Throwable implements NeverSavedWithChunkEntity{

	public static function getNetworkTypeId() : string{ return EntityIds::SMALL_FIREBALL; }

	protected function getInitialGravity() : float{ return 0.0; }

	protected function getInitialDragMultiplier() : float{ return 0.0; }

	final public function canSaveWithChunk() : bool{
		return false;
	}

	public function launchTowards(Living $target) : void{
		$this->setMotion($target->getEyePos()->subtractVector($this->location)->normalize()->multiply(0.8));
		$this->spawnToAll();
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		$hasUpdate = parent::entityBaseTick($tickDiff);
		if($this->ticksLived >= 100){
			$this->flagForDespawn();
		}
		return $hasUpdate;
	}
}
