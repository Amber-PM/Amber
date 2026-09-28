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

use pocketmine\addon\AddonJson;
use pocketmine\addon\AddonManager;
use pocketmine\item\Item;
use pocketmine\item\StringToItemParser;
use pocketmine\world\format\io\GlobalItemDataHandlers;
use Symfony\Component\Filesystem\Path;
use function array_is_list;
use function array_map;
use function array_rand;
use function array_slice;
use function array_values;
use function count;
use function explode;
use function file_get_contents;
use function is_array;
use function is_file;
use function is_numeric;
use function is_string;
use function max;
use function min;
use function mt_rand;
use function shuffle;
use function str_contains;
use function strtolower;
use function substr_count;

/**
 * A behavior pack trade table (trading/*.json): tiers unlocked by trader experience, each with groups of trades
 * from which a number are picked when the mob first trades.
 */
final class TradeTable{
	/**
	 * @param list<array{exp: int, groups: list<array{pick: int, trades: list<mixed[]>}>}> $tiers
	 */
	private function __construct(private array $tiers){}

	/** @param list<string> $packRoots */
	public static function load(string $path, array $packRoots) : ?self{
		foreach($packRoots as $root){
			$file = Path::join($root, $path);
			if(!is_file($file)){
				continue;
			}
			$json = AddonJson::decode((string) file_get_contents($file), $path);
			$tiers = [];
			foreach(is_array($json["tiers"] ?? null) ? $json["tiers"] : [] as $tier){
				if(!is_array($tier)){
					continue;
				}
				$groups = [];
				if(is_array($tier["groups"] ?? null)){
					foreach($tier["groups"] as $group){
						if(is_array($group)){
							$trades = is_array($group["trades"] ?? null) ? array_values($group["trades"]) : [];
							$groups[] = ["pick" => (int) ($group["num_to_select"] ?? count($trades)), "trades" => $trades];
						}
					}
				}elseif(is_array($tier["trades"] ?? null)){
					$groups[] = ["pick" => count($tier["trades"]), "trades" => array_values($tier["trades"])];
				}
				$tiers[] = ["exp" => (int) ($tier["total_exp_required"] ?? 0), "groups" => $groups];
			}
			return new self($tiers);
		}
		return null;
	}

	/** @return list<int> experience needed for each tier */
	public function getTierExperience() : array{
		return array_map(static fn(array $tier) : int => $tier["exp"], $this->tiers);
	}

	/**
	 * Rolls the offers a new trader gets: num_to_select trades per group, each with its quantities rolled.
	 *
	 * @return list<TradeOffer>
	 */
	public function roll(int $firstNetId) : array{
		$offers = [];
		$netId = $firstNetId;
		foreach($this->tiers as $tierIndex => $tier){
			foreach($tier["groups"] as $group){
				$trades = $group["trades"];
				shuffle($trades);
				foreach(array_slice($trades, 0, max(0, $group["pick"])) as $trade){
					$offer = self::offer($trade, $tierIndex, $netId);
					if($offer !== null){
						$offers[] = $offer;
						$netId++;
					}
				}
			}
		}
		return $offers;
	}

	/** @param mixed $trade */
	private static function offer(mixed $trade, int $tier, int $netId) : ?TradeOffer{
		if(!is_array($trade)){
			return null;
		}
		$wants = self::stacks($trade["wants"] ?? []);
		$gives = self::stacks($trade["gives"] ?? []);
		if($wants === [] || $gives === []){
			return null;
		}
		return new TradeOffer(
			$netId,
			$wants[0],
			$wants[1] ?? null,
			$gives[0],
			$tier,
			max(1, (int) ($trade["max_uses"] ?? 7)),
			max(0, (int) ($trade["trader_exp"] ?? 1)),
			(bool) ($trade["reward_exp"] ?? true)
		);
	}

	/**
	 * Items of a wants/gives list; "choice" entries pick one at random.
	 *
	 * @return list<Item>
	 */
	private static function stacks(mixed $list) : array{
		$out = [];
		foreach(is_array($list) ? (array_is_list($list) ? $list : [$list]) : [] as $entry){
			if(is_array($entry) && is_array($entry["choice"] ?? null) && $entry["choice"] !== []){
				$entry = $entry["choice"][array_rand($entry["choice"])];
			}
			$name = is_array($entry) ? ($entry["item"] ?? null) : $entry;
			if(!is_string($name)){
				continue;
			}
			$item = self::item($name);
			if($item === null){
				continue;
			}
			$quantity = is_array($entry) ? ($entry["quantity"] ?? 1) : 1;
			if(is_array($quantity)){
				$min = (int) ($quantity["min"] ?? 1);
				$max = (int) ($quantity["max"] ?? $min);
				$quantity = mt_rand(min($min, $max), max($min, $max));
			}
			$out[] = $item->setCount(max(1, min($item->getMaxStackSize(), is_numeric($quantity) ? (int) $quantity : 1)));
		}
		return $out;
	}

	private static function item(string $name) : ?Item{
		$name = strtolower($name);
		$meta = null;
		if(substr_count($name, ":") === 2){
			[$namespace, $short, $data] = explode(":", $name, 3);
			$name = "$namespace:$short";
			$meta = (int) $data;
		}
		$full = str_contains($name, ":") ? $name : "minecraft:$name";
		$item = AddonManager::getInstance()?->getItem($full) ?? StringToItemParser::getInstance()->parse($full) ?? StringToItemParser::getInstance()->parse(explode(":", $full, 2)[1]);
		if($item !== null && $meta !== null && $meta > 0){
			try{
				$item = GlobalItemDataHandlers::getDeserializer()->deserializeStack(
					GlobalItemDataHandlers::getUpgrader()->upgradeItemTypeDataString($full, $meta, 1, null)
				);
			}catch(\Throwable){
			}
		}
		return $item;
	}
}
