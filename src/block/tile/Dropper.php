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

namespace pocketmine\block\tile;

use pocketmine\block\inventory\DropperInventory;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\world\World;

class Dropper extends Spawnable implements Container, Nameable{

	use ContainerTrait;
	use NameableTrait;

	private DropperInventory $inventory;

	public function __construct(World $world, Vector3 $pos){
		parent::__construct($world, $pos);
		$this->inventory = new DropperInventory($this->position);
	}

	public function readSaveData(CompoundTag $nbt) : void{
		$this->loadItems($nbt);
		$this->loadName($nbt);
	}

	protected function writeSaveData(CompoundTag $nbt) : void{
		$this->saveItems($nbt);
		$this->saveName($nbt);
	}

	public function close() : void{
		if(!$this->closed){
			$this->inventory->removeAllViewers();

			parent::close();
		}
	}

	public function getDefaultName() : string{
		return "Dropper";
	}

	public function getInventory() : DropperInventory{
		return $this->inventory;
	}

	public function getRealInventory() : DropperInventory{
		return $this->inventory;
	}
}
