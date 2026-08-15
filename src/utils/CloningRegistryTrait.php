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

namespace pocketmine\utils;

/**
 * This trait offers the same functionality as RegistryTrait, but also clones any returned objects to prevent outside
 * modification.
 *
 * @deprecated Superseded by {@link RegistrySource}
 * @see CloningRegistrySource
 */
trait CloningRegistryTrait{
	use RegistryTrait;

	protected static function preprocessMember(object $member) : object{
		return clone $member;
	}
}
