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

namespace pocketmine\world\gamerule;

use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\ShortTag;
use pocketmine\network\mcpe\protocol\GameRulesChangedPacket;
use pocketmine\network\mcpe\protocol\types\BoolGameRule;
use pocketmine\network\mcpe\protocol\types\GameRule as ProtocolGameRule;
use pocketmine\network\mcpe\protocol\types\IntGameRule;
use pocketmine\world\World;
use function is_bool;
use function is_int;
use function strtolower;

class GameRules{

	public const COMMAND_BLOCK_OUTPUT = GameRule::COMMAND_BLOCK_OUTPUT;
	public const COMMAND_BLOCKS_ENABLED = GameRule::COMMAND_BLOCKS_ENABLED;
	public const DO_DAYLIGHT_CYCLE = GameRule::DO_DAYLIGHT_CYCLE;
	public const DO_ENTITY_DROPS = GameRule::DO_ENTITY_DROPS;
	public const DO_FIRE_TICK = GameRule::DO_FIRE_TICK;
	public const DO_IMMEDIATE_RESPAWN = GameRule::DO_IMMEDIATE_RESPAWN;
	public const DO_INSOMNIA = GameRule::DO_INSOMNIA;
	public const DO_LIMITED_CRAFTING = GameRule::DO_LIMITED_CRAFTING;
	public const DO_MOB_LOOT = GameRule::DO_MOB_LOOT;
	public const DO_MOB_SPAWNING = GameRule::DO_MOB_SPAWNING;
	public const DO_TILE_DROPS = GameRule::DO_TILE_DROPS;
	public const DO_WEATHER_CYCLE = GameRule::DO_WEATHER_CYCLE;
	public const DROWNING_DAMAGE = GameRule::DROWNING_DAMAGE;
	public const FALL_DAMAGE = GameRule::FALL_DAMAGE;
	public const FIRE_DAMAGE = GameRule::FIRE_DAMAGE;
	public const FREEZE_DAMAGE = GameRule::FREEZE_DAMAGE;
	public const FUNCTION_COMMAND_LIMIT = GameRule::FUNCTION_COMMAND_LIMIT;
	public const KEEP_INVENTORY = GameRule::KEEP_INVENTORY;
	public const LOCATOR_BAR = GameRule::LOCATOR_BAR;
	public const MAX_COMMAND_CHAIN_LENGTH = GameRule::MAX_COMMAND_CHAIN_LENGTH;
	public const MOB_GRIEFING = GameRule::MOB_GRIEFING;
	public const NATURAL_REGENERATION = GameRule::NATURAL_REGENERATION;
	public const PLAYERS_SLEEPING_PERCENTAGE = GameRule::PLAYERS_SLEEPING_PERCENTAGE;
	public const PVP = GameRule::PVP;
	public const RANDOM_TICK_SPEED = GameRule::RANDOM_TICK_SPEED;
	public const RECIPES_UNLOCK = GameRule::RECIPES_UNLOCK;
	public const RESPAWN_BLOCKS_EXPLODE = GameRule::RESPAWN_BLOCKS_EXPLODE;
	public const SEND_COMMAND_FEEDBACK = GameRule::SEND_COMMAND_FEEDBACK;
	public const SHOW_COORDINATES = GameRule::SHOW_COORDINATES;
	public const SHOW_DEATH_MESSAGES = GameRule::SHOW_DEATH_MESSAGES;
	public const SHOW_TAGS = GameRule::SHOW_TAGS;
	public const SPAWN_RADIUS = GameRule::SPAWN_RADIUS;
	public const TNT_EXPLODES = GameRule::TNT_EXPLODES;

	/** @var array<string, GameRuleDefinition> */
	private array $definitions = [];

	/** @var array<string, bool|int> */
	private array $values = [];

	public function __construct(
		private ?World $world = null
	){
		$this->registerDefaults();
	}

	public function getWorld() : ?World{
		return $this->world;
	}

	public function setWorld(?World $world) : void{
		$this->world = $world;
	}

	private function registerDefaults() : void{
		$this->register(new GameRuleDefinition(GameRule::COMMAND_BLOCK_OUTPUT, GameRuleType::BOOL, true));
		$this->register(new GameRuleDefinition(GameRule::COMMAND_BLOCKS_ENABLED, GameRuleType::BOOL, true));
		$this->register(new GameRuleDefinition(GameRule::DO_DAYLIGHT_CYCLE, GameRuleType::BOOL, true));
		$this->register(new GameRuleDefinition(GameRule::DO_ENTITY_DROPS, GameRuleType::BOOL, true));
		$this->register(new GameRuleDefinition(GameRule::DO_FIRE_TICK, GameRuleType::BOOL, true));
		$this->register(new GameRuleDefinition(GameRule::DO_IMMEDIATE_RESPAWN, GameRuleType::BOOL, false));
		$this->register(new GameRuleDefinition(GameRule::DO_INSOMNIA, GameRuleType::BOOL, true));
		$this->register(new GameRuleDefinition(GameRule::DO_LIMITED_CRAFTING, GameRuleType::BOOL, false));
		$this->register(new GameRuleDefinition(GameRule::DO_MOB_LOOT, GameRuleType::BOOL, true));
		$this->register(new GameRuleDefinition(GameRule::DO_MOB_SPAWNING, GameRuleType::BOOL, true));
		$this->register(new GameRuleDefinition(GameRule::DO_TILE_DROPS, GameRuleType::BOOL, true));
		$this->register(new GameRuleDefinition(GameRule::DO_WEATHER_CYCLE, GameRuleType::BOOL, true));
		$this->register(new GameRuleDefinition(GameRule::DROWNING_DAMAGE, GameRuleType::BOOL, true));
		$this->register(new GameRuleDefinition(GameRule::FALL_DAMAGE, GameRuleType::BOOL, true));
		$this->register(new GameRuleDefinition(GameRule::FIRE_DAMAGE, GameRuleType::BOOL, true));
		$this->register(new GameRuleDefinition(GameRule::FREEZE_DAMAGE, GameRuleType::BOOL, true));
		$this->register(new GameRuleDefinition(GameRule::FUNCTION_COMMAND_LIMIT, GameRuleType::INT, 10000));
		$this->register(new GameRuleDefinition(GameRule::KEEP_INVENTORY, GameRuleType::BOOL, false));
		$this->register(new GameRuleDefinition(GameRule::LOCATOR_BAR, GameRuleType::BOOL, false));
		$this->register(new GameRuleDefinition(GameRule::MAX_COMMAND_CHAIN_LENGTH, GameRuleType::INT, 65536));
		$this->register(new GameRuleDefinition(GameRule::MOB_GRIEFING, GameRuleType::BOOL, true));
		$this->register(new GameRuleDefinition(GameRule::NATURAL_REGENERATION, GameRuleType::BOOL, true));
		$this->register(new GameRuleDefinition(GameRule::PLAYERS_SLEEPING_PERCENTAGE, GameRuleType::INT, 100));
		$this->register(new GameRuleDefinition(GameRule::PVP, GameRuleType::BOOL, true));
		$this->register(new GameRuleDefinition(GameRule::RANDOM_TICK_SPEED, GameRuleType::INT, 1));
		$this->register(new GameRuleDefinition(GameRule::RECIPES_UNLOCK, GameRuleType::BOOL, false));
		$this->register(new GameRuleDefinition(GameRule::RESPAWN_BLOCKS_EXPLODE, GameRuleType::BOOL, true));
		$this->register(new GameRuleDefinition(GameRule::SEND_COMMAND_FEEDBACK, GameRuleType::BOOL, true));
		$this->register(new GameRuleDefinition(GameRule::SHOW_COORDINATES, GameRuleType::BOOL, false));
		$this->register(new GameRuleDefinition(GameRule::SHOW_DEATH_MESSAGES, GameRuleType::BOOL, true));
		$this->register(new GameRuleDefinition(GameRule::SHOW_TAGS, GameRuleType::BOOL, true));
		$this->register(new GameRuleDefinition(GameRule::SPAWN_RADIUS, GameRuleType::INT, 5));
		$this->register(new GameRuleDefinition(GameRule::TNT_EXPLODES, GameRuleType::BOOL, true));
	}

	public function register(GameRuleDefinition $definition) : void{
		$this->definitions[$definition->getName()] = $definition;
		$this->values[$definition->getName()] = $definition->getDefaultValue();
	}

	public function has(string $name) : bool{
		return isset($this->definitions[$this->canonicalizeName($name) ?? ""]);
	}

	public function canonicalizeName(string $name) : ?string{
		$lower = strtolower($name);
		if(isset($this->definitions[$lower])){
			return $lower;
		}

		// Common aliases / CamelCase mappings
		return match($lower){
			"keep_inventory" => GameRule::KEEP_INVENTORY,
			"do_mob_spawning" => GameRule::DO_MOB_SPAWNING,
			"mob_griefing" => GameRule::MOB_GRIEFING,
			"do_mob_loot" => GameRule::DO_MOB_LOOT,
			"do_tile_drops" => GameRule::DO_TILE_DROPS,
			"do_fire_tick" => GameRule::DO_FIRE_TICK,
			"natural_regeneration" => GameRule::NATURAL_REGENERATION,
			"do_daylight_cycle" => GameRule::DO_DAYLIGHT_CYCLE,
			"do_weather_cycle" => GameRule::DO_WEATHER_CYCLE,
			"random_tick_speed" => GameRule::RANDOM_TICK_SPEED,
			"spawn_radius" => GameRule::SPAWN_RADIUS,
			"show_coordinates" => GameRule::SHOW_COORDINATES,
			"tnt_explodes" => GameRule::TNT_EXPLODES,
			"show_death_messages" => GameRule::SHOW_DEATH_MESSAGES,
			"drowning_damage" => GameRule::DROWNING_DAMAGE,
			"fall_damage" => GameRule::FALL_DAMAGE,
			"fire_damage" => GameRule::FIRE_DAMAGE,
			"freeze_damage" => GameRule::FREEZE_DAMAGE,
			"command_blocks_enabled" => GameRule::COMMAND_BLOCKS_ENABLED,
			"command_block_output" => GameRule::COMMAND_BLOCK_OUTPUT,
			"send_command_feedback" => GameRule::SEND_COMMAND_FEEDBACK,
			"do_immediate_respawn" => GameRule::DO_IMMEDIATE_RESPAWN,
			"immediate_respawn", "immediaterespawn" => GameRule::DO_IMMEDIATE_RESPAWN,
			"players_sleeping_percentage" => GameRule::PLAYERS_SLEEPING_PERCENTAGE,
			default => null,
		};
	}

	public function getType(string $name) : ?int{
		$canonical = $this->canonicalizeName($name);
		return $canonical !== null ? ($this->definitions[$canonical]->getType() ?? null) : null;
	}

	public function get(string $name) : bool|int{
		$canonical = $this->canonicalizeName($name);
		if($canonical === null){
			throw new \InvalidArgumentException("Unknown game rule: $name");
		}
		return $this->values[$canonical] ?? $this->definitions[$canonical]->getDefaultValue();
	}

	public function getBool(string $name) : bool{
		$val = $this->get($name);
		if(!is_bool($val)){
			throw new \InvalidArgumentException("Game rule $name is an integer, not a boolean");
		}
		return $val;
	}

	public function getInt(string $name) : int{
		$val = $this->get($name);
		if(!is_int($val)){
			throw new \InvalidArgumentException("Game rule $name is a boolean, not an integer");
		}
		return $val;
	}

	public function set(string $name, bool|int $value, bool $broadcast = true) : bool{
		$canonical = $this->canonicalizeName($name);
		if($canonical === null){
			return false;
		}

		$def = $this->definitions[$canonical];
		if($def->getType() === GameRuleType::BOOL && !is_bool($value)){
			return false;
		}
		if($def->getType() === GameRuleType::INT && !is_int($value)){
			return false;
		}

		$this->values[$canonical] = $value;

		if($broadcast){
			$this->broadcastRule($canonical);
		}

		return true;
	}

	/**
	 * @return array<string, bool|int>
	 */
	public function getAll() : array{
		$all = [];
		foreach($this->definitions as $name => $def){
			$all[$name] = $this->values[$name] ?? $def->getDefaultValue();
		}
		return $all;
	}

	public function toNetworkRule(string $name) : ?ProtocolGameRule{
		$canonical = $this->canonicalizeName($name);
		if($canonical === null){
			return null;
		}
		$def = $this->definitions[$canonical];
		$val = $this->values[$canonical] ?? $def->getDefaultValue();
		if($def->getType() === GameRuleType::BOOL){
			return new BoolGameRule((bool) $val, $def->isPlayerModifiable());
		}
		return new IntGameRule((int) $val, $def->isPlayerModifiable());
	}

	/**
	 * @return array<string, ProtocolGameRule>
	 */
	public function toNetworkRules() : array{
		$rules = [];
		foreach($this->definitions as $name => $def){
			$val = $this->values[$name] ?? $def->getDefaultValue();
			if($def->getType() === GameRuleType::BOOL){
				$rules[$name] = new BoolGameRule((bool) $val, $def->isPlayerModifiable());
			}else{
				$rules[$name] = new IntGameRule((int) $val, $def->isPlayerModifiable());
			}
		}
		return $rules;
	}

	public function broadcastRule(string $name) : void{
		if($this->world === null){
			return;
		}
		$canonical = $this->canonicalizeName($name);
		if($canonical === null){
			return;
		}
		$rule = $this->toNetworkRule($canonical);
		if($rule === null){
			return;
		}

		$pk = GameRulesChangedPacket::create([$canonical => $rule]);
		foreach($this->world->getPlayers() as $player){
			$player->getNetworkSession()->sendDataPacket($pk);
		}
	}

	public function readFromNbt(CompoundTag $nbt) : void{
		$gameRulesTag = $nbt->getCompoundTag("GameRules");
		foreach($this->definitions as $name => $def){
			$tag = null;
			if($gameRulesTag !== null){
				$tag = $gameRulesTag->getTag($name);
			}
			if($tag === null){
				$tag = $nbt->getTag($name);
			}
			if($tag !== null){
				if($def->getType() === GameRuleType::BOOL && $tag instanceof ByteTag){
					$this->values[$name] = $tag->getValue() !== 0;
				}elseif($def->getType() === GameRuleType::INT && ($tag instanceof IntTag || $tag instanceof ShortTag || $tag instanceof ByteTag)){
					$this->values[$name] = $tag->getValue();
				}
			}
		}
	}

	public function writeToNbt(CompoundTag $nbt) : void{
		$gameRulesTag = CompoundTag::create();
		foreach($this->definitions as $name => $def){
			$val = $this->values[$name] ?? $def->getDefaultValue();
			if($def->getType() === GameRuleType::BOOL){
				$byte = new ByteTag($val ? 1 : 0);
				$gameRulesTag->setTag($name, $byte);
				$nbt->setTag($name, clone $byte);
			}else{
				$int = new IntTag((int) $val);
				$gameRulesTag->setTag($name, $int);
				$nbt->setTag($name, clone $int);
			}
		}
		$nbt->setTag("GameRules", $gameRulesTag);
	}
}
