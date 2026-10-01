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

use pocketmine\block\utils\AnalogRedstoneSignalEmitter;
use pocketmine\block\utils\AnalogRedstoneSignalEmitterTrait;
use pocketmine\block\utils\RedstoneReceiver;
use pocketmine\block\utils\RedstoneSource;
use pocketmine\block\utils\StaticSupportTrait;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\math\Facing;
use pocketmine\world\redstone\RedstoneEngine;

class RedstoneWire extends Flowable implements AnalogRedstoneSignalEmitter, RedstoneSource, RedstoneReceiver{
	use AnalogRedstoneSignalEmitterTrait;
	use StaticSupportTrait;

	public function readStateFromWorld() : Block{
		parent::readStateFromWorld();
		//TODO: check connections to nearby redstone components

		return $this;
	}

	private function canBeSupportedAt(Block $block) : bool{
		return $block->getAdjacentSupportType(Facing::DOWN)->hasCenterSupport();
	}

	public function asItem() : Item{
		return VanillaItems::REDSTONE_DUST();
	}

	public function getRedstoneOutput(int $face, bool $strongOnly, RedstoneEngine $engine) : int{
		if($strongOnly || $face === Facing::UP || $this->signalStrength === 0){
			return 0;
		}
		return $face === Facing::DOWN || isset($engine->getWires()->getDirections($this)[$face]) ? $this->signalStrength : 0;
	}

	public function onRedstoneUpdate(RedstoneEngine $engine) : void{
		$engine->getWires()->update($this);
	}
}
