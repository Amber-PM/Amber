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

namespace pocketmine\loot;

use pocketmine\entity\Entity;
use pocketmine\entity\Human;
use pocketmine\item\Item;
use pocketmine\lang\Translatable;
use pocketmine\player\Player;
use pocketmine\world\Position;
use function stripos;

final class LootContext{

	public function __construct(
		public readonly Position $position,
		public readonly ?Entity $killer = null,
		public readonly ?Entity $victim = null,
		public readonly ?Item $tool = null,
		public readonly float $luck = 0.0,
		private ?int $lootingLevel = null
	){}

	public function hasPlayerKiller() : bool{
		return $this->killer instanceof Player;
	}

	public function getLootingLevel() : int{
		if($this->lootingLevel !== null){
			return $this->lootingLevel;
		}

		$tool = $this->tool;
		if($tool === null && $this->killer instanceof Human){
			$tool = $this->killer->getInventory()->getItemInHand();
		}

		if($tool instanceof Item){
			foreach($tool->getEnchantments() as $enchInstance){
				$name = $enchInstance->getType()->getName();
				$nameStr = $name instanceof Translatable ? $name->getText() : $name;
				if(stripos($nameStr, "looting") !== false){
					return $enchInstance->getLevel();
				}
			}
		}

		return 0;
	}

	public function withLootingLevel(int $level) : self{
		return new self(
			$this->position,
			$this->killer,
			$this->victim,
			$this->tool,
			$this->luck,
			$level
		);
	}
}
