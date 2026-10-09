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

use pocketmine\entity\Entity;
use pocketmine\math\AxisAlignedBB;
use pocketmine\player\Player;
use function abs;

final class Honey extends Transparent{
	public function getJumpVelocityMultiplier() : float{
		return 0.5;
	}

	public function getFallDamageMultiplier() : float{
		return 0.2;
	}

	public function hasEntityCollision() : bool{
		return true;
	}

	public function onEntityInside(Entity $entity) : bool{
		if($entity instanceof Player && $entity->isFlying()){
			return true;
		}
		$motion = $entity->getMotion();
		if($entity->isOnGround()){
			$entity->setMotion($motion->multiply(0.4)->withComponents(null, $motion->y, null));
			return true;
		}
		$pos = $entity->getPosition();
		$edge = 0.4375 + $entity->size->getWidth() / 2;
		if($motion->y <= 0.08 && (abs($this->position->x + 0.5 - $pos->x) + 0.001 > $edge || abs($this->position->z + 0.5 - $pos->z) + 0.001 > $edge)){
			if($motion->y < -0.13){
				$motion = $motion->multiply(-0.05 / $motion->y);
			}
			$entity->setMotion($motion->withComponents(null, -0.05, null));
			$entity->resetFallDistance();
		}
		return true;
	}

	protected function recalculateCollisionBoxes() : array{
		return [new AxisAlignedBB(0.0625, 0, 0.0625, 0.9375, 0.9375, 0.9375)];
	}
}
