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

namespace pocketmine\player\chat;

use pocketmine\lang\Translatable;

/**
 * Formats chat messages for broadcasting. Used in PlayerChatEvent.
 */
interface ChatFormatter{
	/**
	 * Returns the formatted message to broadcast.
	 * This can return a plain string (which will be used as-is) or a Translatable (which will be translated into
	 * each recipient's language).
	 */
	public function format(string $username, string $message) : Translatable|string;
}
