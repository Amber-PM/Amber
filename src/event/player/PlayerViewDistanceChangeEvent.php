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

namespace pocketmine\event\player;

use pocketmine\player\Player;

/**
 * Called when a player requests a different viewing distance than the current one.
 */
class PlayerViewDistanceChangeEvent extends PlayerEvent{
	public function __construct(
		Player $player,
		protected int $oldDistance,
		protected int $newDistance
	){
		$this->player = $player;
	}

	/**
	 * Returns the new view radius, measured in chunks.
	 */
	public function getNewDistance() : int{
		return $this->newDistance;
	}

	/**
	 * Returns the old view radius, measured in chunks.
	 * A value of -1 means that the player has just connected and did not have a view distance before this event.
	 */
	public function getOldDistance() : int{
		return $this->oldDistance;
	}
}
