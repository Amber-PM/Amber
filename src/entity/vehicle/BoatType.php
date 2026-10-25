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

namespace pocketmine\entity\vehicle;

use pocketmine\block\utils\WoodType;

enum BoatType : string{
	case OAK = "oak";
	case SPRUCE = "spruce";
	case BIRCH = "birch";
	case JUNGLE = "jungle";
	case ACACIA = "acacia";
	case DARK_OAK = "dark_oak";
	case MANGROVE = "mangrove";
	case CHERRY = "cherry";
	case PALE_OAK = "pale_oak";
	case BAMBOO = "bamboo";

	public function isRaft() : bool{
		return $this === self::BAMBOO;
	}

	public function getWoodType() : WoodType{
		return match($this){
			self::OAK => WoodType::OAK,
			self::SPRUCE => WoodType::SPRUCE,
			self::BIRCH => WoodType::BIRCH,
			self::JUNGLE => WoodType::JUNGLE,
			self::ACACIA => WoodType::ACACIA,
			self::DARK_OAK => WoodType::DARK_OAK,
			self::MANGROVE => WoodType::MANGROVE,
			self::BAMBOO => WoodType::BAMBOO,
			self::CHERRY => WoodType::CHERRY,
			self::PALE_OAK => WoodType::PALE_OAK,
		};
	}

	public function getVariantId() : int{
		return match($this){
			self::OAK => 0,
			self::SPRUCE => 1,
			self::BIRCH => 2,
			self::JUNGLE => 3,
			self::ACACIA => 4,
			self::DARK_OAK => 5,
			self::MANGROVE => 6,
			self::BAMBOO => 7,
			self::CHERRY => 8,
			self::PALE_OAK => 9,
		};
	}

	public static function fromVariantId(int $variant) : self{
		return match($variant){
			0 => self::OAK,
			1 => self::SPRUCE,
			2 => self::BIRCH,
			3 => self::JUNGLE,
			4 => self::ACACIA,
			5 => self::DARK_OAK,
			6 => self::MANGROVE,
			7 => self::BAMBOO,
			8 => self::CHERRY,
			9 => self::PALE_OAK,
			default => self::OAK,
		};
	}

	public function getDisplayName() : string{
		return $this->getWoodType()->getDisplayName();
	}
}
