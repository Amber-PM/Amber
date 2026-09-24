<?php

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketMine Team
 * @link http://www.pocketmine.net/
 *
 *
 */

declare(strict_types=1);

namespace pocketmine\addon\entity\ai\goal;

use pocketmine\addon\entity\ai\Goal;
use pocketmine\block\Water;
use function max;
use function min;

/**
 * minecraft:behavior.float: keeps the mob's head above water.
 */
final class FloatGoal extends Goal{
	public function getFlags() : int{ return self::FLAG_JUMP; }

	public function canStart() : bool{
		$world = $this->mob->getWorld();
		$pos = $this->mob->getPosition();
		return $world->getBlock($pos) instanceof Water || $this->mob->isUnderwater();
	}

	public function tick() : void{
		$motion = $this->mob->getMotion();
		if($motion->y < 0.12){
			$this->mob->setMotion($motion->withComponents(null, min(0.12, max($motion->y, 0.0) + 0.04), null));
		}
	}
}
