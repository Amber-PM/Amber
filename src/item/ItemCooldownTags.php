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

/**
 * Tags used by items to determine their cooldown group.
 *
 * These tag values are not related to Minecraft internal IDs.
 * They only share a visual similarity because these are the most obvious values to use.
 * Any arbitrary string can be used.
 *
 * @see Item::getCooldownTag()
 */
final class ItemCooldownTags{

	private function __construct(){
		//NOOP
	}

	public const CHORUS_FRUIT = "chorus_fruit";
	public const ENDER_PEARL = "ender_pearl";
	public const SHIELD = "shield";
	public const GOAT_HORN = "goat_horn";
}
