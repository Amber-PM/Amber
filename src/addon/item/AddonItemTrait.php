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

namespace pocketmine\addon\item;

use pocketmine\addon\AddonManager;
use pocketmine\block\Block;
use pocketmine\item\ItemUseResult;
use pocketmine\math\Vector3;
use pocketmine\player\Player;

/**
 * Shared behaviour of every add-on item class: stack size, damage and cooldown come from the definition, and
 * right-clicks are offered to handlers plugins registered with AddonManager::onItemUse().
 */
trait AddonItemTrait{
	protected AddonItemDefinition $addonDefinition;

	public function getAddonDefinition() : AddonItemDefinition{ return $this->addonDefinition; }

	/** The add-on identifier, e.g. "example:ruby". */
	public function getAddonIdentifier() : string{ return $this->addonDefinition->getIdentifier(); }

	public function getMaxStackSize() : int{
		return $this->addonDefinition->getMaxStackSize();
	}

	public function getAttackPoints() : int{
		$damage = $this->addonDefinition->getAttackDamage();
		return $damage > 0 ? $damage : parent::getAttackPoints();
	}

	public function getCooldownTicks() : int{
		return $this->addonDefinition->getCooldown()["ticks"] ?? 0;
	}

	public function getCooldownTag() : ?string{
		return $this->addonDefinition->getCooldown()["category"] ?? null;
	}

	public function onClickAir(Player $player, Vector3 $directionVector, array &$returnedItems) : ItemUseResult{
		$handled = AddonManager::getInstance()?->dispatchItemUse($player, $this, null) ?? false;
		return $handled ? ItemUseResult::SUCCESS : parent::onClickAir($player, $directionVector, $returnedItems);
	}

	public function onInteractBlock(Player $player, Block $blockReplace, Block $blockClicked, int $face, Vector3 $clickVector, array &$returnedItems) : ItemUseResult{
		$handled = AddonManager::getInstance()?->dispatchItemUse($player, $this, $blockClicked) ?? false;
		return $handled ? ItemUseResult::SUCCESS : parent::onInteractBlock($player, $blockReplace, $blockClicked, $face, $clickVector, $returnedItems);
	}
}
