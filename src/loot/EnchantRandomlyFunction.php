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

use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\enchantment\VanillaEnchantments;
use pocketmine\item\Item;
use function array_rand;
use function mt_rand;

final class EnchantRandomlyFunction implements LootFunction{
	public function apply(Item $item, LootContext $context) : Item{
		$all = [
			VanillaEnchantments::UNBREAKING(),
			VanillaEnchantments::EFFICIENCY(),
			VanillaEnchantments::SHARPNESS(),
			VanillaEnchantments::PROTECTION(),
			VanillaEnchantments::FEATHER_FALLING(),
			VanillaEnchantments::FIRE_ASPECT(),
			VanillaEnchantments::KNOCKBACK(),
			VanillaEnchantments::FORTUNE(),
			VanillaEnchantments::POWER(),
			VanillaEnchantments::PUNCH(),
			VanillaEnchantments::FLAME(),
			VanillaEnchantments::INFINITY(),
			VanillaEnchantments::MENDING(),
			VanillaEnchantments::SILK_TOUCH()
		];

		$enchant = $all[array_rand($all)];
		$level = mt_rand(1, $enchant->getMaxLevel());
		$item->addEnchantment(new EnchantmentInstance($enchant, $level));
		return $item;
	}
}
