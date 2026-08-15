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

namespace pocketmine\block\utils;

use pocketmine\utils\LegacyEnumShimTrait;

/**
 * TODO: These tags need to be removed once we get rid of LegacyEnumShimTrait (PM6)
 *  These are retained for backwards compatibility only.
 *
 * @method static SupportType CENTER()
 * @method static SupportType EDGE()
 * @method static SupportType FULL()
 * @method static SupportType NONE()
 */
enum SupportType{
	use LegacyEnumShimTrait;

	case FULL;
	case CENTER;
	case EDGE;
	case NONE;

	public function hasEdgeSupport() : bool{
		return $this === self::EDGE || $this === self::FULL;
	}

	public function hasCenterSupport() : bool{
		return $this === self::CENTER || $this === self::FULL;
	}
}
