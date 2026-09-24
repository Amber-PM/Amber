<?php

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketMine Team
 * @link http://www.pocketmine.net/
 *
 *
 */

declare(strict_types=1);

namespace pocketmine\addon\loot;

use pocketmine\entity\Entity;
use pocketmine\item\enchantment\StringToEnchantmentParser;
use pocketmine\item\enchantment\VanillaEnchantments;
use pocketmine\item\Item;
use pocketmine\player\Player;
use function max;

/**
 * What a loot table is rolled for: who died or broke the block, who did it, and with what.
 */
final class LootContext{
	public function __construct(
		public readonly ?Entity $thisEntity = null,
		public readonly ?Entity $killer = null,
		public readonly ?Item $tool = null,
		public readonly bool $killedByPlayer = false
	){}

	/**
	 * Looting level of the killer's weapon, or Fortune on the tool for blocks. PocketMine has no Looting
	 * enchantment of its own; it is used when a plugin registers one under the name "looting".
	 */
	public function lootingLevel() : int{
		if($this->tool === null){
			return 0;
		}
		$level = $this->tool->getEnchantmentLevel(VanillaEnchantments::FORTUNE());
		$looting = StringToEnchantmentParser::getInstance()->parse("looting");
		return $looting !== null ? max($level, $this->tool->getEnchantmentLevel($looting)) : $level;
	}

	/** A context for an entity killed with the given (possibly null) damager. */
	public static function forEntityDeath(Entity $entity, ?Entity $killer) : self{
		$tool = $killer instanceof Player ? $killer->getInventory()->getItemInHand() : null;
		return new self($entity, $killer, $tool, $killer instanceof Player);
	}
}
