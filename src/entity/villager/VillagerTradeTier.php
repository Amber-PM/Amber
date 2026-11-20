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

namespace pocketmine\entity\villager;

final class VillagerTradeTier{
	public const NOVICE = 1;
	public const APPRENTICE = 2;
	public const JOURNEYMAN = 3;
	public const EXPERT = 4;
	public const MASTER = 5;

	public const EXP_NOVICE = 0;
	public const EXP_APPRENTICE = 10;
	public const EXP_JOURNEYMAN = 70;
	public const EXP_EXPERT = 150;
	public const EXP_MASTER = 250;

	private function __construct(){
		// NOOP
	}

	public static function getExpRequirement(int $tier) : int{
		return match($tier){
			self::NOVICE => self::EXP_NOVICE,
			self::APPRENTICE => self::EXP_APPRENTICE,
			self::JOURNEYMAN => self::EXP_JOURNEYMAN,
			self::EXPERT => self::EXP_EXPERT,
			self::MASTER => self::EXP_MASTER,
			default => self::EXP_MASTER,
		};
	}

	public static function getTierFromExp(int $exp) : int{
		if($exp >= self::EXP_MASTER){
			return self::MASTER;
		}
		if($exp >= self::EXP_EXPERT){
			return self::EXPERT;
		}
		if($exp >= self::EXP_JOURNEYMAN){
			return self::JOURNEYMAN;
		}
		if($exp >= self::EXP_APPRENTICE){
			return self::APPRENTICE;
		}
		return self::NOVICE;
	}

	public static function getDisplayName(int $tier) : string{
		return match($tier){
			self::NOVICE => "Novice",
			self::APPRENTICE => "Apprentice",
			self::JOURNEYMAN => "Journeyman",
			self::EXPERT => "Expert",
			self::MASTER => "Master",
			default => "Novice",
		};
	}
}
