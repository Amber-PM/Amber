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

namespace pocketmine\item;

use pocketmine\nbt\tag\CompoundTag;

final class ArmorTrim{

	public const MATERIAL_AMETHYST = "amethyst";
	public const MATERIAL_COPPER = "copper";
	public const MATERIAL_DIAMOND = "diamond";
	public const MATERIAL_EMERALD = "emerald";
	public const MATERIAL_GOLD = "gold";
	public const MATERIAL_IRON = "iron";
	public const MATERIAL_LAPIS = "lapis";
	public const MATERIAL_NETHERITE = "netherite";
	public const MATERIAL_QUARTZ = "quartz";
	public const MATERIAL_REDSTONE = "redstone";

	public const PATTERN_COAST = "coast";
	public const PATTERN_DUNE = "dune";
	public const PATTERN_EYE = "eye";
	public const PATTERN_HOST = "host";
	public const PATTERN_RAISER = "raiser";
	public const PATTERN_RIB = "rib";
	public const PATTERN_SENTRY = "sentry";
	public const PATTERN_SHAPER = "shaper";
	public const PATTERN_SILENCE = "silence";
	public const PATTERN_SNOUT = "snout";
	public const PATTERN_SPIRE = "spire";
	public const PATTERN_TIDE = "tide";
	public const PATTERN_VEX = "vex";
	public const PATTERN_WARD = "ward";
	public const PATTERN_WAYFINDER = "wayfinder";
	public const PATTERN_WILD = "wild";

	private const TEMPLATE_TO_PATTERN = [
		ItemTypeIds::COAST_ARMOR_TRIM_SMITHING_TEMPLATE => self::PATTERN_COAST,
		ItemTypeIds::DUNE_ARMOR_TRIM_SMITHING_TEMPLATE => self::PATTERN_DUNE,
		ItemTypeIds::EYE_ARMOR_TRIM_SMITHING_TEMPLATE => self::PATTERN_EYE,
		ItemTypeIds::HOST_ARMOR_TRIM_SMITHING_TEMPLATE => self::PATTERN_HOST,
		ItemTypeIds::RAISER_ARMOR_TRIM_SMITHING_TEMPLATE => self::PATTERN_RAISER,
		ItemTypeIds::RIB_ARMOR_TRIM_SMITHING_TEMPLATE => self::PATTERN_RIB,
		ItemTypeIds::SENTRY_ARMOR_TRIM_SMITHING_TEMPLATE => self::PATTERN_SENTRY,
		ItemTypeIds::SHAPER_ARMOR_TRIM_SMITHING_TEMPLATE => self::PATTERN_SHAPER,
		ItemTypeIds::SILENCE_ARMOR_TRIM_SMITHING_TEMPLATE => self::PATTERN_SILENCE,
		ItemTypeIds::SNOUT_ARMOR_TRIM_SMITHING_TEMPLATE => self::PATTERN_SNOUT,
		ItemTypeIds::SPIRE_ARMOR_TRIM_SMITHING_TEMPLATE => self::PATTERN_SPIRE,
		ItemTypeIds::TIDE_ARMOR_TRIM_SMITHING_TEMPLATE => self::PATTERN_TIDE,
		ItemTypeIds::VEX_ARMOR_TRIM_SMITHING_TEMPLATE => self::PATTERN_VEX,
		ItemTypeIds::WARD_ARMOR_TRIM_SMITHING_TEMPLATE => self::PATTERN_WARD,
		ItemTypeIds::WAYFINDER_ARMOR_TRIM_SMITHING_TEMPLATE => self::PATTERN_WAYFINDER,
		ItemTypeIds::WILD_ARMOR_TRIM_SMITHING_TEMPLATE => self::PATTERN_WILD,
	];

	private const ITEM_TO_MATERIAL = [
		ItemTypeIds::AMETHYST_SHARD => self::MATERIAL_AMETHYST,
		ItemTypeIds::COPPER_INGOT => self::MATERIAL_COPPER,
		ItemTypeIds::DIAMOND => self::MATERIAL_DIAMOND,
		ItemTypeIds::EMERALD => self::MATERIAL_EMERALD,
		ItemTypeIds::GOLD_INGOT => self::MATERIAL_GOLD,
		ItemTypeIds::IRON_INGOT => self::MATERIAL_IRON,
		ItemTypeIds::LAPIS_LAZULI => self::MATERIAL_LAPIS,
		ItemTypeIds::NETHERITE_INGOT => self::MATERIAL_NETHERITE,
		ItemTypeIds::NETHER_QUARTZ => self::MATERIAL_QUARTZ,
		ItemTypeIds::REDSTONE_DUST => self::MATERIAL_REDSTONE,
	];

	public function __construct(
		private string $material,
		private string $pattern
	){}

	public function getMaterial() : string{
		return $this->material;
	}

	public function getPattern() : string{
		return $this->pattern;
	}

	public static function getPatternFromTemplate(Item $template) : ?string{
		return self::TEMPLATE_TO_PATTERN[$template->getTypeId()] ?? null;
	}

	public static function getMaterialFromItem(Item $material) : ?string{
		return self::ITEM_TO_MATERIAL[$material->getTypeId()] ?? null;
	}

	public static function fromItems(Item $template, Item $material) : ?self{
		$pattern = self::getPatternFromTemplate($template);
		$mat = self::getMaterialFromItem($material);
		if($pattern === null || $mat === null){
			return null;
		}
		return new self($mat, $pattern);
	}

	public static function fromNbt(CompoundTag $tag) : ?self{
		$material = $tag->getString(Armor::TAG_TRIM_MATERIAL, "");
		$pattern = $tag->getString(Armor::TAG_TRIM_PATTERN, "");
		if($material === "" || $pattern === ""){
			return null;
		}
		return new self($material, $pattern);
	}

	public function toNbt() : CompoundTag{
		return CompoundTag::create()
			->setString(Armor::TAG_TRIM_MATERIAL, $this->material)
			->setString(Armor::TAG_TRIM_PATTERN, $this->pattern);
	}
}
