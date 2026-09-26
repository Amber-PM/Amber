<?php

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketMine Team
 * @link http://www.pocketmine.net/
 *
 *
 */

declare(strict_types=1);

namespace pocketmine\addon;

use pocketmine\timings\Timings;
use pocketmine\timings\TimingsHandler;

/**
 * Timings sections for the add-on runtime, so /timings reports show what add-ons cost.
 */
final class AddonTimings{
	public const GROUP = "Add-ons";

	public static TimingsHandler $entities;
	public static TimingsHandler $ai;
	public static TimingsHandler $pathfinding;
	public static TimingsHandler $scripts;
	public static TimingsHandler $spawner;

	private static bool $initialized = false;

	private function __construct(){
		//NOOP
	}

	public static function init() : void{
		if(self::$initialized){
			return;
		}
		self::$initialized = true;
		Timings::init();
		self::$entities = new TimingsHandler("Add-on entity runtime (events, sensors, timers)", Timings::$entityBaseTick, self::GROUP);
		self::$ai = new TimingsHandler("Add-on mob AI (behaviors)", self::$entities, self::GROUP);
		self::$pathfinding = new TimingsHandler("Add-on pathfinding", self::$ai, self::GROUP);
		self::$scripts = new TimingsHandler("Add-on scripts (Node.js host)", null, self::GROUP);
		self::$spawner = new TimingsHandler("Add-on natural spawning", null, self::GROUP);
	}
}
