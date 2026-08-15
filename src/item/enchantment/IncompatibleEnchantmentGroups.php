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

namespace pocketmine\item\enchantment;

/**
 * Constants for groupings of incompatible enchantments.
 * Enchantments belonging to the same incompatibility group cannot be applied side-by-side on the same item.
 */
final class IncompatibleEnchantmentGroups{
	public const PROTECTION = "protection";
	public const BOW_INFINITE = "bow_infinite";
	public const BLOCK_DROPS = "block_drops";
}
