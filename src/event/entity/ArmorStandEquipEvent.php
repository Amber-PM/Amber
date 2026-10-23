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

namespace pocketmine\event\entity;

use pocketmine\entity\object\ArmorStand;
use pocketmine\event\Cancellable;
use pocketmine\event\CancellableTrait;
use pocketmine\item\Item;
use pocketmine\player\Player;

/**
 * Called when an armor stand is equipped or unequipped with armor/items by a player.
 *
 * @phpstan-extends EntityEvent<ArmorStand>
 */
class ArmorStandEquipEvent extends EntityEvent implements Cancellable{
	use CancellableTrait;

	public const SLOT_HEAD = 0;
	public const SLOT_CHEST = 1;
	public const SLOT_LEGS = 2;
	public const SLOT_FEET = 3;
	public const SLOT_MAIN_HAND = 4;

	public function __construct(
		ArmorStand $armorStand,
		private Player $player,
		private int $slot,
		private Item $oldItem,
		private Item $newItem
	){
		$this->entity = $armorStand;
	}

	public function getArmorStand() : ArmorStand{
		/** @var ArmorStand $armorStand */
		$armorStand = $this->entity;
		return $armorStand;
	}

	/**
	 * @return ArmorStand
	 */
	public function getEntity(){
		return $this->entity;
	}

	public function getPlayer() : Player{
		return $this->player;
	}

	public function getSlot() : int{
		return $this->slot;
	}

	public function getOldItem() : Item{
		return $this->oldItem;
	}

	public function getNewItem() : Item{
		return $this->newItem;
	}

	public function setNewItem(Item $newItem) : void{
		$this->newItem = $newItem;
	}
}
