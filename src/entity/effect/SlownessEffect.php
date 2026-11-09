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

namespace pocketmine\entity\effect;

use pocketmine\entity\Living;
use function min;

class SlownessEffect extends Effect
{
	//level 7 would reach multiplier of 0 or below, which cannot be reverted by dividing in remove
	//so we just cap the effective level.
	//the multiplier stays positive and the effect is always reversible
	private const MAX_EFFECTIVE_LEVEL = 6;

	private static function getSpeedMultiplier(EffectInstance $instance): float
	{
		return 1 -
			0.15 * min($instance->getEffectLevel(), self::MAX_EFFECTIVE_LEVEL);
	}

	public function add(Living $entity, EffectInstance $instance): void
	{
		$entity->setMovementSpeed(
			$entity->getMovementSpeed() * self::getSpeedMultiplier($instance),
			true,
		);
	}

	public function remove(Living $entity, EffectInstance $instance): void
	{
		$entity->setMovementSpeed(
			$entity->getMovementSpeed() / self::getSpeedMultiplier($instance),
		);
	}
}
