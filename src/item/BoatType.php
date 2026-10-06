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

use pocketmine\block\utils\WoodType;
use pocketmine\utils\LegacyEnumShimTrait;

/**
 * TODO: These tags need to be removed once we get rid of LegacyEnumShimTrait (PM6)
 *  These are retained for backwards compatibility only.
 *
 * @method static BoatType ACACIA()
 * @method static BoatType BAMBOO()
 * @method static BoatType BIRCH()
 * @method static BoatType CHERRY()
 * @method static BoatType DARK_OAK()
 * @method static BoatType JUNGLE()
 * @method static BoatType MANGROVE()
 * @method static BoatType OAK()
 * @method static BoatType PALE_OAK()
 * @method static BoatType SPRUCE()
 */
enum BoatType{
	use LegacyEnumShimTrait;

	case OAK;
	case SPRUCE;
	case BIRCH;
	case JUNGLE;
	case ACACIA;
	case DARK_OAK;
	case MANGROVE;
	case BAMBOO;
	case CHERRY;
	case PALE_OAK;

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

	public function isRaft() : bool{
		return $this === self::BAMBOO;
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

	public function getDisplayName() : string{
		return $this->getWoodType()->getDisplayName();
	}
}
