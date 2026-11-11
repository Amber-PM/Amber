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

use pocketmine\entity\villager\data\VanillaTradeData;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\enchantment\VanillaEnchantments;
use pocketmine\item\Item;
use pocketmine\item\StringToItemParser;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\utils\Filesystem;
use pocketmine\utils\SingletonTrait;
use pocketmine\world\World;
use function array_keys;
use function array_rand;
use function count;
use function explode;
use function is_array;
use function is_file;
use function json_decode;
use function max;
use function min;
use function mt_rand;
use function shuffle;
use function str_replace;
use const JSON_THROW_ON_ERROR;

final class TradeManager{
	use SingletonTrait;

	/** @var array<string, array<string, mixed>> */
	private array $tradeTables = [];

	/** @var array<string, int> worldName:x:y:z => villager entity runtime ID */
	private array $claimedWorkstations = [];

	public function __construct(){
		// Lazy loaded per table
	}

	public static function getInstance() : self{
		return self::make();
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function getTradeTable(string $fileName) : ?array{
		if(isset($this->tradeTables[$fileName])){
			return $this->tradeTables[$fileName];
		}

		$data = VanillaTradeData::getTable($fileName);
		if($data !== null){
			$this->tradeTables[$fileName] = $data;
			return $data;
		}

		if(defined('pocketmine\RESOURCE_PATH')){
			$path = \pocketmine\RESOURCE_PATH . 'vanilla/trading/economy_trades/' . $fileName;
			if(is_file($path)){
				try{
					/** @var array<string, mixed> $fileData */
					$fileData = json_decode(Filesystem::fileGetContents($path), true, flags: JSON_THROW_ON_ERROR);
					$this->tradeTables[$fileName] = $fileData;
					return $fileData;
				}catch(\Throwable){
					// Fall through
				}
			}
		}

		return null;
	}

	/**
	 * @param array<string, mixed>|int $quantityData
	 */
	private function resolveQuantity(array|int $quantityData) : int{
		if(is_array($quantityData)){
			$min = (int) ($quantityData["min"] ?? 1);
			$max = (int) ($quantityData["max"] ?? $min);
			return mt_rand($min, $max);
		}
		return max(1, (int) $quantityData);
	}

	private function resolveItem(string $rawItemString, int $quantity = 1) : ?Item{
		if($rawItemString === ""){
			return null;
		}

		$parts = explode(":", $rawItemString);
		$cleanId = count($parts) === 3 ? $parts[0] . ":" . $parts[1] : $rawItemString;

		$item = StringToItemParser::getInstance()->parse($cleanId);
		if($item === null){
			$shortId = str_replace("minecraft:", "", $cleanId);
			$item = StringToItemParser::getInstance()->parse($shortId);
		}

		if($item !== null){
			$item = clone $item;
			$item->setCount($quantity);
		}

		return $item;
	}

	/**
	 * @param array<string, mixed> $itemEntry
	 * @return array<string, mixed>
	 */
	private function unwrapItemEntry(array $itemEntry) : array{
		if(isset($itemEntry["choice"]) && is_array($itemEntry["choice"]) && count($itemEntry["choice"]) > 0){
			/** @var array<int, array<string, mixed>> $choice */
			$choice = $itemEntry["choice"];
			return $choice[array_rand($choice)];
		}
		return $itemEntry;
	}

	/**
	 * @param array<string, mixed> $tradeData
	 */
	private function buildTradeOffer(array $tradeData, int $tier) : ?TradeOffer{
		$wants = $tradeData["wants"] ?? [];
		$gives = $tradeData["gives"] ?? [];

		if(count($wants) === 0 || count($gives) === 0){
			return null;
		}

		$give = $this->unwrapItemEntry($gives[0]);

		// Handle enchanted book special function
		$functions = $give["functions"] ?? [];
		$isEnchantBook = false;
		foreach($functions as $func){
			if(($func["function"] ?? "") === "enchant_book_for_trading"){
				$isEnchantBook = true;
				break;
			}
		}

		if($isEnchantBook){
			$allEnchants = VanillaEnchantments::getAll();
			$keys = array_keys($allEnchants);
			$enchant = $allEnchants[$keys[array_rand($keys)]];
			$level = mt_rand(1, $enchant->getMaxLevel());

			$book = VanillaItems::ENCHANTED_BOOK();
			$book->addEnchantment(new EnchantmentInstance($enchant, $level));

			$emeraldCost = min(64, max(5, 2 + mt_rand(0, 5) + ($level * (3 + mt_rand(0, 10)))));
			$buyA = VanillaItems::EMERALD()->setCount($emeraldCost);
			$buyB = VanillaItems::BOOK();
			$sell = $book;
		}else{
			$wantA = $this->unwrapItemEntry($wants[0]);
			$qtyA = $this->resolveQuantity($wantA["quantity"] ?? 1);
			$buyA = $this->resolveItem($wantA["item"] ?? "", $qtyA);
			if($buyA === null){
				return null;
			}

			$buyB = null;
			if(isset($wants[1])){
				$wantB = $this->unwrapItemEntry($wants[1]);
				$qtyB = $this->resolveQuantity($wantB["quantity"] ?? 1);
				$buyB = $this->resolveItem($wantB["item"] ?? "", $qtyB);
				if($buyB === null){
					return null;
				}
			}

			$qtySell = $this->resolveQuantity($give["quantity"] ?? 1);
			$sell = $this->resolveItem($give["item"] ?? "", $qtySell);
			if($sell === null){
				return null;
			}

			// Apply random enchants if requested
			foreach($functions as $func){
				if(($func["function"] ?? "") === "enchant_with_levels"){
					$enchants = VanillaEnchantments::getAll();
					$keys = array_keys($enchants);
					$ench = $enchants[$keys[array_rand($keys)]];
					$sell->addEnchantment(new EnchantmentInstance($ench, mt_rand(1, $ench->getMaxLevel())));
					break;
				}
			}
		}

		$maxUses = (int) ($tradeData["max_uses"] ?? 16);
		$traderExp = (int) ($tradeData["trader_exp"] ?? 1);
		$rewardExp = (bool) ($tradeData["reward_exp"] ?? true);
		$priceMultiplierA = (float) ($wants[0]["price_multiplier"] ?? 0.05);
		$priceMultiplierB = (float) ($wants[1]["price_multiplier"] ?? 0.05);

		return new TradeOffer(
			$buyA,
			$buyB,
			$sell,
			$maxUses,
			0,
			$traderExp,
			$rewardExp,
			$tier,
			$priceMultiplierA,
			$priceMultiplierB
		);
	}

	/**
	 * Generates new trade offers for the specified profession and tier.
	 *
	 * @return TradeOffer[]
	 */
	public function generateTradesForTier(int $profession, int $tier) : array{
		$tableName = VillagerProfession::getTradeTableName($profession);
		if($tableName === null){
			return [];
		}

		$data = $this->getTradeTable($tableName);
		if($data === null || !isset($data["tiers"])){
			return [];
		}

		$tierIndex = $tier - 1;
		$tierData = $data["tiers"][$tierIndex] ?? null;
		if($tierData === null || !isset($tierData["groups"])){
			return [];
		}

		$result = [];
		foreach($tierData["groups"] as $group){
			$numToSelect = (int) ($group["num_to_select"] ?? 1);
			$trades = $group["trades"] ?? [];
			if(count($trades) === 0){
				continue;
			}

			shuffle($trades);
			$selected = 0;
			foreach($trades as $tradeData){
				$offer = $this->buildTradeOffer($tradeData, $tier);
				if($offer !== null){
					$result[] = $offer;
					$selected++;
					if($selected >= $numToSelect){
						break;
					}
				}
			}
		}

		return $result;
	}

	/**
	 * Generates all trades for a villager up to the specified tier.
	 *
	 * @return TradeOffer[]
	 */
	public function generateAllTradesUpToTier(int $profession, int $maxTier) : array{
		$offers = [];
		for($t = 1; $t <= $maxTier; $t++){
			foreach($this->generateTradesForTier($profession, $t) as $offer){
				$offers[] = $offer;
			}
		}
		return $offers;
	}

	/**
	 * Generates 5 randomized trade offers for a wandering trader.
	 *
	 * @return TradeOffer[]
	 */
	public function generateWanderingTraderTrades() : array{
		$data = $this->getTradeTable("wandering_trader_trades.json");
		if($data === null || !isset($data["tiers"][0]["groups"])){
			return [];
		}

		$result = [];
		foreach($data["tiers"][0]["groups"] as $group){
			$numToSelect = (int) ($group["num_to_select"] ?? 1);
			$trades = $group["trades"] ?? [];
			if(count($trades) === 0){
				continue;
			}

			shuffle($trades);
			$selected = 0;
			foreach($trades as $tradeData){
				$offer = $this->buildTradeOffer($tradeData, 1);
				if($offer !== null){
					$result[] = $offer;
					$selected++;
					if($selected >= $numToSelect){
						break;
					}
				}
			}
		}

		return $result;
	}

	private static function posKey(World $world, Vector3 $pos) : string{
		return $world->getFolderName() . ":" . (int) $pos->x . ":" . (int) $pos->y . ":" . (int) $pos->z;
	}

	public function isWorkstationClaimed(World $world, Vector3 $pos) : bool{
		return isset($this->claimedWorkstations[self::posKey($world, $pos)]);
	}

	public function claimWorkstation(World $world, Vector3 $pos, int $entityRuntimeId) : bool{
		$key = self::posKey($world, $pos);
		if(isset($this->claimedWorkstations[$key])){
			return $this->claimedWorkstations[$key] === $entityRuntimeId;
		}

		$this->claimedWorkstations[$key] = $entityRuntimeId;
		return true;
	}

	public function releaseWorkstation(World $world, Vector3 $pos) : void{
		unset($this->claimedWorkstations[self::posKey($world, $pos)]);
	}

	public function releaseAllForEntity(int $entityRuntimeId) : void{
		foreach($this->claimedWorkstations as $key => $id){
			if($id === $entityRuntimeId){
				unset($this->claimedWorkstations[$key]);
			}
		}
	}
}
