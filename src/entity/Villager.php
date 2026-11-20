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
use pocketmine\entity\villager\VillagerProfession;
use pocketmine\entity\villager\VillagerTradeTier;
use pocketmine\inventory\villager\TradeInventory;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\network\mcpe\protocol\types\CacheableNbt;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\network\mcpe\protocol\types\inventory\WindowTypes;
use pocketmine\network\mcpe\protocol\UpdateTradePacket;
use pocketmine\player\Player;
use pocketmine\world\particle\AngryVillagerParticle;
use pocketmine\world\particle\HappyVillagerParticle;
use pocketmine\world\sound\XpLevelUpSound;
use function count;
use function floor;
use function mt_rand;

class Villager extends Living implements Ageable, Trader{
	// Backward-compatible aliases for legacy PM plugins
	public const PROFESSION_FARMER = VillagerProfession::FARMER;
	public const PROFESSION_LIBRARIAN = VillagerProfession::LIBRARIAN;
	public const PROFESSION_PRIEST = VillagerProfession::CLERIC;
	public const PROFESSION_BLACKSMITH = VillagerProfession::ARMORER;
	public const PROFESSION_BUTCHER = VillagerProfession::BUTCHER;

	private const TAG_PROFESSION = "Profession"; // TAG_Int
	private const TAG_TRADE_TIER = "TradeTier"; // TAG_Int
	private const TAG_TRADE_EXPERIENCE = "TradeExperience"; // TAG_Int
	private const TAG_OFFERS = "Offers"; // TAG_Compound with Recipes
	private const TAG_WORKSTATION_X = "WorkstationX";
	private const TAG_WORKSTATION_Y = "WorkstationY";
	private const TAG_WORKSTATION_Z = "WorkstationZ";
	private const TAG_RESTOCKS_TODAY = "RestocksToday";
	private const TAG_LAST_RESTOCK_DAY = "LastRestockDay";

	public static function getNetworkTypeId() : string{ return EntityIds::VILLAGER_V2; }

	private bool $baby = false;
	private int $profession = VillagerProfession::NONE;
	private int $tradeTier = VillagerTradeTier::NOVICE;
	private int $experience = 0;
	/** @var TradeOffer[] */
	private array $tradeOffers = [];
	private ?Vector3 $workstationPos = null;
	private int $restocksToday = 0;
	private int $lastRestockDay = -1;
	private int $checkWorkstationTicks = 0;

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(1.9, 0.6);
	}

	public function getName() : string{
		return VillagerProfession::getDisplayName($this->profession);
	}

	protected function initEntity(CompoundTag $nbt) : void{
		parent::initEntity($nbt);

		$this->profession = $nbt->getInt(self::TAG_PROFESSION, VillagerProfession::NONE);
		if($this->profession < 0 || $this->profession > 14){
			$this->profession = VillagerProfession::NONE;
		}

		$this->tradeTier = $nbt->getInt(self::TAG_TRADE_TIER, VillagerTradeTier::NOVICE);
		if($this->tradeTier < 1 || $this->tradeTier > 5){
			$this->tradeTier = VillagerTradeTier::NOVICE;
		}

		$this->experience = $nbt->getInt(self::TAG_TRADE_EXPERIENCE, 0);
		$this->restocksToday = $nbt->getInt(self::TAG_RESTOCKS_TODAY, 0);
		$this->lastRestockDay = $nbt->getInt(self::TAG_LAST_RESTOCK_DAY, -1);

		if($nbt->getTag(self::TAG_WORKSTATION_X) !== null){
			$this->workstationPos = new Vector3(
				$nbt->getInt(self::TAG_WORKSTATION_X),
				$nbt->getInt(self::TAG_WORKSTATION_Y),
				$nbt->getInt(self::TAG_WORKSTATION_Z)
			);
			TradeManager::getInstance()->claimWorkstation($this->getWorld(), $this->workstationPos, $this->getId());
		}

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

		if(count($this->tradeOffers) === 0 && $this->profession !== VillagerProfession::NONE && $this->profession !== VillagerProfession::NITWIT){
			$this->tradeOffers = TradeManager::getInstance()->generateTradesForTier($this->profession, $this->tradeTier);
		}
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT();
		$nbt->setInt(self::TAG_PROFESSION, $this->profession);
		$nbt->setInt(self::TAG_TRADE_TIER, $this->tradeTier);
		$nbt->setInt(self::TAG_TRADE_EXPERIENCE, $this->experience);
		$nbt->setInt(self::TAG_RESTOCKS_TODAY, $this->restocksToday);
		$nbt->setInt(self::TAG_LAST_RESTOCK_DAY, $this->lastRestockDay);

		if($this->workstationPos !== null){
			$nbt->setInt(self::TAG_WORKSTATION_X, (int) $this->workstationPos->x);
			$nbt->setInt(self::TAG_WORKSTATION_Y, (int) $this->workstationPos->y);
			$nbt->setInt(self::TAG_WORKSTATION_Z, (int) $this->workstationPos->z);
		}

		$recipesList = [];
		foreach($this->tradeOffers as $offer){
			$recipesList[] = $offer->toNbt();
		}
		$offersTag = CompoundTag::create()->setTag("Recipes", new ListTag($recipesList));
		$nbt->setTag(self::TAG_OFFERS, $offersTag);

		return $nbt;
	}

	public function setProfession(int $profession) : void{
		$this->profession = $profession;
		$this->networkPropertiesDirty = true;
	}

	public function getProfession() : int{
		return $this->profession;
	}

	public function getTradeTier() : int{
		return $this->tradeTier;
	}

	public function setTradeTier(int $tradeTier) : void{
		$this->tradeTier = $tradeTier;
		$this->networkPropertiesDirty = true;
	}

	public function getTradeExperience() : int{
		return $this->experience;
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

	public function getWorkstationPosition() : ?Vector3{
		return $this->workstationPos !== null ? clone $this->workstationPos : null;
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
		if($amount <= 0){
			return;
		}

		$this->experience += $amount;
		$newTier = VillagerTradeTier::getTierFromExp($this->experience);

		if($newTier > $this->tradeTier){
			$this->tradeTier = $newTier;
			$this->networkPropertiesDirty = true;

			$newOffers = TradeManager::getInstance()->generateTradesForTier($this->profession, $this->tradeTier);
			foreach($newOffers as $newOffer){
				$this->tradeOffers[] = $newOffer;
			}

			$world = $this->getWorld();
			$pos = $this->location->add(0, $this->size->getHeight() * 0.75, 0);
			$world->addParticle($pos, new HappyVillagerParticle());
			$world->addSound($pos, new XpLevelUpSound(mt_rand(1, 10)));
		}
	}

	public function resupplyTrades() : void{
		foreach($this->tradeOffers as $offer){
			$offer->resetUses();
		}

		$world = $this->getWorld();
		$pos = $this->location->add(0, $this->size->getHeight() * 0.75, 0);
		$world->addParticle($pos, new HappyVillagerParticle());
	}

	public function claimWorkstation(Vector3 $pos, int $profession) : bool{
		if(!TradeManager::getInstance()->claimWorkstation($this->getWorld(), $pos, $this->getId())){
			return false;
		}

		$this->workstationPos = $pos->asVector3();
		$this->setProfession($profession);
		$this->tradeTier = VillagerTradeTier::NOVICE;
		$this->experience = 0;
		$this->tradeOffers = TradeManager::getInstance()->generateTradesForTier($profession, VillagerTradeTier::NOVICE);

		$this->getWorld()->addParticle($this->location->add(0, $this->size->getHeight() * 0.75, 0), new HappyVillagerParticle());
		return true;
	}

	public function releaseWorkstation() : void{
		if($this->workstationPos !== null){
			TradeManager::getInstance()->releaseWorkstation($this->getWorld(), $this->workstationPos);
			$this->workstationPos = null;
		}
	}

	public function checkWorkstation() : void{
		if($this->baby || $this->profession === VillagerProfession::NITWIT){
			return;
		}

		$world = $this->getWorld();

		if($this->workstationPos !== null){
			$block = $world->getBlock($this->workstationPos);
			$expectedBlockId = VillagerProfession::getWorkstationBlockId($this->profession);
			if($expectedBlockId !== null && $block->getTypeId() !== $expectedBlockId){
				$this->releaseWorkstation();
				if($this->experience === 0){
					$this->setProfession(VillagerProfession::NONE);
					$this->tradeOffers = [];
				}
			}
		}

		if($this->profession === VillagerProfession::NONE){
			$tradeMgr = TradeManager::getInstance();
			$floorX = (int) floor($this->location->x);
			$floorY = (int) floor($this->location->y);
			$floorZ = (int) floor($this->location->z);

			$radius = 8;
			$yRadius = 3;

			for($x = -$radius; $x <= $radius; $x++){
				for($z = -$radius; $z <= $radius; $z++){
					for($y = -$yRadius; $y <= $yRadius; $y++){
						$checkPos = new Vector3($floorX + $x, $floorY + $y, $floorZ + $z);
						$block = $world->getBlock($checkPos);
						$prof = VillagerProfession::fromWorkstationBlockId($block->getTypeId());
						if($prof !== null && !$tradeMgr->isWorkstationClaimed($world, $checkPos)){
							if($this->claimWorkstation($checkPos, $prof)){
								return;
							}
						}
					}
				}
			}
		}

		$timeOfDay = $world->getTimeOfDay();
		if($timeOfDay >= 2000 && $timeOfDay <= 9000 && $this->workstationPos !== null){
			$currentDay = (int) floor($world->getTime() / 24000);
			if($currentDay !== $this->lastRestockDay){
				$this->lastRestockDay = $currentDay;
				$this->restocksToday = 0;
			}

			if($this->restocksToday < 2 && $this->location->distanceSquared($this->workstationPos) <= 16.0){
				$this->resupplyTrades();
				$this->restocksToday++;
			}
		}
	}

	public function onInteract(Player $player, Vector3 $clickPos) : bool{
		if($this->baby || $this->profession === VillagerProfession::NITWIT || $this->profession === VillagerProfession::NONE){
			$this->getWorld()->addParticle($this->location->add(0, $this->size->getHeight() * 0.75, 0), new AngryVillagerParticle());
			return false;
		}

		if(count($this->tradeOffers) === 0){
			$this->tradeOffers = TradeManager::getInstance()->generateTradesForTier($this->profession, $this->tradeTier);
		}

		$player->setCurrentWindow(new TradeInventory($this));
		return true;
	}

	public function createUpdateTradePacket(int $windowId, Player $player) : UpdateTradePacket{
		$recipesList = [];
		foreach($this->tradeOffers as $offer){
			$recipesList[] = $offer->toNbt();
		}

		$tierExpList = [
			CompoundTag::create()->setInt("0", VillagerTradeTier::EXP_NOVICE),
			CompoundTag::create()->setInt("1", VillagerTradeTier::EXP_APPRENTICE),
			CompoundTag::create()->setInt("2", VillagerTradeTier::EXP_JOURNEYMAN),
			CompoundTag::create()->setInt("3", VillagerTradeTier::EXP_EXPERT),
			CompoundTag::create()->setInt("4", VillagerTradeTier::EXP_MASTER),
		];

		$offersTag = CompoundTag::create()
			->setTag("Recipes", new ListTag($recipesList))
			->setTag("TierExpRequirements", new ListTag($tierExpList));

		return UpdateTradePacket::create(
			$windowId,
			WindowTypes::TRADING,
			0,
			$this->tradeTier,
			$this->getId(),
			$player->getId(),
			VillagerProfession::getDisplayName($this->profession),
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

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		$hasUpdate = parent::entityBaseTick($tickDiff);

		$this->checkWorkstationTicks += $tickDiff;
		if($this->checkWorkstationTicks >= 100){
			$this->checkWorkstationTicks = 0;
			$this->checkWorkstation();
		}

		return $hasUpdate;
	}

	protected function onDispose() : void{
		parent::onDispose();
		$this->releaseWorkstation();
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);
		$properties->setGenericFlag(EntityMetadataFlags::BABY, $this->baby);
		$properties->setInt(EntityMetadataProperties::VARIANT, $this->profession);
		$properties->setInt(EntityMetadataProperties::TRADE_TIER, $this->tradeTier);
		$properties->setInt(EntityMetadataProperties::MAX_TRADE_TIER, 5);
	}
}
