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

use pocketmine\item\VanillaItems;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use function mt_rand;

class GlowSquid extends Squid{

	public static function getNetworkTypeId() : string{
		return EntityIds::GLOW_SQUID;
	}

	public function getName() : string{
		return "Glow Squid";
	}

	public function getDrops() : array{
		$drops = [];
		$count = mt_rand(1, 3);
		$drops[] = VanillaItems::GLOW_INK_SAC()->setCount($count);
		return $drops;
	}
}
