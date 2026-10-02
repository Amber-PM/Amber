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

namespace pocketmine\reload;

use pocketmine\player\Player;
use pocketmine\resourcepacks\ResourcePack;
use pocketmine\Server;
use function array_map;
use function bin2hex;
use function count;
use function implode;
use function is_numeric;
use function is_string;
use function strrpos;
use function substr;
use function trim;

/**
 * Reloads resource packs and add-ons. Clients only download packs when they join, so when the pack stack changed,
 * online players are sent back to the address they joined through: they reconnect at once and get the new packs.
 */
final class AssetReloader{

	public function __construct(private Server $server){}

	public function reload() : ReloadReport{
		$report = new ReloadReport();
		$before = self::stackFingerprint($this->server->getResourcePackManager()->getResourceStack());
		try{
			$this->server->reloadResourcePacks();
		}catch(\Throwable $e){
			$report->error("Resource packs could not be reloaded: " . $e->getMessage());
			return $report;
		}

		$addons = $this->server->getAddonManager()->reload();
		$report->applied("Reloaded add-on loot tables, structures, trade tables and spawn rules");
		if($addons["scripts"] === true){
			$report->applied("Restarted add-on scripts");
		}elseif($addons["scripts"] === false){
			$report->error("Add-on scripts could not be restarted, see the console");
		}
		foreach($addons["restartNeeded"] as $what){
			$report->needsRestart("Add-ons: $what changed");
		}

		$stack = $this->server->getResourcePackManager()->getResourceStack();
		if(self::stackFingerprint($stack) === $before){
			$report->applied("Resource packs unchanged (" . count($stack) . " loaded)");
			return $report;
		}
		$moved = 0;
		$stayed = 0;
		foreach($this->server->getOnlinePlayers() as $player){
			if($this->sendBack($player)){
				++$moved;
			}else{
				++$stayed;
			}
		}
		$report->applied("Loaded the new resource packs (" . count($stack) . "); $moved player(s) are reconnecting to download them");
		if($stayed > 0){
			$report->needsRestart("$stayed player(s) get the new resource packs when they next join (their join address is unknown)");
		}
		return $report;
	}

	/** Transfers the player to the address it joined through, so it reconnects and downloads the new packs. */
	private function sendBack(Player $player) : bool{
		$address = $player->getPlayerInfo()->getExtraData()["ServerAddress"] ?? null;
		if(!is_string($address) || ($colon = strrpos($address, ":")) === false){
			return false;
		}
		$host = trim(substr($address, 0, $colon), "[]");
		$port = substr($address, $colon + 1);
		if($host === "" || !is_numeric($port)){
			return false;
		}
		return $player->transfer($host, (int) $port, "Reloading resource packs");
	}

	/** @param ResourcePack[] $stack */
	private static function stackFingerprint(array $stack) : string{
		return implode(",", array_map(static fn(ResourcePack $pack) : string => $pack->getPackId() . "@" . $pack->getPackVersion() . "#" . bin2hex($pack->getSha256()), $stack));
	}
}
