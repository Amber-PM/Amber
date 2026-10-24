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

final class GameRule{

	public const COMMAND_BLOCK_OUTPUT = "commandblockoutput";
	public const COMMAND_BLOCKS_ENABLED = "commandblocksenabled";
	public const DO_DAYLIGHT_CYCLE = "dodaylightcycle";
	public const DO_ENTITY_DROPS = "doentitydrops";
	public const DO_FIRE_TICK = "dofiretick";
	public const DO_IMMEDIATE_RESPAWN = "doimmediaterespawn";
	public const DO_INSOMNIA = "doinsomnia";
	public const DO_LIMITED_CRAFTING = "dolimitedcrafting";
	public const DO_MOB_LOOT = "domobloot";
	public const DO_MOB_SPAWNING = "domobspawning";
	public const DO_TILE_DROPS = "dotiledrops";
	public const DO_WEATHER_CYCLE = "doweathercycle";
	public const DROWNING_DAMAGE = "drowningdamage";
	public const FALL_DAMAGE = "falldamage";
	public const FIRE_DAMAGE = "firedamage";
	public const FREEZE_DAMAGE = "freezedamage";
	public const FUNCTION_COMMAND_LIMIT = "functioncommandlimit";
	public const KEEP_INVENTORY = "keepinventory";
	public const LOCATOR_BAR = "locatorbar";
	public const MAX_COMMAND_CHAIN_LENGTH = "maxcommandchainlength";
	public const MOB_GRIEFING = "mobgriefing";
	public const NATURAL_REGENERATION = "naturalregeneration";
	public const PLAYERS_SLEEPING_PERCENTAGE = "playerssleepingpercentage";
	public const PVP = "pvp";
	public const RANDOM_TICK_SPEED = "randomtickspeed";
	public const RECIPES_UNLOCK = "recipesunlock";
	public const RESPAWN_BLOCKS_EXPLODE = "respawnblocksexplode";
	public const SEND_COMMAND_FEEDBACK = "sendcommandfeedback";
	public const SHOW_COORDINATES = "showcoordinates";
	public const SHOW_DEATH_MESSAGES = "showdeathmessages";
	public const SHOW_TAGS = "showtags";
	public const SPAWN_RADIUS = "spawnradius";
	public const TNT_EXPLODES = "tntexplodes";

	private function __construct(){
		// NOOP
	}
}
