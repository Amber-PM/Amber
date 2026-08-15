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

namespace pocketmine\world\particle;

use pocketmine\data\bedrock\item\ItemTypeSerializeException;
use pocketmine\item\Item;
use pocketmine\network\mcpe\convert\ItemTranslator;

abstract class ItemParticle implements Particle{

	private ItemTranslator $itemTranslator;

	public function __construct(private Item $item){}

	public function setItemTranslator(ItemTranslator $itemTranslator) : void{
		$this->itemTranslator = $itemTranslator;
	}

	/**
	 * @return int[]
	 * @phpstan-return array{int, int, int|null}
	 *
	 * @throws ItemTypeSerializeException
	 */
	public function toNetworkId() : array{
		return $this->itemTranslator->toNetworkId($this->item);
	}
}
