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

use pocketmine\event\Cancellable;
use pocketmine\event\CancellableTrait;
use pocketmine\item\Item;
use pocketmine\player\Player;
use pocketmine\utils\Utils;

/**
 * Called when a player eats something
 */
class PlayerItemConsumeEvent extends PlayerEvent implements Cancellable{
	use CancellableTrait;

	/**
	 * @param Item[] $residue
	 */
	public function __construct(
		Player $player,
		private Item $item,
		private array $residue = []
	){
		$this->player = $player;
	}

	public function getItem() : Item{
		return clone $this->item;
	}

	/**
	 * Returns the leftover items returned to the player after consuming the item.
	 * For example, glass bottles for potions, bowls for beetroot soup, etc.
	 *
	 * @return Item[]
	 */
	public function getResidue() : array{
		return Utils::cloneObjectArray($this->residue);
	}

	/**
	 * Sets the items returned to the player after consuming the item.
	 *
	 * @param Item[] $items
	 */
	public function setResidue(array $items) : void{
		Utils::validateArrayValueType($items, function(Item $_) : void{});

		$this->residue = Utils::cloneObjectArray($items);
	}
}
