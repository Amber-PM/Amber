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

namespace pocketmine\event;

/**
 * @internal
 * @phpstan-template TEvent of Event
 */
final class RegisteredListenerCache{

	/**
	 * List of all handlers that will be called for a particular event, ordered by execution order.
	 *
	 * @var RegisteredListener[]
	 * @phpstan-var list<RegisteredListener<TEvent>>
	 */
	public ?array $list = null;
}
