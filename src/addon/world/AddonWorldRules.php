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

namespace pocketmine\addon\world;

use pocketmine\network\mcpe\protocol\GameRulesChangedPacket;
use pocketmine\network\mcpe\protocol\LevelEventPacket;
use pocketmine\network\mcpe\protocol\types\BoolGameRule;
use pocketmine\network\mcpe\protocol\types\IntGameRule;
use pocketmine\network\mcpe\protocol\types\LevelEvent;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\utils\Filesystem;
use pocketmine\world\World;
use function file_get_contents;
use function in_array;
use function is_array;
use function is_bool;
use function is_file;
use function is_int;
use function json_decode;
use function json_encode;
use function mt_rand;
use function strtolower;

/**
 * Weather and game rules for add-ons, which PocketMine has none of.
 *
 * Weather is per world and visual (clients render rain and thunder); scripts read it back and get
 * weatherChange events. Game rules are shared by scripts (world.gameRules), /gamerule and plugins; those with
 * an effect on the server are enforced by the runtime's listeners, the client-side ones are sent to players.
 */
final class AddonWorldRules{
	public const CLEAR = "clear";
	public const RAIN = "rain";
	public const THUNDER = "thunder";

	/** Game rules and their defaults. Rules the client shows are sent to it. */
	public const RULES = [
		"commandblockoutput" => true, "commandblocksenabled" => true, "dodaylightcycle" => true, "doentitydrops" => true,
		"dofiretick" => true, "doimmediaterespawn" => false, "doinsomnia" => true, "domobloot" => true, "domobspawning" => true,
		"dotiledrops" => true, "doweathercycle" => true, "drowningdamage" => true, "falldamage" => true, "firedamage" => true,
		"freezedamage" => true, "functioncommandlimit" => 10000, "keepinventory" => false, "maxcommandchainlength" => 65535,
		"mobgriefing" => true, "naturalregeneration" => true, "playerssleepingpercentage" => 100, "projectilescanbreakblocks" => true,
		"pvp" => true, "randomtickspeed" => 1, "recipesunlock" => true, "respawnblocksexplode" => true, "sendcommandfeedback" => true,
		"showbordereffect" => true, "showcoordinates" => false, "showdaysplayed" => false, "showdeathmessages" => true,
		"showrecipemessages" => true, "showtags" => true, "spawnradius" => 5, "tntexplodes" => true, "tntexplosiondropdecay" => false, "dolimitedcrafting" => false,
	];
	/** Rules the client needs to know. */
	private const CLIENT_RULES = ["showcoordinates", "showdaysplayed", "doimmediaterespawn", "showdeathmessages", "dodaylightcycle", "showtags", "recipesunlock", "showrecipemessages"];

	/** @var array<string, bool|int> changed rules only */
	private array $rules = [];
	/** @var array<string, array{string, int}> world folder => [weather, ticks left (-1 = until changed)] */
	private array $weather = [];
	/** @var \Closure(World, string, string) : void|null called when a world's weather changes */
	private ?\Closure $onWeatherChange = null;
	/** @var \Closure(World, string, string, int) : bool|null asked before the weather changes; true cancels */
	private ?\Closure $beforeWeatherChange = null;
	/** @var \Closure(string, bool|int) : void|null called when a game rule changes */
	private ?\Closure $onRuleChange = null;

	public function __construct(private Server $server, private string $file){
		$data = is_file($file) ? json_decode((string) @file_get_contents($file), true) : null;
		foreach(is_array($data["rules"] ?? null) ? $data["rules"] : [] as $rule => $value){
			if(isset(self::RULES[$rule]) && (is_bool($value) || is_int($value))){
				$this->rules[(string) $rule] = $value;
			}
		}
	}

	public function onWeatherChange(\Closure $callback) : void{
		$this->onWeatherChange = $callback;
	}

	/** @param \Closure(World, string, string, int) : bool $callback returns true to cancel the change */
	public function beforeWeatherChange(\Closure $callback) : void{
		$this->beforeWeatherChange = $callback;
	}

	/** @param \Closure(string, bool|int) : void $callback */
	public function onRuleChange(\Closure $callback) : void{
		$this->onRuleChange = $callback;
	}

	// ---------------------------------------------------------------- game rules

	public function getRule(string $rule) : bool|int|null{
		$rule = strtolower($rule);
		return $this->rules[$rule] ?? self::RULES[$rule] ?? null;
	}

	public function isEnabled(string $rule) : bool{
		return (bool) $this->getRule($rule);
	}

	/** Sets a rule (true/false for boolean rules, a number for the others). Returns false for unknown rules. */
	public function setRule(string $rule, bool|int|string $value) : bool{
		$rule = strtolower($rule);
		if(!isset(self::RULES[$rule])){
			return false;
		}
		$default = self::RULES[$rule];
		if(is_bool($default)){
			$value = is_bool($value) ? $value : (strtolower((string) $value) === "true" || $value === 1 || $value === "1");
		}else{
			$value = (int) $value;
		}
		$changed = $this->getRule($rule) !== $value;
		$this->rules[$rule] = $value;
		$this->save();
		if($changed && $this->onRuleChange !== null){
			($this->onRuleChange)($rule, $value);
		}
		if($rule === "dodaylightcycle"){
			foreach($this->server->getWorldManager()->getWorlds() as $world){
				(bool) $value ? $world->startTime() : $world->stopTime();
			}
		}
		if(in_array($rule, self::CLIENT_RULES, true)){
			foreach($this->server->getOnlinePlayers() as $player){
				$this->sendRules($player, [$rule]);
			}
		}
		return true;
	}

	/** @return array<string, bool|int> */
	public function getRules() : array{
		return $this->rules + self::RULES;
	}

	/** @param list<string>|null $only */
	public function sendRules(Player $player, ?array $only = null) : void{
		$rules = [];
		foreach($only ?? self::CLIENT_RULES as $rule){
			$value = $this->getRule($rule);
			if(is_bool($value)){
				$rules[$rule] = new BoolGameRule($value, false);
			}elseif(is_int($value)){
				$rules[$rule] = new IntGameRule($value, false);
			}
		}
		$player->getNetworkSession()->sendDataPacket(GameRulesChangedPacket::create($rules));
	}

	/** On join: client rules that differ from the default, and the world's weather. */
	public function onJoin(Player $player) : void{
		$changed = [];
		foreach(self::CLIENT_RULES as $rule){
			if(isset($this->rules[$rule])){
				$changed[] = $rule;
			}
		}
		if($changed !== []){
			$this->sendRules($player, $changed);
		}
		$this->sendWeather($player, $this->getWeather($player->getWorld()));
	}

	/** Applies world-wide rules to a world that just loaded. */
	public function applyToWorld(World $world) : void{
		if(!$this->isEnabled("dodaylightcycle")){
			$world->stopTime();
		}
	}

	// ---------------------------------------------------------------- weather

	public function getWeather(World $world) : string{
		return $this->weather[$world->getFolderName()][0] ?? self::CLEAR;
	}

	/** Sets a world's weather for $ticks (random 5-15 minutes when null, like the game; -1 until changed). */
	public function setWeather(World $world, string $weather, ?int $ticks = null) : void{
		$weather = strtolower($weather);
		if(!in_array($weather, [self::CLEAR, self::RAIN, self::THUNDER], true)){
			return;
		}
		$old = $this->getWeather($world);
		$ticks ??= mt_rand(6000, 18000);
		if($old !== $weather && $this->beforeWeatherChange !== null && ($this->beforeWeatherChange)($world, $old, $weather, $ticks) === true){
			return;
		}
		$this->weather[$world->getFolderName()] = [$weather, $ticks];
		foreach($world->getPlayers() as $player){
			$this->sendWeather($player, $weather);
		}
		if($old !== $weather && $this->onWeatherChange !== null){
			($this->onWeatherChange)($world, $old, $weather);
		}
	}

	private function sendWeather(Player $player, string $weather) : void{
		$session = $player->getNetworkSession();
		$session->sendDataPacket(LevelEventPacket::create($weather === self::CLEAR ? LevelEvent::STOP_RAIN : LevelEvent::START_RAIN, 65535, null));
		$session->sendDataPacket(LevelEventPacket::create($weather === self::THUNDER ? LevelEvent::START_THUNDER : LevelEvent::STOP_THUNDER, 65535, null));
	}

	/** Counts weather down; clears it when it runs out (only when doweathercycle is on). */
	public function tick(int $tickDiff) : void{
		if(!$this->isEnabled("doweathercycle")){
			return;
		}
		foreach($this->weather as $folder => [$weather, $left]){
			if($weather === self::CLEAR || $left < 0){
				continue;
			}
			$left -= $tickDiff;
			if($left <= 0){
				$world = $this->server->getWorldManager()->getWorldByName($folder);
				unset($this->weather[$folder]);
				if($world !== null){
					$this->setWeather($world, self::CLEAR, -1);
				}
			}else{
				$this->weather[$folder][1] = $left;
			}
		}
	}

	private function save() : void{
		Filesystem::safeFilePutContents($this->file, (string) json_encode(["rules" => $this->rules]));
	}
}
