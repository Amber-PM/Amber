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

namespace pocketmine\entity;

use pocketmine\entity\villager\TradeManager;
use pocketmine\entity\villager\TradeOffer;
use pocketmine\entity\villager\Trader;
use pocketmine\inventory\villager\TradeInventory;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\network\mcpe\protocol\types\CacheableNbt;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\inventory\WindowTypes;
use pocketmine\network\mcpe\protocol\UpdateTradePacket;
use pocketmine\player\Player;
use pocketmine\world\particle\HappyVillagerParticle;
use function count;

class WanderingTrader extends Living implements Ageable, Trader{

	private const TAG_OFFERS = "Offers";

	public static function getNetworkTypeId() : string{ return EntityIds::WANDERING_TRADER; }

	private bool $baby = false;
	/** @var TradeOffer[] */
	private array $tradeOffers = [];

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(1.9, 0.6);
	}

	public function getName() : string{
		return "Wandering Trader";
	}

	protected function initEntity(CompoundTag $nbt) : void{
		parent::initEntity($nbt);

		$offersTag = $nbt->getCompoundTag(self::TAG_OFFERS);
		if($offersTag !== null){
			$recipesTag = $offersTag->getListTag("Recipes");
			if($recipesTag !== null){
				/** @var CompoundTag $recipeTag */
				foreach($recipesTag as $recipeTag){
					try{
						$this->tradeOffers[] = TradeOffer::fromNbt($recipeTag);
					}catch(\Throwable){
						// Skip malformed offers
					}
				}
			}
		}

		if(count($this->tradeOffers) === 0){
			$this->tradeOffers = TradeManager::getInstance()->generateWanderingTraderTrades();
		}
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT();

		$recipesList = [];
		foreach($this->tradeOffers as $offer){
			$recipesList[] = $offer->toNbt();
		}
		$offersTag = CompoundTag::create()->setTag("Recipes", new ListTag($recipesList));
		$nbt->setTag(self::TAG_OFFERS, $offersTag);

		return $nbt;
	}

	/**
	 * @return TradeOffer[]
	 */
	public function getTradeOffers() : array{
		return $this->tradeOffers;
	}

	public function getTradeOffer(int $index) : ?TradeOffer{
		return $this->tradeOffers[$index] ?? null;
	}

	/**
	 * @param TradeOffer[] $offers
	 */
	public function setTradeOffers(array $offers) : void{
		$this->tradeOffers = $offers;
	}

	public function isBaby() : bool{
		return $this->baby;
	}

	public function setBaby(bool $baby = true) : void{
		$this->baby = $baby;
		$this->networkPropertiesDirty = true;
	}

	public function getPickedItem() : ?Item{
		return VanillaItems::VILLAGER_SPAWN_EGG();
	}

	public function addExperience(int $amount) : void{
		// Wandering traders do not level up
	}

	public function resupplyTrades() : void{
		foreach($this->tradeOffers as $offer){
			$offer->resetUses();
		}

		$world = $this->getWorld();
		$pos = $this->location->add(0, $this->size->getHeight() * 0.75, 0);
		$world->addParticle($pos, new HappyVillagerParticle());
	}

	public function onInteract(Player $player, \pocketmine\math\Vector3 $clickPos) : bool{
		if($this->baby || count($this->tradeOffers) === 0){
			return false;
		}

		$player->setCurrentWindow(new TradeInventory($this));
		return true;
	}

	public function createUpdateTradePacket(int $windowId, Player $player) : UpdateTradePacket{
		$recipesList = [];
		foreach($this->tradeOffers as $offer){
			$recipesList[] = $offer->toNbt();
		}

		$offersTag = CompoundTag::create()
			->setTag("Recipes", new ListTag($recipesList))
			->setTag("TierExpRequirements", new ListTag([]));

		return UpdateTradePacket::create(
			$windowId,
			WindowTypes::TRADING,
			0,
			1,
			$this->getId(),
			$player->getId(),
			"Wandering Trader",
			true,
			true,
			new CacheableNbt($offersTag)
		);
	}

	public function sendTradeData(Player $player, ?int $windowId = null) : void{
		if($windowId === null){
			$windowId = $player->getNetworkSession()->getInvManager()?->getCurrentWindowId();
			if($windowId === null){
				return;
			}
		}

		$player->getNetworkSession()->sendDataPacket($this->createUpdateTradePacket($windowId, $player));
	}
}
