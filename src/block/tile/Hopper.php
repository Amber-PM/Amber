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

use pocketmine\block\inventory\HopperInventory;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\world\World;
use function max;

class Hopper extends Spawnable implements Container, Nameable{

	use ContainerTrait;
	use NameableTrait;

	private const TAG_TRANSFER_COOLDOWN = "TransferCooldown";

	private HopperInventory $inventory;
	private int $transferCooldown = 0;
	private ?int $lastCooldownTick = null;

	public function __construct(World $world, Vector3 $pos){
		parent::__construct($world, $pos);
		$this->inventory = new HopperInventory($this->position);
	}

	public function readSaveData(CompoundTag $nbt) : void{
		$this->loadItems($nbt);
		$this->loadName($nbt);

		$this->transferCooldown = max(0, $nbt->getInt(self::TAG_TRANSFER_COOLDOWN, 0));
	}

	protected function writeSaveData(CompoundTag $nbt) : void{
		$this->saveItems($nbt);
		$this->saveName($nbt);

		$nbt->setInt(self::TAG_TRANSFER_COOLDOWN, $this->transferCooldown);
	}

	public function getTransferCooldown() : int{ return $this->transferCooldown; }

	public function startTransferCooldown(int $currentTick, int $delay) : void{
		$this->transferCooldown = max(0, $delay);
		$this->lastCooldownTick = $currentTick;
	}

	public function resetCooldownClock() : void{ $this->lastCooldownTick = null; }

	public function advanceTransferCooldown(int $currentTick) : void{
		$elapsed = $this->lastCooldownTick === null ? 1 : max(0, $currentTick - $this->lastCooldownTick);
		$this->lastCooldownTick = $currentTick;
		$this->transferCooldown = max(0, $this->transferCooldown - $elapsed);
	}

	public function close() : void{
		if(!$this->closed){
			$this->inventory->removeAllViewers();

			parent::close();
		}
	}

	public function getDefaultName() : string{
		return "Hopper";
	}

	public function getInventory() : HopperInventory{
		return $this->inventory;
	}

	public function getRealInventory() : HopperInventory{
		return $this->inventory;
	}
}
