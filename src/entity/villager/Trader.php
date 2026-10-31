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

namespace pocketmine\entity\villager;

use pocketmine\network\mcpe\protocol\UpdateTradePacket;
use pocketmine\player\Player;
use pocketmine\world\Position;
use pocketmine\world\World;

interface Trader{

	public function getId() : int;

	public function getName() : string;

	public function getPosition() : Position;

	public function getWorld() : World;

	/**
	 * @return TradeOffer[]
	 */
	public function getTradeOffers() : array;

	public function getTradeOffer(int $index) : ?TradeOffer;

	public function addExperience(int $amount) : void;

	public function resupplyTrades() : void;

	public function createUpdateTradePacket(int $windowId, Player $player) : UpdateTradePacket;

	public function sendTradeData(Player $player, ?int $windowId = null) : void;
}
