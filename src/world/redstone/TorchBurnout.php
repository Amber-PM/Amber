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

namespace pocketmine\world\redstone;

use pocketmine\math\Vector3;
use pocketmine\world\World;
use function count;

/**
 * Torches that toggle too often in a short time burn out for a while, as in the game, which stops fast clocks.
 */
final class TorchBurnout{
	private const TOGGLES = 8;
	private const WINDOW = 60;
	private const DURATION = 160;

	/** @var array<int, list<int>> torch hash => ticks it toggled at */
	private array $toggles = [];
	/** @var array<int, int> torch hash => tick its burnout ends */
	private array $burntOut = [];

	public function isBurntOut(Vector3 $pos, int $currentTick) : bool{
		$hash = self::hash($pos);
		if(($this->burntOut[$hash] ?? 0) > $currentTick){
			return true;
		}
		unset($this->burntOut[$hash]);
		return false;
	}

	/** Records a toggle of the torch; when it burns out, it is re-evaluated once the burnout ends. */
	public function recordToggle(Vector3 $pos, RedstoneEngine $engine) : void{
		$hash = self::hash($pos);
		$currentTick = $engine->getCurrentTick();
		$recent = [];
		foreach($this->toggles[$hash] ?? [] as $tick){
			if($currentTick - $tick < self::WINDOW){
				$recent[] = $tick;
			}
		}
		$recent[] = $currentTick;
		if(count($recent) > self::TOGGLES){
			$this->burntOut[$hash] = $currentTick + self::DURATION;
			$recent = [];
			$engine->schedule($pos, self::DURATION + 1);
		}
		$this->toggles[$hash] = $recent;
	}

	public function forget(int $hash) : void{
		unset($this->toggles[$hash], $this->burntOut[$hash]);
	}

	private static function hash(Vector3 $pos) : int{
		return World::blockHash($pos->getFloorX(), $pos->getFloorY(), $pos->getFloorZ());
	}
}
