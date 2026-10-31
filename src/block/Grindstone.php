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

namespace pocketmine\block;

use pocketmine\block\inventory\GrindstoneInventory;
use pocketmine\block\utils\BellAttachmentType;
use pocketmine\block\utils\HorizontalFacing;
use pocketmine\block\utils\HorizontalFacingTrait;
use pocketmine\data\runtime\RuntimeDataDescriber;
use pocketmine\item\Item;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\BlockTransaction;

final class Grindstone extends Transparent implements HorizontalFacing{
	use HorizontalFacingTrait;

	private BellAttachmentType $attachmentType = BellAttachmentType::FLOOR;

	protected function describeBlockOnlyState(RuntimeDataDescriber $w) : void{
		$w->enum($this->attachmentType);
		$w->horizontalFacing($this->facing);
	}

	public function getAttachmentType() : BellAttachmentType{ return $this->attachmentType; }

	public function setAttachmentType(BellAttachmentType $attachmentType) : self{
		$this->attachmentType = $attachmentType;
		return $this;
	}

	public function place(BlockTransaction $tx, Item $item, Block $blockReplace, Block $blockClicked, int $face, Vector3 $clickVector, ?Player $player = null) : bool{
		if($face === Facing::UP){
			$this->setAttachmentType(BellAttachmentType::FLOOR);
			$this->setFacing($player !== null ? Facing::opposite($player->getHorizontalFacing()) : Facing::NORTH);
		}elseif($face === Facing::DOWN){
			$this->setAttachmentType(BellAttachmentType::CEILING);
			$this->setFacing($player !== null ? Facing::opposite($player->getHorizontalFacing()) : Facing::NORTH);
		}else{
			$this->setAttachmentType(BellAttachmentType::ONE_WALL);
			$this->setFacing($face);
		}
		return parent::place($tx, $item, $blockReplace, $blockClicked, $face, $clickVector, $player);
	}

	public function onInteract(Item $item, int $face, Vector3 $clickVector, ?Player $player = null, array &$returnedItems = []) : bool{
		if($player !== null){
			$player->setCurrentWindow(new GrindstoneInventory($this->position));
		}

		return true;
	}
}
