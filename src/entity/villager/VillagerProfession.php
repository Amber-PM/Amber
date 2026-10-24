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

use pocketmine\block\BlockTypeIds;

final class VillagerProfession{
	public const NONE = 0; // Unemployed
	public const FARMER = 1;
	public const FISHERMAN = 2;
	public const SHEPHERD = 3;
	public const FLETCHER = 4;
	public const LIBRARIAN = 5;
	public const CARTOGRAPHER = 6;
	public const CLERIC = 7;
	public const ARMORER = 8;
	public const WEAPON_SMITH = 9;
	public const TOOL_SMITH = 10;
	public const BUTCHER = 11;
	public const LEATHERWORKER = 12;
	public const MASON = 13;
	public const NITWIT = 14;

	private function __construct(){
		// NOOP
	}

	/**
	 * Returns the workstation block type ID corresponding to this profession, or null if none.
	 */
	public static function getWorkstationBlockId(int $profession) : ?int{
		return match($profession){
			self::FARMER => defined(BlockTypeIds::class . '::COMPOSTER') ? BlockTypeIds::COMPOSTER : 10836,
			self::FISHERMAN => BlockTypeIds::BARREL,
			self::SHEPHERD => BlockTypeIds::LOOM,
			self::FLETCHER => BlockTypeIds::FLETCHING_TABLE,
			self::LIBRARIAN => BlockTypeIds::LECTERN,
			self::CARTOGRAPHER => BlockTypeIds::CARTOGRAPHY_TABLE,
			self::CLERIC => BlockTypeIds::BREWING_STAND,
			self::ARMORER => BlockTypeIds::BLAST_FURNACE,
			self::WEAPON_SMITH => defined(BlockTypeIds::class . '::GRINDSTONE') ? BlockTypeIds::GRINDSTONE : 10835,
			self::TOOL_SMITH => BlockTypeIds::SMITHING_TABLE,
			self::BUTCHER => BlockTypeIds::SMOKER,
			self::LEATHERWORKER => BlockTypeIds::CAULDRON,
			self::MASON => BlockTypeIds::STONECUTTER,
			default => null,
		};
	}

	/**
	 * Returns the profession corresponding to the workstation block type ID, or null if not a workstation.
	 */
	public static function fromWorkstationBlockId(int $blockId) : ?int{
		$composterId = defined(BlockTypeIds::class . '::COMPOSTER') ? BlockTypeIds::COMPOSTER : 10836;
		$grindstoneId = defined(BlockTypeIds::class . '::GRINDSTONE') ? BlockTypeIds::GRINDSTONE : 10835;

		return match($blockId){
			$composterId => self::FARMER,
			BlockTypeIds::BARREL => self::FISHERMAN,
			BlockTypeIds::LOOM => self::SHEPHERD,
			BlockTypeIds::FLETCHING_TABLE => self::FLETCHER,
			BlockTypeIds::LECTERN => self::LIBRARIAN,
			BlockTypeIds::CARTOGRAPHY_TABLE => self::CARTOGRAPHER,
			BlockTypeIds::BREWING_STAND => self::CLERIC,
			BlockTypeIds::BLAST_FURNACE => self::ARMORER,
			$grindstoneId => self::WEAPON_SMITH,
			BlockTypeIds::SMITHING_TABLE => self::TOOL_SMITH,
			BlockTypeIds::SMOKER => self::BUTCHER,
			BlockTypeIds::CAULDRON,
			BlockTypeIds::WATER_CAULDRON,
			BlockTypeIds::LAVA_CAULDRON,
			BlockTypeIds::POTION_CAULDRON,
			BlockTypeIds::POWDER_SNOW_CAULDRON => self::LEATHERWORKER,
			BlockTypeIds::STONECUTTER,
			BlockTypeIds::LEGACY_STONECUTTER => self::MASON,
			default => null,
		};
	}

	public static function getDisplayName(int $profession) : string{
		return match($profession){
			self::FARMER => "Farmer",
			self::FISHERMAN => "Fisherman",
			self::SHEPHERD => "Shepherd",
			self::FLETCHER => "Fletcher",
			self::LIBRARIAN => "Librarian",
			self::CARTOGRAPHER => "Cartographer",
			self::CLERIC => "Cleric",
			self::ARMORER => "Armorer",
			self::WEAPON_SMITH => "Weaponsmith",
			self::TOOL_SMITH => "Toolsmith",
			self::BUTCHER => "Butcher",
			self::LEATHERWORKER => "Leatherworker",
			self::MASON => "Stone Mason",
			self::NITWIT => "Nitwit",
			default => "Villager",
		};
	}

	public static function getTradeTableName(int $profession) : ?string{
		return match($profession){
			self::FARMER => "farmer_trades.json",
			self::FISHERMAN => "fisherman_trades.json",
			self::SHEPHERD => "shepherd_trades.json",
			self::FLETCHER => "fletcher_trades.json",
			self::LIBRARIAN => "librarian_trades.json",
			self::CARTOGRAPHER => "cartographer_trades.json",
			self::CLERIC => "cleric_trades.json",
			self::ARMORER => "armorer_trades.json",
			self::WEAPON_SMITH => "weapon_smith_trades.json",
			self::TOOL_SMITH => "tool_smith_trades.json",
			self::BUTCHER => "butcher_trades.json",
			self::LEATHERWORKER => "leather_worker_trades.json",
			self::MASON => "stone_mason_trades.json",
			default => null,
		};
	}

	/**
	 * @return int[]
	 */
	public static function getAllProfessions() : array{
		return [
			self::NONE,
			self::FARMER,
			self::FISHERMAN,
			self::SHEPHERD,
			self::FLETCHER,
			self::LIBRARIAN,
			self::CARTOGRAPHER,
			self::CLERIC,
			self::ARMORER,
			self::WEAPON_SMITH,
			self::TOOL_SMITH,
			self::BUTCHER,
			self::LEATHERWORKER,
			self::MASON,
			self::NITWIT,
		];
	}
}
