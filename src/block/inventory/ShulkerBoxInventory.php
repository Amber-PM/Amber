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

namespace pocketmine\block\inventory;

use pocketmine\block\BlockTypeIds;
use pocketmine\inventory\Inventory;
use pocketmine\inventory\SimpleInventory;
use pocketmine\inventory\transaction\action\validator\CallbackSlotValidator;
use pocketmine\inventory\transaction\TransactionValidationException;
use pocketmine\item\Item;
use pocketmine\item\ItemTypeIds;
use pocketmine\network\mcpe\protocol\BlockEventPacket;
use pocketmine\network\mcpe\protocol\types\BlockPosition;
use pocketmine\world\Position;
use pocketmine\world\sound\ShulkerBoxCloseSound;
use pocketmine\world\sound\ShulkerBoxOpenSound;
use pocketmine\world\sound\Sound;

class ShulkerBoxInventory extends SimpleInventory implements BlockInventory{
	use AnimatedBlockInventoryTrait;

	public function __construct(Position $holder){
		$this->holder = $holder;
		parent::__construct(27);
		$this->validators->add(new CallbackSlotValidator(static function(Inventory $inventory, Item $item, int $slot) : ?TransactionValidationException{
			return self::isShulkerBox($item) ? new TransactionValidationException("Shulker boxes cannot be placed inside shulker boxes") : null;
		}));
	}

	protected function getOpenSound() : Sound{
		return new ShulkerBoxOpenSound();
	}

	protected function getCloseSound() : Sound{
		return new ShulkerBoxCloseSound();
	}

	private static function isShulkerBox(Item $item) : bool{
		$blockTypeId = ItemTypeIds::toBlockTypeId($item->getTypeId());
		return $blockTypeId === BlockTypeIds::SHULKER_BOX || $blockTypeId === BlockTypeIds::DYED_SHULKER_BOX;
	}

	public function canAddItem(Item $item) : bool{
		if(self::isShulkerBox($item)){
			return false;
		}
		return parent::canAddItem($item);
	}

	protected function animateBlock(bool $isOpen) : void{
		$holder = $this->getHolder();

		//event ID is always 1 for a chest
		$holder->getWorld()->broadcastPacketToViewers($holder, BlockEventPacket::create(BlockPosition::fromVector3($holder), 1, $isOpen ? 1 : 0));
	}
}
