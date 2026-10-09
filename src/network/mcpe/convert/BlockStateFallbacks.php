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

namespace pocketmine\network\mcpe\convert;

use function array_keys;
use function preg_match;
use function preg_replace;
use function str_starts_with;
use function substr;

/**
 * Stand-ins for blocks an older client does not have: the closest-looking block that existed before, so these
 * clients see a similar block instead of an "update!" block. Candidates are tried in order; only those present in
 * the client's palette are used.
 *
 * @internal
 */
final class BlockStateFallbacks{
	/**
	 * Rewrites of the name (without namespace), applied in order; every rule that matches adds a candidate.
	 * @var string[]
	 * @phpstan-var array<string, string>
	 */
	private const RULES = [
		//1.21.0 copper blocks
		'/^(waxed_)?(exposed_|weathered_|oxidized_)?copper_door$/' => 'iron_door',
		'/^(waxed_)?(exposed_|weathered_|oxidized_)?copper_trapdoor$/' => 'iron_trapdoor',
		'/^(waxed_)?(exposed_|weathered_|oxidized_)?copper_bulb$/' => 'redstone_lamp',
		'/^(waxed_)?(exposed_|weathered_|oxidized_)?(chiseled_copper|copper_grate)$/' => '$1$2cut_copper',
		//1.21.110 copper blocks
		'/^(waxed_)?(exposed_|weathered_|oxidized_)?copper_bars$/' => 'iron_bars',
		'/^(waxed_)?(exposed_|weathered_|oxidized_)?copper_lantern$/' => 'lantern',
		'/^(waxed_)?(exposed_|weathered_|oxidized_)?copper_chain$/' => 'iron_chain',
		'/^copper_torch$/' => 'torch',
		'/^(waxed_)?(exposed_|weathered_|oxidized_)lightning_rod$/' => 'lightning_rod',
		'/^waxed_lightning_rod$/' => 'lightning_rod',
		//1.21.0 tuff blocks
		'/^chiseled_tuff(_bricks)?$/' => 'chiseled_deepslate',
		'/^tuff_bricks$/' => 'deepslate_bricks',
		'/^tuff_brick_(slab|double_slab|stairs|wall)$/' => 'deepslate_brick_$1',
		'/^polished_tuff$/' => 'polished_deepslate',
		'/^polished_tuff_(slab|double_slab|stairs|wall)$/' => 'polished_deepslate_$1',
		'/^tuff_(slab|double_slab|stairs|wall)$/' => 'cobbled_deepslate_$1',
		//1.21.50 pale garden
		'/^(stripped_)?pale_oak_(log|wood)$/' => '$1dark_oak_$2',
		'/^pale_oak_(standing|wall)_sign$/' => 'darkoak_$1_sign',
		'/^pale_oak_(.+)$/' => 'dark_oak_$1',
		'/^pale_moss_(block|carpet)$/' => 'moss_$1',
		'/^pale_hanging_moss$/' => 'hanging_roots',
		'/^creaking_heart$/' => 'dark_oak_log',
		'/^(open|closed)_eyeblossom$/' => 'oxeye_daisy',
		//1.21.50 resin
		'/^resin_bricks$/' => 'brick_block',
		'/^resin_brick_(slab|double_slab|stairs|wall)$/' => 'brick_$1',
		'/^chiseled_resin_bricks$/' => 'chiseled_nether_bricks',
		'/^resin_block$/' => 'honeycomb_block',
		'/^resin_clump$/' => 'glow_lichen',
		//1.21.70+ plants
		'/^cactus_flower$/' => 'poppy',
		'/^(bush|firefly_bush)$/' => 'short_grass',
		'/^(short|tall)_dry_grass$/' => 'deadbush',
		'/^(leaf_litter|wildflowers)$/' => 'pink_petals',
		//1.26.x
		'/^(.+)_(wool|concrete)_(slab|double_slab|stairs)$/' => '$1_$2',
		'/^(.+)_cushion$/' => '$1_carpet',
		'/^(stripped_)?poplar_(log|wood)$/' => '$1birch_$2',
		'/^(orange|red|yellow)_poplar_leaves$/' => 'birch_leaves',
		'/^poplar_(standing|wall)_sign$/' => 'birch_$1_sign',
		'/^poplar_(.+)$/' => 'birch_$1',
	];

	/**
	 * Generic stand-ins by shape, tried after the rules.
	 * @var string[]
	 * @phpstan-var array<string, string>
	 */
	private const SHAPES = [
		'/_double_slab$/' => 'cobblestone_double_slab',
		'/_slab$/' => 'cobblestone_slab',
		'/_stairs$/' => 'stone_stairs',
		'/_wall$/' => 'cobblestone_wall',
		'/_fence_gate$/' => 'fence_gate',
		'/_fence$/' => 'oak_fence',
		'/_trapdoor$/' => 'trapdoor',
		'/_door$/' => 'wooden_door',
		'/_button$/' => 'wooden_button',
		'/_pressure_plate$/' => 'wooden_pressure_plate',
		'/_hanging_sign$/' => 'oak_hanging_sign',
		'/_leaves$/' => 'oak_leaves',
		'/_log$/' => 'oak_log',
	];

	private function __construct(){
		//NOOP
	}

	/**
	 * @return string[] namespaced block names, most similar first
	 * @phpstan-return list<string>
	 */
	public static function getCandidates(string $name) : array{
		if(!str_starts_with($name, "minecraft:")){
			return [];
		}
		$shortName = substr($name, 10);
		$candidates = [];
		foreach(self::RULES as $pattern => $replacement){
			$candidate = preg_replace($pattern, $replacement, $shortName, 1, $count);
			if($count > 0 && $candidate !== null && $candidate !== $shortName){
				$candidates["minecraft:" . $candidate] = true;
			}
		}
		foreach(self::SHAPES as $pattern => $replacement){
			if(preg_match($pattern, $shortName) === 1){
				$candidates["minecraft:" . $replacement] = true;
			}
		}
		return array_keys($candidates);
	}
}
