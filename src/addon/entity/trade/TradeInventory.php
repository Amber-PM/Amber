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

namespace pocketmine\addon\entity\trade;

use pocketmine\addon\entity\AddonEntity;
use pocketmine\inventory\SimpleInventory;
use pocketmine\inventory\TemporaryInventory;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\network\mcpe\protocol\types\CacheableNbt;
use pocketmine\network\mcpe\protocol\types\inventory\WindowTypes;
use pocketmine\network\mcpe\protocol\UpdateTradePacket;
use pocketmine\player\Player;

/**
 * The trade screen between a player and an add-on trader: the two ingredient slots (returned to the player
 * when the screen closes) and the trader's offers.
 */
final class TradeInventory extends SimpleInventory implements TemporaryInventory{
	public const SLOT_A = 0;
	public const SLOT_B = 1;

	public function __construct(private AddonEntity $trader, private Player $player){
		parent::__construct(2);
	}

	public function getTrader() : AddonEntity{ return $this->trader; }

	public function getPlayer() : Player{ return $this->player; }

	public function getOffer(int $netId) : ?TradeOffer{
		foreach($this->trader->getTradeOffers() as $offer){
			if($offer->netId === $netId){
				return $offer;
			}
		}
		return null;
	}

	/** The packet that opens (or refreshes) the trade screen, for the player's protocol. */
	public function createPacket(int $windowId) : UpdateTradePacket{
		$converter = $this->player->getNetworkSession()->getTypeConverter();
		$tier = $this->trader->getTradeTier();
		$recipes = [];
		foreach($this->trader->getTradeOffers() as $offer){
			if($offer->tier <= $tier){
				$recipes[] = $offer->toNbt($converter);
			}
		}
		$tiers = [];
		foreach($this->trader->getTradeTierExperience() as $index => $exp){
			$tiers[] = CompoundTag::create()->setInt((string) $index, $exp);
		}
		$nbt = CompoundTag::create()
			->setTag("Recipes", new ListTag($recipes))
			->setTag("TierExpRequirements", new ListTag($tiers));
		return UpdateTradePacket::create(
			$windowId,
			WindowTypes::TRADING,
			0,
			$tier,
			$this->trader->getId(),
			$this->player->getId(),
			$this->trader->getTradeDisplayName(),
			$this->trader->usesNewTradeScreen(),
			false,
			new CacheableNbt($nbt)
		);
	}
}
