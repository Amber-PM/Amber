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

namespace pocketmine\entity\villager\data;

final class VanillaTradeData{

	/** @var array<string, array<string, mixed>> */
	private static array $tables = [];

	private function __construct(){
		// NOOP
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public static function getTable(string $name) : ?array{
		if(isset(self::$tables[$name])){
			return self::$tables[$name];
		}

		$data = self::loadTable($name);
		if($data !== null){
			self::$tables[$name] = $data;
		}
		return $data;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private static function loadTable(string $name) : ?array{
		return match($name){
			'armorer_trades.json' => array (
  'tiers' => 
  array (
    0 => 
    array (
      'total_exp_required' => 0,
      'groups' => 
      array (
        0 => 
        array (
          'num_to_select' => 1,
          'trades' => 
          array (
            0 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:coal:0',
                  'quantity' => 15,
                  'price_multiplier' => 0.05,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                ),
              ),
              'trader_exp' => 2,
              'max_uses' => 16,
              'reward_exp' => true,
            ),
          ),
        ),
        1 => 
        array (
          'num_to_select' => 1,
          'trades' => 
          array (
            0 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 7,
                  'price_multiplier' => 0.2,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:iron_leggings',
                  'quantity' => 1,
                ),
              ),
              'trader_exp' => 1,
              'max_uses' => 12,
              'reward_exp' => true,
            ),
            1 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 4,
                  'price_multiplier' => 0.2,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:iron_boots',
                  'quantity' => 1,
                ),
              ),
              'trader_exp' => 1,
              'max_uses' => 12,
              'reward_exp' => true,
            ),
            2 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 5,
                  'price_multiplier' => 0.2,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:iron_helmet',
                  'quantity' => 1,
                ),
              ),
              'trader_exp' => 1,
              'max_uses' => 12,
              'reward_exp' => true,
            ),
            3 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 9,
                  'price_multiplier' => 0.2,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:iron_chestplate',
                  'quantity' => 1,
                ),
              ),
              'trader_exp' => 1,
              'max_uses' => 12,
              'reward_exp' => true,
            ),
          ),
        ),
      ),
    ),
    1 => 
    array (
      'total_exp_required' => 10,
      'groups' => 
      array (
        0 => 
        array (
          'num_to_select' => 1,
          'trades' => 
          array (
            0 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:iron_ingot',
                  'quantity' => 4,
                  'price_multiplier' => 0.05,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                ),
              ),
              'trader_exp' => 10,
              'max_uses' => 12,
              'reward_exp' => true,
            ),
          ),
        ),
        1 => 
        array (
          'num_to_select' => 1,
          'trades' => 
          array (
            0 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 36,
                  'price_multiplier' => 0.2,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:bell',
                  'quantity' => 1,
                ),
              ),
              'trader_exp' => 5,
              'max_uses' => 12,
              'reward_exp' => true,
            ),
            1 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                  'price_multiplier' => 0.2,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:chainmail_boots',
                  'quantity' => 1,
                ),
              ),
              'trader_exp' => 5,
              'max_uses' => 12,
              'reward_exp' => true,
            ),
            2 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 3,
                  'price_multiplier' => 0.2,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:chainmail_leggings',
                  'quantity' => 1,
                ),
              ),
              'trader_exp' => 5,
              'max_uses' => 12,
              'reward_exp' => true,
            ),
          ),
        ),
      ),
    ),
    2 => 
    array (
      'total_exp_required' => 70,
      'groups' => 
      array (
        0 => 
        array (
          'num_to_select' => 1,
          'trades' => 
          array (
            0 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:bucket:10',
                  'quantity' => 1,
                  'price_multiplier' => 0.05,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                ),
              ),
              'trader_exp' => 20,
              'max_uses' => 12,
              'reward_exp' => true,
            ),
          ),
        ),
        1 => 
        array (
          'num_to_select' => 1,
          'trades' => 
          array (
            0 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:diamond',
                  'quantity' => 1,
                  'price_multiplier' => 0.05,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                ),
              ),
              'trader_exp' => 20,
              'max_uses' => 12,
              'reward_exp' => true,
            ),
          ),
        ),
        2 => 
        array (
          'num_to_select' => 1,
          'trades' => 
          array (
            0 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                  'price_multiplier' => 0.2,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:chainmail_helmet',
                  'quantity' => 1,
                ),
              ),
              'trader_exp' => 10,
              'max_uses' => 12,
              'reward_exp' => true,
            ),
            1 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 4,
                  'price_multiplier' => 0.2,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:chainmail_chestplate',
                  'quantity' => 1,
                ),
              ),
              'trader_exp' => 10,
              'max_uses' => 12,
              'reward_exp' => true,
            ),
            2 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 5,
                  'price_multiplier' => 0.2,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:shield',
                  'quantity' => 1,
                ),
              ),
              'trader_exp' => 10,
              'max_uses' => 12,
              'reward_exp' => true,
            ),
          ),
        ),
      ),
    ),
    3 => 
    array (
      'total_exp_required' => 150,
      'groups' => 
      array (
        0 => 
        array (
          'num_to_select' => 1,
          'trades' => 
          array (
            0 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 14,
                  'price_multiplier' => 0.2,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:diamond_leggings',
                  'quantity' => 1,
                  'functions' => 
                  array (
                    0 => 
                    array (
                      'function' => 'enchant_with_levels',
                      'treasure' => false,
                      'levels' => 
                      array (
                        'min' => 5,
                        'max' => 19,
                      ),
                    ),
                  ),
                ),
              ),
              'trader_exp' => 15,
              'max_uses' => 3,
              'reward_exp' => true,
            ),
            1 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 8,
                  'price_multiplier' => 0.2,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:diamond_boots',
                  'quantity' => 1,
                  'functions' => 
                  array (
                    0 => 
                    array (
                      'function' => 'enchant_with_levels',
                      'treasure' => false,
                      'levels' => 
                      array (
                        'min' => 5,
                        'max' => 19,
                      ),
                    ),
                  ),
                ),
              ),
              'trader_exp' => 15,
              'max_uses' => 3,
              'reward_exp' => true,
            ),
          ),
        ),
      ),
    ),
    4 => 
    array (
      'total_exp_required' => 250,
      'groups' => 
      array (
        0 => 
        array (
          'num_to_select' => 1,
          'trades' => 
          array (
            0 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 8,
                  'price_multiplier' => 0.2,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:diamond_helmet',
                  'quantity' => 1,
                  'functions' => 
                  array (
                    0 => 
                    array (
                      'function' => 'enchant_with_levels',
                      'treasure' => false,
                      'levels' => 
                      array (
                        'min' => 5,
                        'max' => 19,
                      ),
                    ),
                  ),
                ),
              ),
              'trader_exp' => 30,
              'max_uses' => 3,
              'reward_exp' => true,
            ),
            1 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 16,
                  'price_multiplier' => 0.2,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:diamond_chestplate',
                  'quantity' => 1,
                  'functions' => 
                  array (
                    0 => 
                    array (
                      'function' => 'enchant_with_levels',
                      'treasure' => false,
                      'levels' => 
                      array (
                        'min' => 5,
                        'max' => 19,
                      ),
                    ),
                  ),
                ),
              ),
              'trader_exp' => 30,
              'max_uses' => 3,
              'reward_exp' => true,
            ),
          ),
        ),
      ),
    ),
  ),
),
			'butcher_trades.json' => array (
  'format_version' => '1.26.10',
  'tiers' => 
  array (
    0 => 
    array (
      'groups' => 
      array (
        0 => 
        array (
          'num_to_select' => 1,
          'trades' => 
          array (
            0 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'choice' => 
                  array (
                    0 => 
                    array (
                      'quantity' => 
                      array (
                        'min' => 14,
                        'max' => 14,
                      ),
                      'item' => 'minecraft:chicken',
                      'functions' => 
                      array (
                      ),
                      'price_multiplier' => 0.05,
                    ),
                  ),
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'choice' => 
                  array (
                    0 => 
                    array (
                      'item' => 'minecraft:emerald',
                      'functions' => 
                      array (
                      ),
                    ),
                  ),
                ),
              ),
              'trader_exp' => 2,
              'max_uses' => 16,
            ),
            1 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'choice' => 
                  array (
                    0 => 
                    array (
                      'quantity' => 
                      array (
                        'min' => 4,
                        'max' => 4,
                      ),
                      'item' => 'minecraft:rabbit',
                      'functions' => 
                      array (
                      ),
                      'price_multiplier' => 0.05,
                    ),
                  ),
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'choice' => 
                  array (
                    0 => 
                    array (
                      'item' => 'minecraft:emerald',
                      'functions' => 
                      array (
                      ),
                    ),
                  ),
                ),
              ),
              'trader_exp' => 2,
              'max_uses' => 16,
            ),
            2 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'choice' => 
                  array (
                    0 => 
                    array (
                      'quantity' => 
                      array (
                        'min' => 7,
                        'max' => 7,
                      ),
                      'item' => 'minecraft:porkchop',
                      'functions' => 
                      array (
                      ),
                      'price_multiplier' => 0.05,
                    ),
                  ),
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'choice' => 
                  array (
                    0 => 
                    array (
                      'item' => 'minecraft:emerald',
                      'functions' => 
                      array (
                      ),
                    ),
                  ),
                ),
              ),
              'trader_exp' => 2,
              'max_uses' => 16,
            ),
          ),
        ),
        1 => 
        array (
          'num_to_select' => 1,
          'trades' => 
          array (
            0 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'choice' => 
                  array (
                    0 => 
                    array (
                      'item' => 'minecraft:emerald',
                      'functions' => 
                      array (
                      ),
                      'price_multiplier' => 0.05,
                    ),
                  ),
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'choice' => 
                  array (
                    0 => 
                    array (
                      'item' => 'minecraft:rabbit_stew',
                      'functions' => 
                      array (
                      ),
                    ),
                  ),
                ),
              ),
              'max_uses' => 12,
            ),
          ),
        ),
      ),
      'total_exp_required' => 0,
    ),
    1 => 
    array (
      'groups' => 
      array (
        0 => 
        array (
          'num_to_select' => 1,
          'trades' => 
          array (
            0 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'choice' => 
                  array (
                    0 => 
                    array (
                      'quantity' => 
                      array (
                        'min' => 15,
                        'max' => 15,
                      ),
                      'item' => 'minecraft:coal:0',
                      'functions' => 
                      array (
                      ),
                      'price_multiplier' => 0.05,
                    ),
                  ),
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'choice' => 
                  array (
                    0 => 
                    array (
                      'item' => 'minecraft:emerald',
                      'functions' => 
                      array (
                      ),
                    ),
                  ),
                ),
              ),
              'trader_exp' => 10,
              'max_uses' => 16,
            ),
          ),
        ),
        1 => 
        array (
          'num_to_select' => 1,
          'trades' => 
          array (
            0 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'choice' => 
                  array (
                    0 => 
                    array (
                      'item' => 'minecraft:emerald',
                      'functions' => 
                      array (
                      ),
                      'price_multiplier' => 0.05,
                    ),
                  ),
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'choice' => 
                  array (
                    0 => 
                    array (
                      'quantity' => 
                      array (
                        'min' => 8,
                        'max' => 8,
                      ),
                      'item' => 'minecraft:cooked_chicken',
                      'functions' => 
                      array (
                      ),
                    ),
                  ),
                ),
              ),
              'trader_exp' => 5,
              'max_uses' => 16,
            ),
            1 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'choice' => 
                  array (
                    0 => 
                    array (
                      'item' => 'minecraft:emerald',
                      'functions' => 
                      array (
                      ),
                      'price_multiplier' => 0.05,
                    ),
                  ),
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'choice' => 
                  array (
                    0 => 
                    array (
                      'quantity' => 
                      array (
                        'min' => 5,
                        'max' => 5,
                      ),
                      'item' => 'minecraft:cooked_porkchop',
                      'functions' => 
                      array (
                      ),
                    ),
                  ),
                ),
              ),
              'trader_exp' => 5,
              'max_uses' => 16,
            ),
          ),
        ),
      ),
      'total_exp_required' => 10,
    ),
    2 => 
    array (
      'groups' => 
      array (
        0 => 
        array (
          'num_to_select' => 1,
          'trades' => 
          array (
            0 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'choice' => 
                  array (
                    0 => 
                    array (
                      'quantity' => 
                      array (
                        'min' => 10,
                        'max' => 10,
                      ),
                      'item' => 'minecraft:beef',
                      'functions' => 
                      array (
                      ),
                      'price_multiplier' => 0.05,
                    ),
                  ),
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'choice' => 
                  array (
                    0 => 
                    array (
                      'item' => 'minecraft:emerald',
                      'functions' => 
                      array (
                      ),
                    ),
                  ),
                ),
              ),
              'trader_exp' => 20,
              'max_uses' => 16,
            ),
            1 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'choice' => 
                  array (
                    0 => 
                    array (
                      'quantity' => 
                      array (
                        'min' => 7,
                        'max' => 7,
                      ),
                      'item' => 'minecraft:muttonraw',
                      'functions' => 
                      array (
                      ),
                      'price_multiplier' => 0.05,
                    ),
                  ),
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'choice' => 
                  array (
                    0 => 
                    array (
                      'item' => 'minecraft:emerald',
                      'functions' => 
                      array (
                      ),
                    ),
                  ),
                ),
              ),
              'trader_exp' => 20,
              'max_uses' => 16,
            ),
          ),
        ),
      ),
      'total_exp_required' => 70,
    ),
    3 => 
    array (
      'groups' => 
      array (
        0 => 
        array (
          'num_to_select' => 1,
          'trades' => 
          array (
            0 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'choice' => 
                  array (
                    0 => 
                    array (
                      'quantity' => 
                      array (
                        'min' => 10,
                        'max' => 10,
                      ),
                      'item' => 'minecraft:dried_kelp_block',
                      'functions' => 
                      array (
                      ),
                      'price_multiplier' => 0.05,
                    ),
                  ),
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'choice' => 
                  array (
                    0 => 
                    array (
                      'item' => 'minecraft:emerald',
                      'functions' => 
                      array (
                      ),
                    ),
                  ),
                ),
              ),
              'trader_exp' => 30,
              'max_uses' => 12,
            ),
          ),
        ),
      ),
      'total_exp_required' => 150,
    ),
    4 => 
    array (
      'groups' => 
      array (
        0 => 
        array (
          'num_to_select' => 1,
          'trades' => 
          array (
            0 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'choice' => 
                  array (
                    0 => 
                    array (
                      'quantity' => 
                      array (
                        'min' => 10,
                        'max' => 10,
                      ),
                      'item' => 'minecraft:sweet_berries',
                      'functions' => 
                      array (
                      ),
                      'price_multiplier' => 0.05,
                    ),
                  ),
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'choice' => 
                  array (
                    0 => 
                    array (
                      'item' => 'minecraft:emerald',
                      'functions' => 
                      array (
                      ),
                    ),
                  ),
                ),
              ),
              'trader_exp' => 30,
              'max_uses' => 12,
            ),
          ),
        ),
      ),
      'total_exp_required' => 250,
    ),
  ),
),
			'cartographer_trades.json' => array (
  'tiers' => 
  array (
    0 => 
    array (
      'total_exp_required' => 0,
      'groups' => 
      array (
        0 => 
        array (
          'num_to_select' => 2,
          'trades' => 
          array (
            0 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:paper',
                  'quantity' => 24,
                  'price_multiplier' => 0.05,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                ),
              ),
              'trader_exp' => 2,
              'max_uses' => 12,
              'reward_exp' => true,
            ),
            1 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 7,
                  'price_multiplier' => 0.05,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:empty_map',
                  'quantity' => 1,
                ),
              ),
              'trader_exp' => 1,
              'max_uses' => 12,
              'reward_exp' => true,
            ),
          ),
        ),
      ),
    ),
    1 => 
    array (
      'total_exp_required' => 10,
      'groups' => 
      array (
        0 => 
        array (
          'num_to_select' => 2,
          'trades' => 
          array (
            0 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:glass_pane',
                  'quantity' => 11,
                  'price_multiplier' => 0.05,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                ),
              ),
              'trader_exp' => 10,
              'max_uses' => 12,
              'reward_exp' => true,
            ),
            1 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 8,
                  'price_multiplier' => 0.05,
                ),
                1 => 
                array (
                  'item' => 'minecraft:compass',
                  'quantity' => 1,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'choice' => 
                  array (
                    0 => 
                    array (
                      'item' => 'minecraft:map',
                      'quantity' => 1,
                      'functions' => 
                      array (
                        0 => 
                        array (
                          'function' => 'exploration_map',
                          'destination' => 'village_taiga',
                        ),
                      ),
                      'filters' => 
                      array (
                        'all_of' => 
                        array (
                          0 => 
                          array (
                            'test' => 'in_overworld',
                            'subject' => 'self',
                            'value' => true,
                            'operator' => '=',
                          ),
                          1 => 
                          array (
                            'any_of' => 
                            array (
                              0 => 
                              array (
                                'test' => 'is_mark_variant',
                                'subject' => 'self',
                                'value' => 0,
                                'operator' => '=',
                              ),
                              1 => 
                              array (
                                'test' => 'is_mark_variant',
                                'subject' => 'self',
                                'value' => 4,
                                'operator' => '=',
                              ),
                              2 => 
                              array (
                                'test' => 'is_mark_variant',
                                'subject' => 'self',
                                'value' => 5,
                                'operator' => '=',
                              ),
                            ),
                          ),
                        ),
                      ),
                    ),
                  ),
                ),
              ),
              'trader_exp' => 5,
              'max_uses' => 12,
              'reward_exp' => true,
            ),
            2 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 8,
                  'price_multiplier' => 0.05,
                ),
                1 => 
                array (
                  'item' => 'minecraft:compass',
                  'quantity' => 1,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'choice' => 
                  array (
                    0 => 
                    array (
                      'item' => 'minecraft:map',
                      'quantity' => 1,
                      'functions' => 
                      array (
                        0 => 
                        array (
                          'function' => 'exploration_map',
                          'destination' => 'swamp_hut',
                        ),
                      ),
                      'filters' => 
                      array (
                        'all_of' => 
                        array (
                          0 => 
                          array (
                            'test' => 'in_overworld',
                            'subject' => 'self',
                            'value' => true,
                            'operator' => '=',
                          ),
                          1 => 
                          array (
                            'any_of' => 
                            array (
                              0 => 
                              array (
                                'test' => 'is_mark_variant',
                                'subject' => 'self',
                                'value' => 2,
                                'operator' => '=',
                              ),
                              1 => 
                              array (
                                'test' => 'is_mark_variant',
                                'subject' => 'self',
                                'value' => 4,
                                'operator' => '=',
                              ),
                              2 => 
                              array (
                                'test' => 'is_mark_variant',
                                'subject' => 'self',
                                'value' => 6,
                                'operator' => '=',
                              ),
                            ),
                          ),
                        ),
                      ),
                    ),
                  ),
                ),
              ),
              'trader_exp' => 5,
              'max_uses' => 12,
              'reward_exp' => true,
            ),
            3 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 8,
                  'price_multiplier' => 0.05,
                ),
                1 => 
                array (
                  'item' => 'minecraft:compass',
                  'quantity' => 1,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'choice' => 
                  array (
                    0 => 
                    array (
                      'item' => 'minecraft:map',
                      'quantity' => 1,
                      'functions' => 
                      array (
                        0 => 
                        array (
                          'function' => 'exploration_map',
                          'destination' => 'village_snowy',
                        ),
                      ),
                      'filters' => 
                      array (
                        'all_of' => 
                        array (
                          0 => 
                          array (
                            'test' => 'in_overworld',
                            'subject' => 'self',
                            'value' => true,
                            'operator' => '=',
                          ),
                          1 => 
                          array (
                            'any_of' => 
                            array (
                              0 => 
                              array (
                                'test' => 'is_mark_variant',
                                'subject' => 'self',
                                'value' => 5,
                                'operator' => '=',
                              ),
                              1 => 
                              array (
                                'test' => 'is_mark_variant',
                                'subject' => 'self',
                                'value' => 6,
                                'operator' => '=',
                              ),
                            ),
                          ),
                        ),
                      ),
                    ),
                  ),
                ),
              ),
              'trader_exp' => 5,
              'max_uses' => 12,
              'reward_exp' => true,
            ),
            4 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 8,
                  'price_multiplier' => 0.05,
                ),
                1 => 
                array (
                  'item' => 'minecraft:compass',
                  'quantity' => 1,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'choice' => 
                  array (
                    0 => 
                    array (
                      'item' => 'minecraft:map',
                      'quantity' => 1,
                      'functions' => 
                      array (
                        0 => 
                        array (
                          'function' => 'exploration_map',
                          'destination' => 'village_savanna',
                        ),
                      ),
                      'filters' => 
                      array (
                        'all_of' => 
                        array (
                          0 => 
                          array (
                            'test' => 'in_overworld',
                            'subject' => 'self',
                            'value' => true,
                            'operator' => '=',
                          ),
                          1 => 
                          array (
                            'any_of' => 
                            array (
                              0 => 
                              array (
                                'test' => 'is_mark_variant',
                                'subject' => 'self',
                                'value' => 0,
                                'operator' => '=',
                              ),
                              1 => 
                              array (
                                'test' => 'is_mark_variant',
                                'subject' => 'self',
                                'value' => 1,
                                'operator' => '=',
                              ),
                              2 => 
                              array (
                                'test' => 'is_mark_variant',
                                'subject' => 'self',
                                'value' => 2,
                                'operator' => '=',
                              ),
                            ),
                          ),
                        ),
                      ),
                    ),
                  ),
                ),
              ),
              'trader_exp' => 5,
              'max_uses' => 12,
              'reward_exp' => true,
            ),
            5 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 8,
                  'price_multiplier' => 0.05,
                ),
                1 => 
                array (
                  'item' => 'minecraft:compass',
                  'quantity' => 1,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'choice' => 
                  array (
                    0 => 
                    array (
                      'item' => 'minecraft:map',
                      'quantity' => 1,
                      'functions' => 
                      array (
                        0 => 
                        array (
                          'function' => 'exploration_map',
                          'destination' => 'village_plains',
                        ),
                      ),
                      'filters' => 
                      array (
                        'all_of' => 
                        array (
                          0 => 
                          array (
                            'test' => 'in_overworld',
                            'subject' => 'self',
                            'value' => true,
                            'operator' => '=',
                          ),
                          1 => 
                          array (
                            'any_of' => 
                            array (
                              0 => 
                              array (
                                'test' => 'is_mark_variant',
                                'subject' => 'self',
                                'value' => 1,
                                'operator' => '=',
                              ),
                              1 => 
                              array (
                                'test' => 'is_mark_variant',
                                'subject' => 'self',
                                'value' => 3,
                                'operator' => '=',
                              ),
                              2 => 
                              array (
                                'test' => 'is_mark_variant',
                                'subject' => 'self',
                                'value' => 4,
                                'operator' => '=',
                              ),
                              3 => 
                              array (
                                'test' => 'is_mark_variant',
                                'subject' => 'self',
                                'value' => 6,
                                'operator' => '=',
                              ),
                            ),
                          ),
                        ),
                      ),
                    ),
                  ),
                ),
              ),
              'trader_exp' => 5,
              'max_uses' => 12,
              'reward_exp' => true,
            ),
            6 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 8,
                  'price_multiplier' => 0.05,
                ),
                1 => 
                array (
                  'item' => 'minecraft:compass',
                  'quantity' => 1,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'choice' => 
                  array (
                    0 => 
                    array (
                      'item' => 'minecraft:map',
                      'quantity' => 1,
                      'functions' => 
                      array (
                        0 => 
                        array (
                          'function' => 'exploration_map',
                          'destination' => 'jungle_temple',
                        ),
                      ),
                      'filters' => 
                      array (
                        'all_of' => 
                        array (
                          0 => 
                          array (
                            'test' => 'in_overworld',
                            'subject' => 'self',
                            'value' => true,
                            'operator' => '=',
                          ),
                          1 => 
                          array (
                            'any_of' => 
                            array (
                              0 => 
                              array (
                                'test' => 'is_mark_variant',
                                'subject' => 'self',
                                'value' => 1,
                                'operator' => '=',
                              ),
                              1 => 
                              array (
                                'test' => 'is_mark_variant',
                                'subject' => 'self',
                                'value' => 3,
                                'operator' => '=',
                              ),
                              2 => 
                              array (
                                'test' => 'is_mark_variant',
                                'subject' => 'self',
                                'value' => 5,
                                'operator' => '=',
                              ),
                            ),
                          ),
                        ),
                      ),
                    ),
                  ),
                ),
              ),
              'trader_exp' => 5,
              'max_uses' => 12,
              'reward_exp' => true,
            ),
            7 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 8,
                  'price_multiplier' => 0.05,
                ),
                1 => 
                array (
                  'item' => 'minecraft:compass',
                  'quantity' => 1,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'choice' => 
                  array (
                    0 => 
                    array (
                      'item' => 'minecraft:map',
                      'quantity' => 1,
                      'functions' => 
                      array (
                        0 => 
                        array (
                          'function' => 'exploration_map',
                          'destination' => 'village_desert',
                        ),
                      ),
                      'filters' => 
                      array (
                        'all_of' => 
                        array (
                          0 => 
                          array (
                            'test' => 'in_overworld',
                            'subject' => 'self',
                            'value' => true,
                            'operator' => '=',
                          ),
                          1 => 
                          array (
                            'any_of' => 
                            array (
                              0 => 
                              array (
                                'test' => 'is_mark_variant',
                                'subject' => 'self',
                                'value' => 2,
                                'operator' => '=',
                              ),
                              1 => 
                              array (
                                'test' => 'is_mark_variant',
                                'subject' => 'self',
                                'value' => 3,
                                'operator' => '=',
                              ),
                            ),
                          ),
                        ),
                      ),
                    ),
                  ),
                ),
              ),
              'trader_exp' => 5,
              'max_uses' => 12,
              'reward_exp' => true,
            ),
          ),
        ),
      ),
    ),
    2 => 
    array (
      'total_exp_required' => 70,
      'groups' => 
      array (
        0 => 
        array (
          'num_to_select' => 2,
          'trades' => 
          array (
            0 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:compass',
                  'quantity' => 1,
                  'price_multiplier' => 0.05,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                ),
              ),
              'trader_exp' => 20,
              'max_uses' => 12,
              'reward_exp' => true,
            ),
            1 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 13,
                  'price_multiplier' => 0.05,
                ),
                1 => 
                array (
                  'item' => 'minecraft:compass',
                  'quantity' => 1,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:map',
                  'quantity' => 1,
                  'functions' => 
                  array (
                    0 => 
                    array (
                      'function' => 'exploration_map',
                      'destination' => 'monument',
                    ),
                  ),
                  'filters' => 
                  array (
                    'test' => 'in_overworld',
                    'subject' => 'self',
                    'value' => true,
                    'operator' => '=',
                  ),
                ),
              ),
              'trader_exp' => 10,
              'max_uses' => 12,
              'reward_exp' => true,
            ),
            2 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 12,
                  'price_multiplier' => 0.05,
                ),
                1 => 
                array (
                  'item' => 'minecraft:compass',
                  'quantity' => 1,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:map',
                  'quantity' => 1,
                  'functions' => 
                  array (
                    0 => 
                    array (
                      'function' => 'exploration_map',
                      'destination' => 'trial_chambers',
                    ),
                  ),
                  'filters' => 
                  array (
                    'test' => 'in_overworld',
                    'subject' => 'self',
                    'value' => true,
                    'operator' => '=',
                  ),
                ),
              ),
              'trader_exp' => 10,
              'max_uses' => 12,
              'reward_exp' => true,
            ),
          ),
        ),
      ),
    ),
    3 => 
    array (
      'total_exp_required' => 150,
      'groups' => 
      array (
        0 => 
        array (
          'num_to_select' => 2,
          'trades' => 
          array (
            0 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 7,
                  'price_multiplier' => 0.05,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:frame',
                  'quantity' => 1,
                ),
              ),
              'trader_exp' => 15,
              'max_uses' => 12,
              'reward_exp' => true,
            ),
            1 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 2,
                  'price_multiplier' => 0.05,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'choice' => 
                  array (
                    0 => 
                    array (
                      'item' => 'minecraft:banner:4',
                      'quantity' => 1,
                      'filters' => 
                      array (
                        'any_of' => 
                        array (
                          0 => 
                          array (
                            'test' => 'is_mark_variant',
                            'subject' => 'self',
                            'value' => 4,
                            'operator' => '=',
                          ),
                          1 => 
                          array (
                            'test' => 'is_mark_variant',
                            'subject' => 'self',
                            'value' => 6,
                            'operator' => '=',
                          ),
                        ),
                      ),
                    ),
                  ),
                ),
              ),
              'trader_exp' => 15,
              'max_uses' => 12,
              'reward_exp' => true,
            ),
            2 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 2,
                  'price_multiplier' => 0.05,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'choice' => 
                  array (
                    0 => 
                    array (
                      'item' => 'minecraft:banner:15',
                      'quantity' => 1,
                      'filters' => 
                      array (
                        'any_of' => 
                        array (
                          0 => 
                          array (
                            'test' => 'is_mark_variant',
                            'subject' => 'self',
                            'value' => 0,
                            'operator' => '=',
                          ),
                          1 => 
                          array (
                            'test' => 'is_mark_variant',
                            'subject' => 'self',
                            'value' => 4,
                            'operator' => '=',
                          ),
                        ),
                      ),
                    ),
                  ),
                ),
              ),
              'trader_exp' => 15,
              'max_uses' => 12,
              'reward_exp' => true,
            ),
            3 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 2,
                  'price_multiplier' => 0.05,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'choice' => 
                  array (
                    0 => 
                    array (
                      'item' => 'minecraft:banner:1',
                      'quantity' => 1,
                      'filters' => 
                      array (
                        'any_of' => 
                        array (
                          0 => 
                          array (
                            'test' => 'is_mark_variant',
                            'subject' => 'self',
                            'value' => 3,
                            'operator' => '=',
                          ),
                          1 => 
                          array (
                            'test' => 'is_mark_variant',
                            'subject' => 'self',
                            'value' => 4,
                            'operator' => '=',
                          ),
                        ),
                      ),
                    ),
                  ),
                ),
              ),
              'trader_exp' => 15,
              'max_uses' => 12,
              'reward_exp' => true,
            ),
            4 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 2,
                  'price_multiplier' => 0.05,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'choice' => 
                  array (
                    0 => 
                    array (
                      'item' => 'minecraft:banner:2',
                      'quantity' => 1,
                      'filters' => 
                      array (
                        'any_of' => 
                        array (
                          0 => 
                          array (
                            'test' => 'is_mark_variant',
                            'subject' => 'self',
                            'value' => 1,
                            'operator' => '=',
                          ),
                          1 => 
                          array (
                            'test' => 'is_mark_variant',
                            'subject' => 'self',
                            'value' => 2,
                            'operator' => '=',
                          ),
                          2 => 
                          array (
                            'test' => 'is_mark_variant',
                            'subject' => 'self',
                            'value' => 3,
                            'operator' => '=',
                          ),
                        ),
                      ),
                    ),
                  ),
                ),
              ),
              'trader_exp' => 15,
              'max_uses' => 12,
              'reward_exp' => true,
            ),
            5 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 2,
                  'price_multiplier' => 0.05,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'choice' => 
                  array (
                    0 => 
                    array (
                      'item' => 'minecraft:banner:10',
                      'quantity' => 1,
                      'filters' => 
                      array (
                        'any_of' => 
                        array (
                          0 => 
                          array (
                            'test' => 'is_mark_variant',
                            'subject' => 'self',
                            'value' => 1,
                            'operator' => '=',
                          ),
                          1 => 
                          array (
                            'test' => 'is_mark_variant',
                            'subject' => 'self',
                            'value' => 6,
                            'operator' => '=',
                          ),
                        ),
                      ),
                    ),
                  ),
                ),
              ),
              'trader_exp' => 15,
              'max_uses' => 12,
              'reward_exp' => true,
            ),
            6 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 2,
                  'price_multiplier' => 0.05,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'choice' => 
                  array (
                    0 => 
                    array (
                      'item' => 'minecraft:banner:5',
                      'quantity' => 1,
                      'filters' => 
                      array (
                        'any_of' => 
                        array (
                          0 => 
                          array (
                            'test' => 'is_mark_variant',
                            'subject' => 'self',
                            'value' => 5,
                            'operator' => '=',
                          ),
                          1 => 
                          array (
                            'test' => 'is_mark_variant',
                            'subject' => 'self',
                            'value' => 6,
                            'operator' => '=',
                          ),
                        ),
                      ),
                    ),
                  ),
                ),
              ),
              'trader_exp' => 15,
              'max_uses' => 12,
              'reward_exp' => true,
            ),
            7 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 2,
                  'price_multiplier' => 0.05,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'choice' => 
                  array (
                    0 => 
                    array (
                      'item' => 'minecraft:banner:6',
                      'quantity' => 1,
                      'filters' => 
                      array (
                        'any_of' => 
                        array (
                          0 => 
                          array (
                            'test' => 'is_mark_variant',
                            'subject' => 'self',
                            'value' => 1,
                            'operator' => '=',
                          ),
                          1 => 
                          array (
                            'test' => 'is_mark_variant',
                            'subject' => 'self',
                            'value' => 4,
                            'operator' => '=',
                          ),
                        ),
                      ),
                    ),
                  ),
                ),
              ),
              'trader_exp' => 15,
              'max_uses' => 12,
              'reward_exp' => true,
            ),
            8 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 2,
                  'price_multiplier' => 0.05,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'choice' => 
                  array (
                    0 => 
                    array (
                      'item' => 'minecraft:banner:11',
                      'quantity' => 1,
                      'filters' => 
                      array (
                        'any_of' => 
                        array (
                          0 => 
                          array (
                            'test' => 'is_mark_variant',
                            'subject' => 'self',
                            'value' => 0,
                            'operator' => '=',
                          ),
                          1 => 
                          array (
                            'test' => 'is_mark_variant',
                            'subject' => 'self',
                            'value' => 2,
                            'operator' => '=',
                          ),
                        ),
                      ),
                    ),
                  ),
                ),
              ),
              'trader_exp' => 15,
              'max_uses' => 12,
              'reward_exp' => true,
            ),
            9 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 2,
                  'price_multiplier' => 0.05,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'choice' => 
                  array (
                    0 => 
                    array (
                      'item' => 'minecraft:banner:14',
                      'quantity' => 1,
                      'filters' => 
                      array (
                        'any_of' => 
                        array (
                          0 => 
                          array (
                            'test' => 'is_mark_variant',
                            'subject' => 'self',
                            'value' => 1,
                            'operator' => '=',
                          ),
                          1 => 
                          array (
                            'test' => 'is_mark_variant',
                            'subject' => 'self',
                            'value' => 3,
                            'operator' => '=',
                          ),
                        ),
                      ),
                    ),
                  ),
                ),
              ),
              'trader_exp' => 15,
              'max_uses' => 12,
              'reward_exp' => true,
            ),
            10 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 2,
                  'price_multiplier' => 0.05,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'choice' => 
                  array (
                    0 => 
                    array (
                      'item' => 'minecraft:banner:3',
                      'quantity' => 1,
                      'filters' => 
                      array (
                        'any_of' => 
                        array (
                          0 => 
                          array (
                            'test' => 'is_mark_variant',
                            'subject' => 'self',
                            'value' => 0,
                            'operator' => '=',
                          ),
                          1 => 
                          array (
                            'test' => 'is_mark_variant',
                            'subject' => 'self',
                            'value' => 2,
                            'operator' => '=',
                          ),
                        ),
                      ),
                    ),
                  ),
                ),
              ),
              'trader_exp' => 15,
              'max_uses' => 12,
              'reward_exp' => true,
            ),
            11 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 2,
                  'price_multiplier' => 0.05,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'choice' => 
                  array (
                    0 => 
                    array (
                      'item' => 'minecraft:banner:13',
                      'quantity' => 1,
                      'filters' => 
                      array (
                        'test' => 'is_mark_variant',
                        'subject' => 'self',
                        'value' => 3,
                        'operator' => '=',
                      ),
                    ),
                  ),
                ),
              ),
              'trader_exp' => 15,
              'max_uses' => 12,
              'reward_exp' => true,
            ),
            12 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 2,
                  'price_multiplier' => 0.05,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'choice' => 
                  array (
                    0 => 
                    array (
                      'item' => 'minecraft:banner:12',
                      'quantity' => 1,
                      'filters' => 
                      array (
                        'any_of' => 
                        array (
                          0 => 
                          array (
                            'test' => 'is_mark_variant',
                            'subject' => 'self',
                            'value' => 4,
                            'operator' => '=',
                          ),
                          1 => 
                          array (
                            'test' => 'is_mark_variant',
                            'subject' => 'self',
                            'value' => 5,
                            'operator' => '=',
                          ),
                        ),
                      ),
                    ),
                  ),
                ),
              ),
              'trader_exp' => 15,
              'max_uses' => 12,
              'reward_exp' => true,
            ),
            13 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 2,
                  'price_multiplier' => 0.05,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'choice' => 
                  array (
                    0 => 
                    array (
                      'item' => 'minecraft:banner:9',
                      'quantity' => 1,
                      'filters' => 
                      array (
                        'any_of' => 
                        array (
                          0 => 
                          array (
                            'test' => 'is_mark_variant',
                            'subject' => 'self',
                            'value' => 0,
                            'operator' => '=',
                          ),
                          1 => 
                          array (
                            'test' => 'is_mark_variant',
                            'subject' => 'self',
                            'value' => 6,
                            'operator' => '=',
                          ),
                        ),
                      ),
                    ),
                  ),
                ),
              ),
              'trader_exp' => 15,
              'max_uses' => 12,
              'reward_exp' => true,
            ),
            14 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 2,
                  'price_multiplier' => 0.05,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'choice' => 
                  array (
                    0 => 
                    array (
                      'item' => 'minecraft:banner:8',
                      'quantity' => 1,
                      'filters' => 
                      array (
                        'test' => 'is_mark_variant',
                        'subject' => 'self',
                        'value' => 1,
                        'operator' => '=',
                      ),
                    ),
                  ),
                ),
              ),
              'trader_exp' => 15,
              'max_uses' => 12,
              'reward_exp' => true,
            ),
            15 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 2,
                  'price_multiplier' => 0.05,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'choice' => 
                  array (
                    0 => 
                    array (
                      'item' => 'minecraft:banner',
                      'quantity' => 1,
                      'filters' => 
                      array (
                        'test' => 'is_mark_variant',
                        'subject' => 'self',
                        'value' => 5,
                        'operator' => '=',
                      ),
                    ),
                  ),
                ),
              ),
              'trader_exp' => 15,
              'max_uses' => 12,
              'reward_exp' => true,
            ),
          ),
        ),
      ),
    ),
    4 => 
    array (
      'total_exp_required' => 250,
      'groups' => 
      array (
        0 => 
        array (
          'num_to_select' => 2,
          'trades' => 
          array (
            0 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 8,
                  'price_multiplier' => 0.05,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:globe_banner_pattern',
                  'quantity' => 1,
                ),
              ),
              'trader_exp' => 30,
              'max_uses' => 12,
              'reward_exp' => true,
            ),
            1 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 14,
                  'price_multiplier' => 0.05,
                ),
                1 => 
                array (
                  'item' => 'minecraft:compass',
                  'quantity' => 1,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:map',
                  'quantity' => 1,
                  'functions' => 
                  array (
                    0 => 
                    array (
                      'function' => 'exploration_map',
                      'destination' => 'mansion',
                    ),
                  ),
                  'filters' => 
                  array (
                    'test' => 'in_overworld',
                    'subject' => 'self',
                    'value' => true,
                    'operator' => '=',
                  ),
                ),
              ),
              'trader_exp' => 30,
              'max_uses' => 12,
              'reward_exp' => true,
            ),
          ),
        ),
      ),
    ),
  ),
),
			'cleric_trades.json' => array (
  'format_version' => '1.26.10',
  'tiers' => 
  array (
    0 => 
    array (
      'groups' => 
      array (
        0 => 
        array (
          'num_to_select' => 1,
          'trades' => 
          array (
            0 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'choice' => 
                  array (
                    0 => 
                    array (
                      'quantity' => 
                      array (
                        'min' => 32,
                        'max' => 32,
                      ),
                      'item' => 'minecraft:rotten_flesh',
                      'functions' => 
                      array (
                      ),
                      'price_multiplier' => 0.05,
                    ),
                  ),
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'choice' => 
                  array (
                    0 => 
                    array (
                      'item' => 'minecraft:emerald',
                      'functions' => 
                      array (
                      ),
                    ),
                  ),
                ),
              ),
              'trader_exp' => 2,
              'max_uses' => 16,
            ),
          ),
        ),
        1 => 
        array (
          'num_to_select' => 1,
          'trades' => 
          array (
            0 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'choice' => 
                  array (
                    0 => 
                    array (
                      'item' => 'minecraft:emerald',
                      'functions' => 
                      array (
                      ),
                      'price_multiplier' => 0.05,
                    ),
                  ),
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'choice' => 
                  array (
                    0 => 
                    array (
                      'quantity' => 
                      array (
                        'min' => 2,
                        'max' => 2,
                      ),
                      'item' => 'minecraft:redstone',
                      'functions' => 
                      array (
                      ),
                    ),
                  ),
                ),
              ),
              'max_uses' => 12,
            ),
          ),
        ),
      ),
      'total_exp_required' => 0,
    ),
    1 => 
    array (
      'groups' => 
      array (
        0 => 
        array (
          'num_to_select' => 1,
          'trades' => 
          array (
            0 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'choice' => 
                  array (
                    0 => 
                    array (
                      'quantity' => 
                      array (
                        'min' => 3,
                        'max' => 3,
                      ),
                      'item' => 'minecraft:gold_ingot',
                      'functions' => 
                      array (
                      ),
                      'price_multiplier' => 0.05,
                    ),
                  ),
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'choice' => 
                  array (
                    0 => 
                    array (
                      'item' => 'minecraft:emerald',
                      'functions' => 
                      array (
                      ),
                    ),
                  ),
                ),
              ),
              'trader_exp' => 10,
              'max_uses' => 12,
            ),
          ),
        ),
        1 => 
        array (
          'num_to_select' => 1,
          'trades' => 
          array (
            0 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'choice' => 
                  array (
                    0 => 
                    array (
                      'item' => 'minecraft:emerald',
                      'functions' => 
                      array (
                      ),
                      'price_multiplier' => 0.05,
                    ),
                  ),
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'choice' => 
                  array (
                    0 => 
                    array (
                      'item' => 'minecraft:dye:4',
                      'functions' => 
                      array (
                      ),
                    ),
                  ),
                ),
              ),
              'trader_exp' => 5,
              'max_uses' => 12,
            ),
          ),
        ),
      ),
      'total_exp_required' => 10,
    ),
    2 => 
    array (
      'groups' => 
      array (
        0 => 
        array (
          'num_to_select' => 1,
          'trades' => 
          array (
            0 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'choice' => 
                  array (
                    0 => 
                    array (
                      'quantity' => 
                      array (
                        'min' => 2,
                        'max' => 2,
                      ),
                      'item' => 'minecraft:rabbit_foot',
                      'functions' => 
                      array (
                      ),
                      'price_multiplier' => 0.05,
                    ),
                  ),
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'choice' => 
                  array (
                    0 => 
                    array (
                      'item' => 'minecraft:emerald',
                      'functions' => 
                      array (
                      ),
                    ),
                  ),
                ),
              ),
              'trader_exp' => 20,
              'max_uses' => 12,
            ),
          ),
        ),
        1 => 
        array (
          'num_to_select' => 1,
          'trades' => 
          array (
            0 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'choice' => 
                  array (
                    0 => 
                    array (
                      'quantity' => 
                      array (
                        'min' => 4,
                        'max' => 4,
                      ),
                      'item' => 'minecraft:emerald',
                      'functions' => 
                      array (
                      ),
                      'price_multiplier' => 0.05,
                    ),
                  ),
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'choice' => 
                  array (
                    0 => 
                    array (
                      'item' => 'minecraft:glowstone',
                      'functions' => 
                      array (
                      ),
                    ),
                  ),
                ),
              ),
              'trader_exp' => 10,
              'max_uses' => 12,
            ),
          ),
        ),
      ),
      'total_exp_required' => 70,
    ),
    3 => 
    array (
      'groups' => 
      array (
        0 => 
        array (
          'num_to_select' => 1,
          'trades' => 
          array (
            0 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'choice' => 
                  array (
                    0 => 
                    array (
                      'quantity' => 
                      array (
                        'min' => 4,
                        'max' => 4,
                      ),
                      'item' => 'minecraft:turtle_shell_piece',
                      'functions' => 
                      array (
                      ),
                      'price_multiplier' => 0.05,
                    ),
                  ),
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'choice' => 
                  array (
                    0 => 
                    array (
                      'item' => 'minecraft:emerald',
                      'functions' => 
                      array (
                      ),
                    ),
                  ),
                ),
              ),
              'trader_exp' => 30,
              'max_uses' => 12,
            ),
            1 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'choice' => 
                  array (
                    0 => 
                    array (
                      'quantity' => 
                      array (
                        'min' => 9,
                        'max' => 9,
                      ),
                      'item' => 'minecraft:glass_bottle',
                      'functions' => 
                      array (
                      ),
                      'price_multiplier' => 0.05,
                    ),
                  ),
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'choice' => 
                  array (
                    0 => 
                    array (
                      'item' => 'minecraft:emerald',
                      'functions' => 
                      array (
                      ),
                    ),
                  ),
                ),
              ),
              'trader_exp' => 30,
              'max_uses' => 12,
            ),
          ),
        ),
        1 => 
        array (
          'num_to_select' => 1,
          'trades' => 
          array (
            0 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'choice' => 
                  array (
                    0 => 
                    array (
                      'quantity' => 
                      array (
                        'min' => 5,
                        'max' => 5,
                      ),
                      'item' => 'minecraft:emerald',
                      'functions' => 
                      array (
                      ),
                      'price_multiplier' => 0.05,
                    ),
                  ),
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'choice' => 
                  array (
                    0 => 
                    array (
                      'item' => 'minecraft:ender_pearl',
                      'functions' => 
                      array (
                      ),
                    ),
                  ),
                ),
              ),
              'trader_exp' => 15,
              'max_uses' => 12,
            ),
          ),
        ),
      ),
      'total_exp_required' => 150,
    ),
    4 => 
    array (
      'groups' => 
      array (
        0 => 
        array (
          'num_to_select' => 1,
          'trades' => 
          array (
            0 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'choice' => 
                  array (
                    0 => 
                    array (
                      'quantity' => 
                      array (
                        'min' => 22,
                        'max' => 22,
                      ),
                      'item' => 'minecraft:nether_wart',
                      'functions' => 
                      array (
                      ),
                      'price_multiplier' => 0.05,
                    ),
                  ),
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'choice' => 
                  array (
                    0 => 
                    array (
                      'item' => 'minecraft:emerald',
                      'functions' => 
                      array (
                      ),
                    ),
                  ),
                ),
              ),
              'trader_exp' => 30,
              'max_uses' => 12,
            ),
          ),
        ),
        1 => 
        array (
          'num_to_select' => 1,
          'trades' => 
          array (
            0 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'choice' => 
                  array (
                    0 => 
                    array (
                      'quantity' => 
                      array (
                        'min' => 3,
                        'max' => 3,
                      ),
                      'item' => 'minecraft:emerald',
                      'functions' => 
                      array (
                      ),
                      'price_multiplier' => 0.05,
                    ),
                  ),
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'choice' => 
                  array (
                    0 => 
                    array (
                      'item' => 'minecraft:experience_bottle',
                      'functions' => 
                      array (
                      ),
                    ),
                  ),
                ),
              ),
              'trader_exp' => 30,
              'max_uses' => 12,
            ),
          ),
        ),
      ),
      'total_exp_required' => 250,
    ),
  ),
),
			'farmer_trades.json' => array (
  'tiers' => 
  array (
    0 => 
    array (
      'total_exp_required' => 0,
      'groups' => 
      array (
        0 => 
        array (
          'num_to_select' => 1,
          'trades' => 
          array (
            0 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:wheat',
                  'quantity' => 20,
                  'price_multiplier' => 0.05,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                ),
              ),
              'trader_exp' => 2,
              'max_uses' => 16,
              'reward_exp' => true,
            ),
            1 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:potato',
                  'quantity' => 26,
                  'price_multiplier' => 0.05,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                ),
              ),
              'trader_exp' => 2,
              'max_uses' => 16,
              'reward_exp' => true,
            ),
            2 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:beetroot',
                  'quantity' => 15,
                  'price_multiplier' => 0.05,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                ),
              ),
              'trader_exp' => 2,
              'max_uses' => 16,
              'reward_exp' => true,
            ),
            3 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:carrot',
                  'quantity' => 22,
                  'price_multiplier' => 0.05,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                ),
              ),
              'trader_exp' => 2,
              'max_uses' => 16,
              'reward_exp' => true,
            ),
          ),
        ),
        1 => 
        array (
          'num_to_select' => 1,
          'trades' => 
          array (
            0 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                  'price_multiplier' => 0.05,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:bread',
                  'quantity' => 6,
                ),
              ),
              'trader_exp' => 1,
              'max_uses' => 16,
              'reward_exp' => true,
            ),
          ),
        ),
      ),
    ),
    1 => 
    array (
      'total_exp_required' => 10,
      'groups' => 
      array (
        0 => 
        array (
          'num_to_select' => 1,
          'trades' => 
          array (
            0 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:pumpkin',
                  'quantity' => 6,
                  'price_multiplier' => 0.05,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                ),
              ),
              'trader_exp' => 10,
              'max_uses' => 12,
              'reward_exp' => true,
            ),
          ),
        ),
        1 => 
        array (
          'num_to_select' => 1,
          'trades' => 
          array (
            0 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                  'price_multiplier' => 0.05,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:pumpkin_pie',
                  'quantity' => 4,
                ),
              ),
              'trader_exp' => 5,
              'max_uses' => 12,
              'reward_exp' => true,
            ),
            1 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                  'price_multiplier' => 0.05,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:apple',
                  'quantity' => 4,
                ),
              ),
              'trader_exp' => 5,
              'max_uses' => 16,
              'reward_exp' => true,
            ),
          ),
        ),
      ),
    ),
    2 => 
    array (
      'total_exp_required' => 70,
      'groups' => 
      array (
        0 => 
        array (
          'num_to_select' => 1,
          'trades' => 
          array (
            0 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:melon_block',
                  'quantity' => 4,
                  'price_multiplier' => 0.05,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                ),
              ),
              'trader_exp' => 20,
              'max_uses' => 12,
              'reward_exp' => true,
            ),
          ),
        ),
        1 => 
        array (
          'num_to_select' => 1,
          'trades' => 
          array (
            0 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 3,
                  'price_multiplier' => 0.05,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:cookie',
                  'quantity' => 18,
                ),
              ),
              'trader_exp' => 10,
              'max_uses' => 12,
              'reward_exp' => true,
            ),
          ),
        ),
      ),
    ),
    3 => 
    array (
      'total_exp_required' => 150,
      'groups' => 
      array (
        0 => 
        array (
          'num_to_select' => 1,
          'trades' => 
          array (
            0 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                  'price_multiplier' => 0.05,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:suspicious_stew:0',
                  'quantity' => 1,
                  'functions' => 
                  array (
                    0 => 
                    array (
                      'function' => 'random_aux_value',
                      'values' => 
                      array (
                        'min' => 0,
                        'max' => 5,
                      ),
                    ),
                  ),
                ),
              ),
              'trader_exp' => 15,
              'max_uses' => 12,
              'reward_exp' => true,
            ),
          ),
        ),
        1 => 
        array (
          'num_to_select' => 1,
          'trades' => 
          array (
            0 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                  'price_multiplier' => 0.05,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:cake',
                  'quantity' => 1,
                ),
              ),
              'trader_exp' => 15,
              'max_uses' => 12,
              'reward_exp' => true,
            ),
          ),
        ),
      ),
    ),
    4 => 
    array (
      'total_exp_required' => 250,
      'groups' => 
      array (
        0 => 
        array (
          'num_to_select' => 1,
          'trades' => 
          array (
            0 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 3,
                  'price_multiplier' => 0.05,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:golden_carrot',
                  'quantity' => 3,
                ),
              ),
              'trader_exp' => 30,
              'max_uses' => 12,
              'reward_exp' => true,
            ),
            1 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 4,
                  'price_multiplier' => 0.05,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:speckled_melon',
                  'quantity' => 3,
                ),
              ),
              'trader_exp' => 30,
              'max_uses' => 12,
              'reward_exp' => true,
            ),
          ),
        ),
      ),
    ),
  ),
),
			'fisherman_trades.json' => array (
  'format_version' => '1.18.10',
  'tiers' => 
  array (
    0 => 
    array (
      'total_exp_required' => 0,
      'groups' => 
      array (
        0 => 
        array (
          'num_to_select' => 1,
          'trades' => 
          array (
            0 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:string',
                  'quantity' => 20,
                  'price_multiplier' => 0.05,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                ),
              ),
              'trader_exp' => 2,
              'max_uses' => 16,
              'reward_exp' => true,
            ),
            1 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:coal:0',
                  'quantity' => 10,
                  'price_multiplier' => 0.05,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                ),
              ),
              'trader_exp' => 2,
              'max_uses' => 16,
              'reward_exp' => true,
            ),
          ),
        ),
        1 => 
        array (
          'num_to_select' => 1,
          'trades' => 
          array (
            0 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 3,
                  'price_multiplier' => 0.05,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:bucket:2',
                  'quantity' => 1,
                ),
              ),
              'trader_exp' => 1,
              'max_uses' => 16,
              'reward_exp' => true,
            ),
            1 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                  'price_multiplier' => 0.05,
                ),
                1 => 
                array (
                  'item' => 'minecraft:fish',
                  'quantity' => 6,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:cooked_fish',
                  'quantity' => 6,
                ),
              ),
              'trader_exp' => 1,
              'max_uses' => 16,
              'reward_exp' => true,
            ),
          ),
        ),
      ),
    ),
    1 => 
    array (
      'total_exp_required' => 10,
      'groups' => 
      array (
        0 => 
        array (
          'num_to_select' => 1,
          'trades' => 
          array (
            0 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:fish',
                  'quantity' => 15,
                  'price_multiplier' => 0.05,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                ),
              ),
              'trader_exp' => 10,
              'max_uses' => 16,
              'reward_exp' => true,
            ),
          ),
        ),
        1 => 
        array (
          'num_to_select' => 1,
          'trades' => 
          array (
            0 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 2,
                  'price_multiplier' => 0.05,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:campfire',
                  'quantity' => 1,
                ),
              ),
              'trader_exp' => 5,
              'max_uses' => 12,
              'reward_exp' => true,
            ),
            1 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                  'price_multiplier' => 0.05,
                ),
                1 => 
                array (
                  'item' => 'minecraft:salmon',
                  'quantity' => 6,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:cooked_salmon',
                  'quantity' => 6,
                ),
              ),
              'trader_exp' => 5,
              'max_uses' => 16,
              'reward_exp' => true,
            ),
          ),
        ),
      ),
    ),
    2 => 
    array (
      'total_exp_required' => 70,
      'groups' => 
      array (
        0 => 
        array (
          'num_to_select' => 1,
          'trades' => 
          array (
            0 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:salmon',
                  'quantity' => 13,
                  'price_multiplier' => 0.05,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                ),
              ),
              'trader_exp' => 20,
              'max_uses' => 16,
              'reward_exp' => true,
            ),
          ),
        ),
        1 => 
        array (
          'num_to_select' => 1,
          'trades' => 
          array (
            0 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 3,
                  'price_multiplier' => 0.2,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:fishing_rod',
                  'quantity' => 1,
                  'functions' => 
                  array (
                    0 => 
                    array (
                      'function' => 'enchant_with_levels',
                      'treasure' => false,
                      'levels' => 
                      array (
                        'min' => 5,
                        'max' => 19,
                      ),
                    ),
                  ),
                ),
              ),
              'trader_exp' => 10,
              'max_uses' => 3,
              'reward_exp' => true,
            ),
          ),
        ),
      ),
    ),
    3 => 
    array (
      'total_exp_required' => 150,
      'groups' => 
      array (
        0 => 
        array (
          'num_to_select' => 1,
          'trades' => 
          array (
            0 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:clownfish',
                  'quantity' => 6,
                  'price_multiplier' => 0.05,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                ),
              ),
              'trader_exp' => 30,
              'max_uses' => 12,
              'reward_exp' => true,
            ),
          ),
        ),
      ),
    ),
    4 => 
    array (
      'total_exp_required' => 250,
      'groups' => 
      array (
        0 => 
        array (
          'trades' => 
          array (
            0 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:pufferfish',
                  'quantity' => 4,
                  'price_multiplier' => 0.05,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                ),
              ),
              'trader_exp' => 30,
              'max_uses' => 12,
              'reward_exp' => true,
            ),
            1 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'choice' => 
                  array (
                    0 => 
                    array (
                      'item' => 'minecraft:oak_boat',
                      'quantity' => 1,
                      'price_multiplier' => 0.05,
                      'filters' => 
                      array (
                        'test' => 'is_mark_variant',
                        'subject' => 'self',
                        'value' => 0,
                        'operator' => '=',
                      ),
                    ),
                    1 => 
                    array (
                      'item' => 'minecraft:spruce_boat',
                      'quantity' => 1,
                      'price_multiplier' => 0.05,
                      'filters' => 
                      array (
                        'any_of' => 
                        array (
                          0 => 
                          array (
                            'test' => 'is_mark_variant',
                            'subject' => 'self',
                            'value' => 4,
                            'operator' => '=',
                          ),
                          1 => 
                          array (
                            'test' => 'is_mark_variant',
                            'subject' => 'self',
                            'value' => 6,
                            'operator' => '=',
                          ),
                        ),
                      ),
                    ),
                    2 => 
                    array (
                      'item' => 'minecraft:jungle_boat',
                      'quantity' => 1,
                      'price_multiplier' => 0.05,
                      'filters' => 
                      array (
                        'any_of' => 
                        array (
                          0 => 
                          array (
                            'test' => 'is_mark_variant',
                            'subject' => 'self',
                            'value' => 1,
                            'operator' => '=',
                          ),
                          1 => 
                          array (
                            'test' => 'is_mark_variant',
                            'subject' => 'self',
                            'value' => 2,
                            'operator' => '=',
                          ),
                        ),
                      ),
                    ),
                    3 => 
                    array (
                      'item' => 'minecraft:acacia_boat',
                      'quantity' => 1,
                      'price_multiplier' => 0.05,
                      'filters' => 
                      array (
                        'test' => 'is_mark_variant',
                        'subject' => 'self',
                        'value' => 3,
                        'operator' => '=',
                      ),
                    ),
                    4 => 
                    array (
                      'item' => 'minecraft:dark_oak_boat',
                      'quantity' => 1,
                      'price_multiplier' => 0.05,
                      'filters' => 
                      array (
                        'test' => 'is_mark_variant',
                        'subject' => 'self',
                        'value' => 5,
                        'operator' => '=',
                      ),
                    ),
                  ),
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                ),
              ),
              'trader_exp' => 30,
              'max_uses' => 12,
              'reward_exp' => true,
            ),
          ),
        ),
      ),
    ),
  ),
),
			'fletcher_trades.json' => array (
  'tiers' => 
  array (
    0 => 
    array (
      'total_exp_required' => 0,
      'groups' => 
      array (
        0 => 
        array (
          'num_to_select' => 1,
          'trades' => 
          array (
            0 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:stick',
                  'quantity' => 32,
                  'price_multiplier' => 0.05,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                ),
              ),
              'trader_exp' => 2,
              'max_uses' => 16,
              'reward_exp' => true,
            ),
          ),
        ),
        1 => 
        array (
          'num_to_select' => 1,
          'trades' => 
          array (
            0 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                  'price_multiplier' => 0.05,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:arrow',
                  'quantity' => 16,
                ),
              ),
              'trader_exp' => 1,
              'max_uses' => 12,
              'reward_exp' => true,
            ),
            1 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                  'price_multiplier' => 0.05,
                ),
                1 => 
                array (
                  'item' => 'minecraft:gravel',
                  'quantity' => 10,
                  'price_multiplier' => 0.05,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:flint',
                  'quantity' => 10,
                ),
              ),
              'trader_exp' => 1,
              'max_uses' => 12,
              'reward_exp' => true,
            ),
          ),
        ),
      ),
    ),
    1 => 
    array (
      'total_exp_required' => 10,
      'groups' => 
      array (
        0 => 
        array (
          'num_to_select' => 1,
          'trades' => 
          array (
            0 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:flint',
                  'quantity' => 26,
                  'price_multiplier' => 0.05,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                ),
              ),
              'trader_exp' => 10,
              'max_uses' => 12,
              'reward_exp' => true,
            ),
          ),
        ),
        1 => 
        array (
          'num_to_select' => 1,
          'trades' => 
          array (
            0 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 2,
                  'price_multiplier' => 0.05,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:bow',
                  'quantity' => 1,
                ),
              ),
              'trader_exp' => 5,
              'max_uses' => 12,
              'reward_exp' => true,
            ),
          ),
        ),
      ),
    ),
    2 => 
    array (
      'total_exp_required' => 70,
      'groups' => 
      array (
        0 => 
        array (
          'num_to_select' => 1,
          'trades' => 
          array (
            0 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:string',
                  'quantity' => 14,
                  'price_multiplier' => 0.05,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                ),
              ),
              'trader_exp' => 20,
              'max_uses' => 16,
              'reward_exp' => true,
            ),
          ),
        ),
        1 => 
        array (
          'num_to_select' => 1,
          'trades' => 
          array (
            0 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 3,
                  'price_multiplier' => 0.05,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:crossbow',
                  'quantity' => 1,
                ),
              ),
              'trader_exp' => 10,
              'max_uses' => 12,
              'reward_exp' => true,
            ),
          ),
        ),
      ),
    ),
    3 => 
    array (
      'total_exp_required' => 150,
      'groups' => 
      array (
        0 => 
        array (
          'num_to_select' => 1,
          'trades' => 
          array (
            0 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:feather',
                  'quantity' => 24,
                  'price_multiplier' => 0.05,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                ),
              ),
              'trader_exp' => 30,
              'max_uses' => 16,
              'reward_exp' => true,
            ),
          ),
        ),
        1 => 
        array (
          'num_to_select' => 1,
          'trades' => 
          array (
            0 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 2,
                  'price_multiplier' => 0.05,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:bow',
                  'quantity' => 1,
                  'functions' => 
                  array (
                    0 => 
                    array (
                      'function' => 'enchant_with_levels',
                      'treasure' => false,
                      'levels' => 
                      array (
                        'min' => 5,
                        'max' => 19,
                      ),
                    ),
                  ),
                ),
              ),
              'trader_exp' => 15,
              'max_uses' => 3,
              'reward_exp' => true,
            ),
          ),
        ),
      ),
    ),
    4 => 
    array (
      'total_exp_required' => 250,
      'groups' => 
      array (
        0 => 
        array (
          'num_to_select' => 1,
          'trades' => 
          array (
            0 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:tripwire_hook',
                  'quantity' => 8,
                  'price_multiplier' => 0.05,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                ),
              ),
              'trader_exp' => 30,
              'max_uses' => 12,
              'reward_exp' => true,
            ),
          ),
        ),
        1 => 
        array (
          'num_to_select' => 1,
          'trades' => 
          array (
            0 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 3,
                  'price_multiplier' => 0.05,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:crossbow',
                  'quantity' => 1,
                  'functions' => 
                  array (
                    0 => 
                    array (
                      'function' => 'enchant_with_levels',
                      'treasure' => false,
                      'levels' => 
                      array (
                        'min' => 5,
                        'max' => 19,
                      ),
                    ),
                  ),
                ),
              ),
              'trader_exp' => 15,
              'max_uses' => 3,
              'reward_exp' => true,
            ),
            1 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 2,
                  'price_multiplier' => 0.05,
                ),
                1 => 
                array (
                  'item' => 'minecraft:arrow',
                  'quantity' => 5,
                  'price_multiplier' => 0.05,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'choice' => 
                  array (
                    0 => 
                    array (
                      'item' => 'minecraft:arrow',
                      'quantity' => 5,
                      'functions' => 
                      array (
                        0 => 
                        array (
                          'function' => 'set_potion',
                          'id' => 'nightvision',
                        ),
                      ),
                    ),
                    1 => 
                    array (
                      'item' => 'minecraft:arrow',
                      'quantity' => 5,
                      'functions' => 
                      array (
                        0 => 
                        array (
                          'function' => 'set_potion',
                          'id' => 'invisibility',
                        ),
                      ),
                    ),
                    2 => 
                    array (
                      'item' => 'minecraft:arrow',
                      'quantity' => 5,
                      'functions' => 
                      array (
                        0 => 
                        array (
                          'function' => 'set_potion',
                          'id' => 'leaping',
                        ),
                      ),
                    ),
                    3 => 
                    array (
                      'item' => 'minecraft:arrow',
                      'quantity' => 5,
                      'functions' => 
                      array (
                        0 => 
                        array (
                          'function' => 'set_potion',
                          'id' => 'fire_resistance',
                        ),
                      ),
                    ),
                    4 => 
                    array (
                      'item' => 'minecraft:arrow',
                      'quantity' => 5,
                      'functions' => 
                      array (
                        0 => 
                        array (
                          'function' => 'set_potion',
                          'id' => 'swiftness',
                        ),
                      ),
                    ),
                    5 => 
                    array (
                      'item' => 'minecraft:arrow',
                      'quantity' => 5,
                      'functions' => 
                      array (
                        0 => 
                        array (
                          'function' => 'set_potion',
                          'id' => 'slowness',
                        ),
                      ),
                    ),
                    6 => 
                    array (
                      'item' => 'minecraft:arrow',
                      'quantity' => 5,
                      'functions' => 
                      array (
                        0 => 
                        array (
                          'function' => 'set_potion',
                          'id' => 'water_breathing',
                        ),
                      ),
                    ),
                    7 => 
                    array (
                      'item' => 'minecraft:arrow',
                      'quantity' => 5,
                      'functions' => 
                      array (
                        0 => 
                        array (
                          'function' => 'set_potion',
                          'id' => 'healing',
                        ),
                      ),
                    ),
                    8 => 
                    array (
                      'item' => 'minecraft:arrow',
                      'quantity' => 5,
                      'functions' => 
                      array (
                        0 => 
                        array (
                          'function' => 'set_potion',
                          'id' => 'harming',
                        ),
                      ),
                    ),
                    9 => 
                    array (
                      'item' => 'minecraft:arrow',
                      'quantity' => 5,
                      'functions' => 
                      array (
                        0 => 
                        array (
                          'function' => 'set_potion',
                          'id' => 'poison',
                        ),
                      ),
                    ),
                    10 => 
                    array (
                      'item' => 'minecraft:arrow',
                      'quantity' => 5,
                      'functions' => 
                      array (
                        0 => 
                        array (
                          'function' => 'set_potion',
                          'id' => 'regeneration',
                        ),
                      ),
                    ),
                    11 => 
                    array (
                      'item' => 'minecraft:arrow',
                      'quantity' => 5,
                      'functions' => 
                      array (
                        0 => 
                        array (
                          'function' => 'set_potion',
                          'id' => 'strength',
                        ),
                      ),
                    ),
                    12 => 
                    array (
                      'item' => 'minecraft:arrow',
                      'quantity' => 5,
                      'functions' => 
                      array (
                        0 => 
                        array (
                          'function' => 'set_potion',
                          'id' => 'weakness',
                        ),
                      ),
                    ),
                    13 => 
                    array (
                      'item' => 'minecraft:arrow',
                      'quantity' => 5,
                      'functions' => 
                      array (
                        0 => 
                        array (
                          'function' => 'set_potion',
                          'id' => 'turtle_master',
                        ),
                      ),
                    ),
                    14 => 
                    array (
                      'item' => 'minecraft:arrow',
                      'quantity' => 5,
                      'functions' => 
                      array (
                        0 => 
                        array (
                          'function' => 'set_potion',
                          'id' => 'wither',
                        ),
                      ),
                    ),
                  ),
                ),
              ),
              'trader_exp' => 30,
              'max_uses' => 12,
              'reward_exp' => true,
            ),
          ),
        ),
      ),
    ),
  ),
),
			'leather_worker_trades.json' => array (
  'tiers' => 
  array (
    0 => 
    array (
      'total_exp_required' => 0,
      'groups' => 
      array (
        0 => 
        array (
          'num_to_select' => 1,
          'trades' => 
          array (
            0 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:leather',
                  'quantity' => 6,
                  'price_multiplier' => 0.05,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                ),
              ),
              'trader_exp' => 2,
              'max_uses' => 16,
              'reward_exp' => true,
            ),
          ),
        ),
        1 => 
        array (
          'num_to_select' => 1,
          'trades' => 
          array (
            0 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 3,
                  'price_multiplier' => 0.2,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:leather_leggings',
                  'quantity' => 1,
                  'functions' => 
                  array (
                    0 => 
                    array (
                      'function' => 'random_dye',
                    ),
                  ),
                ),
              ),
              'trader_exp' => 1,
              'max_uses' => 12,
              'reward_exp' => true,
            ),
            1 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 7,
                  'price_multiplier' => 0.2,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:leather_chestplate',
                  'quantity' => 1,
                  'functions' => 
                  array (
                    0 => 
                    array (
                      'function' => 'random_dye',
                    ),
                  ),
                ),
              ),
              'trader_exp' => 1,
              'max_uses' => 12,
              'reward_exp' => true,
            ),
          ),
        ),
      ),
    ),
    1 => 
    array (
      'total_exp_required' => 10,
      'groups' => 
      array (
        0 => 
        array (
          'num_to_select' => 1,
          'trades' => 
          array (
            0 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:flint',
                  'quantity' => 26,
                  'price_multiplier' => 0.05,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                ),
              ),
              'trader_exp' => 10,
              'max_uses' => 12,
              'reward_exp' => true,
            ),
          ),
        ),
        1 => 
        array (
          'num_to_select' => 1,
          'trades' => 
          array (
            0 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 5,
                  'price_multiplier' => 0.2,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:leather_helmet',
                  'quantity' => 1,
                  'functions' => 
                  array (
                    0 => 
                    array (
                      'function' => 'random_dye',
                    ),
                  ),
                ),
              ),
              'trader_exp' => 5,
              'max_uses' => 12,
              'reward_exp' => true,
            ),
            1 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 4,
                  'price_multiplier' => 0.2,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:leather_boots',
                  'quantity' => 1,
                  'functions' => 
                  array (
                    0 => 
                    array (
                      'function' => 'random_dye',
                    ),
                  ),
                ),
              ),
              'trader_exp' => 5,
              'max_uses' => 12,
              'reward_exp' => true,
            ),
          ),
        ),
      ),
    ),
    2 => 
    array (
      'total_exp_required' => 70,
      'groups' => 
      array (
        0 => 
        array (
          'num_to_select' => 1,
          'trades' => 
          array (
            0 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:rabbit_hide',
                  'quantity' => 9,
                  'price_multiplier' => 0.05,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                ),
              ),
              'trader_exp' => 20,
              'max_uses' => 12,
              'reward_exp' => true,
            ),
          ),
        ),
        1 => 
        array (
          'num_to_select' => 1,
          'trades' => 
          array (
            0 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 7,
                  'price_multiplier' => 0.2,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:leather_chestplate',
                  'quantity' => 1,
                  'functions' => 
                  array (
                    0 => 
                    array (
                      'function' => 'random_dye',
                    ),
                  ),
                ),
              ),
              'trader_exp' => 10,
              'max_uses' => 12,
              'reward_exp' => true,
            ),
          ),
        ),
      ),
    ),
    3 => 
    array (
      'total_exp_required' => 150,
      'groups' => 
      array (
        0 => 
        array (
          'num_to_select' => 1,
          'trades' => 
          array (
            0 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:turtle_shell_piece',
                  'quantity' => 4,
                  'price_multiplier' => 0.05,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                ),
              ),
              'trader_exp' => 30,
              'max_uses' => 12,
              'reward_exp' => true,
            ),
          ),
        ),
        1 => 
        array (
          'num_to_select' => 1,
          'trades' => 
          array (
            0 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 6,
                  'price_multiplier' => 0.2,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:horsearmorleather',
                  'quantity' => 1,
                  'functions' => 
                  array (
                    0 => 
                    array (
                      'function' => 'random_dye',
                    ),
                  ),
                ),
              ),
              'trader_exp' => 15,
              'max_uses' => 12,
              'reward_exp' => true,
            ),
          ),
        ),
      ),
    ),
    4 => 
    array (
      'total_exp_required' => 250,
      'groups' => 
      array (
        0 => 
        array (
          'num_to_select' => 1,
          'trades' => 
          array (
            0 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 5,
                  'price_multiplier' => 0.2,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:leather_helmet',
                  'quantity' => 1,
                  'functions' => 
                  array (
                    0 => 
                    array (
                      'function' => 'random_dye',
                    ),
                  ),
                ),
              ),
              'trader_exp' => 30,
              'max_uses' => 12,
              'reward_exp' => true,
            ),
            1 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 6,
                  'price_multiplier' => 0.2,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:saddle',
                  'quantity' => 1,
                ),
              ),
              'trader_exp' => 30,
              'max_uses' => 12,
              'reward_exp' => true,
            ),
          ),
        ),
      ),
    ),
  ),
),
			'librarian_trades.json' => array (
  'tiers' => 
  array (
    0 => 
    array (
      'total_exp_required' => 0,
      'groups' => 
      array (
        0 => 
        array (
          'num_to_select' => 1,
          'trades' => 
          array (
            0 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:paper',
                  'quantity' => 24,
                  'price_multiplier' => 0.05,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                ),
              ),
              'trader_exp' => 2,
              'max_uses' => 16,
              'reward_exp' => true,
            ),
          ),
        ),
        1 => 
        array (
          'num_to_select' => 1,
          'trades' => 
          array (
            0 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 9,
                  'price_multiplier' => 0.05,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:bookshelf',
                  'quantity' => 1,
                ),
              ),
              'trader_exp' => 1,
              'max_uses' => 12,
              'reward_exp' => true,
            ),
            1 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'price_multiplier' => 0.2,
                ),
                1 => 
                array (
                  'item' => 'minecraft:book',
                  'quantity' => 1,
                  'price_multiplier' => 0.2,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:book',
                  'quantity' => 1,
                  'functions' => 
                  array (
                    0 => 
                    array (
                      'function' => 'enchant_book_for_trading',
                      'base_cost' => 2,
                      'base_random_cost' => 5,
                      'per_level_random_cost' => 10,
                      'per_level_cost' => 3,
                    ),
                  ),
                ),
              ),
              'trader_exp' => 1,
              'max_uses' => 12,
              'reward_exp' => true,
            ),
          ),
        ),
      ),
    ),
    1 => 
    array (
      'total_exp_required' => 10,
      'groups' => 
      array (
        0 => 
        array (
          'num_to_select' => 1,
          'trades' => 
          array (
            0 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:book',
                  'quantity' => 4,
                  'price_multiplier' => 0.05,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                ),
              ),
              'trader_exp' => 10,
              'max_uses' => 12,
              'reward_exp' => true,
            ),
          ),
        ),
        1 => 
        array (
          'num_to_select' => 1,
          'trades' => 
          array (
            0 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                  'price_multiplier' => 0.05,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:lantern',
                  'quantity' => 1,
                ),
              ),
              'trader_exp' => 5,
              'max_uses' => 12,
              'reward_exp' => true,
            ),
            1 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'price_multiplier' => 0.2,
                ),
                1 => 
                array (
                  'item' => 'minecraft:book',
                  'quantity' => 1,
                  'price_multiplier' => 0.2,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:book',
                  'quantity' => 1,
                  'functions' => 
                  array (
                    0 => 
                    array (
                      'function' => 'enchant_book_for_trading',
                      'base_cost' => 2,
                      'base_random_cost' => 5,
                      'per_level_random_cost' => 10,
                      'per_level_cost' => 3,
                    ),
                  ),
                ),
              ),
              'trader_exp' => 5,
              'max_uses' => 12,
              'reward_exp' => true,
            ),
          ),
        ),
      ),
    ),
    2 => 
    array (
      'total_exp_required' => 70,
      'groups' => 
      array (
        0 => 
        array (
          'num_to_select' => 1,
          'trades' => 
          array (
            0 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:dye:0',
                  'quantity' => 5,
                  'price_multiplier' => 0.05,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                ),
              ),
              'trader_exp' => 20,
              'max_uses' => 12,
              'reward_exp' => true,
            ),
          ),
        ),
        1 => 
        array (
          'num_to_select' => 1,
          'trades' => 
          array (
            0 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                  'price_multiplier' => 0.05,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:glass',
                  'quantity' => 4,
                ),
              ),
              'trader_exp' => 10,
              'max_uses' => 12,
              'reward_exp' => true,
            ),
            1 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'price_multiplier' => 0.2,
                ),
                1 => 
                array (
                  'item' => 'minecraft:book',
                  'quantity' => 1,
                  'price_multiplier' => 0.2,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:book',
                  'quantity' => 1,
                  'functions' => 
                  array (
                    0 => 
                    array (
                      'function' => 'enchant_book_for_trading',
                      'base_cost' => 2,
                      'base_random_cost' => 5,
                      'per_level_random_cost' => 10,
                      'per_level_cost' => 3,
                    ),
                  ),
                ),
              ),
              'trader_exp' => 10,
              'max_uses' => 12,
              'reward_exp' => true,
            ),
          ),
        ),
      ),
    ),
    3 => 
    array (
      'total_exp_required' => 150,
      'groups' => 
      array (
        0 => 
        array (
          'num_to_select' => 1,
          'trades' => 
          array (
            0 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:writable_book',
                  'quantity' => 1,
                  'price_multiplier' => 0.05,
                ),
                1 => 
                array (
                  'item' => 'minecraft:writable_book',
                  'quantity' => 1,
                  'price_multiplier' => 0.05,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                ),
              ),
              'trader_exp' => 30,
              'max_uses' => 12,
              'reward_exp' => true,
            ),
          ),
        ),
        1 => 
        array (
          'num_to_select' => 1,
          'trades' => 
          array (
            0 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 4,
                  'price_multiplier' => 0.05,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:compass',
                  'quantity' => 1,
                ),
              ),
              'trader_exp' => 15,
              'max_uses' => 12,
              'reward_exp' => true,
            ),
            1 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 5,
                  'price_multiplier' => 0.05,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:clock',
                  'quantity' => 1,
                ),
              ),
              'trader_exp' => 15,
              'max_uses' => 12,
              'reward_exp' => true,
            ),
            2 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'price_multiplier' => 0.2,
                ),
                1 => 
                array (
                  'item' => 'minecraft:book',
                  'quantity' => 1,
                  'price_multiplier' => 0.2,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:book',
                  'quantity' => 1,
                  'functions' => 
                  array (
                    0 => 
                    array (
                      'function' => 'enchant_book_for_trading',
                      'base_cost' => 2,
                      'base_random_cost' => 5,
                      'per_level_random_cost' => 10,
                      'per_level_cost' => 3,
                    ),
                  ),
                ),
              ),
              'trader_exp' => 15,
              'max_uses' => 12,
              'reward_exp' => true,
            ),
          ),
        ),
      ),
    ),
    4 => 
    array (
      'total_exp_required' => 250,
      'groups' => 
      array (
        0 => 
        array (
          'num_to_select' => 1,
          'trades' => 
          array (
            0 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 3,
                  'price_multiplier' => 0.05,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:red_candle',
                  'quantity' => 1,
                ),
              ),
              'trader_exp' => 30,
              'max_uses' => 12,
              'reward_exp' => true,
            ),
            1 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 3,
                  'price_multiplier' => 0.05,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:red_candle',
                  'quantity' => 1,
                ),
              ),
              'trader_exp' => 30,
              'max_uses' => 12,
              'reward_exp' => true,
            ),
            2 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 3,
                  'price_multiplier' => 0.05,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:red_candle',
                  'quantity' => 1,
                ),
              ),
              'trader_exp' => 30,
              'max_uses' => 12,
              'reward_exp' => true,
            ),
            3 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 3,
                  'price_multiplier' => 0.05,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:yellow_candle',
                  'quantity' => 1,
                ),
              ),
              'trader_exp' => 30,
              'max_uses' => 12,
              'reward_exp' => true,
            ),
          ),
        ),
      ),
    ),
  ),
),
			'shepherd_trades.json' => array (
  'tiers' => 
  array (
    0 => 
    array (
      'total_exp_required' => 0,
      'groups' => 
      array (
        0 => 
        array (
          'num_to_select' => 1,
          'trades' => 
          array (
            0 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'choice' => 
                  array (
                    0 => 
                    array (
                      'item' => 'minecraft:white_wool',
                      'quantity' => 18,
                      'price_multiplier' => 0.05,
                    ),
                    1 => 
                    array (
                      'item' => 'minecraft:gray_wool',
                      'quantity' => 18,
                      'price_multiplier' => 0.05,
                    ),
                    2 => 
                    array (
                      'item' => 'minecraft:brown_wool',
                      'quantity' => 18,
                      'price_multiplier' => 0.05,
                    ),
                    3 => 
                    array (
                      'item' => 'minecraft:black_wool',
                      'quantity' => 18,
                      'price_multiplier' => 0.05,
                    ),
                  ),
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                ),
              ),
              'trader_exp' => 2,
              'max_uses' => 16,
              'reward_exp' => true,
            ),
          ),
        ),
        1 => 
        array (
          'num_to_select' => 1,
          'trades' => 
          array (
            0 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 2,
                  'price_multiplier' => 0.05,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:shears',
                  'quantity' => 1,
                ),
              ),
              'trader_exp' => 1,
              'max_uses' => 12,
              'reward_exp' => true,
            ),
          ),
        ),
      ),
    ),
    1 => 
    array (
      'total_exp_required' => 10,
      'groups' => 
      array (
        0 => 
        array (
          'num_to_select' => 1,
          'trades' => 
          array (
            0 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'choice' => 
                  array (
                    0 => 
                    array (
                      'item' => 'minecraft:black_dye',
                      'quantity' => 12,
                      'price_multiplier' => 0.05,
                    ),
                    1 => 
                    array (
                      'item' => 'minecraft:gray_dye',
                      'quantity' => 12,
                      'price_multiplier' => 0.05,
                    ),
                    2 => 
                    array (
                      'item' => 'minecraft:lime_dye',
                      'quantity' => 12,
                      'price_multiplier' => 0.05,
                    ),
                    3 => 
                    array (
                      'item' => 'minecraft:light_blue_dye',
                      'quantity' => 12,
                      'price_multiplier' => 0.05,
                    ),
                    4 => 
                    array (
                      'item' => 'minecraft:white_dye',
                      'quantity' => 12,
                      'price_multiplier' => 0.05,
                    ),
                  ),
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                ),
              ),
              'trader_exp' => 10,
              'max_uses' => 16,
              'reward_exp' => true,
            ),
          ),
        ),
        1 => 
        array (
          'num_to_select' => 1,
          'trades' => 
          array (
            0 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                  'price_multiplier' => 0.05,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'choice' => 
                  array (
                    0 => 
                    array (
                      'item' => 'minecraft:white_wool',
                    ),
                    1 => 
                    array (
                      'item' => 'minecraft:orange_wool',
                    ),
                    2 => 
                    array (
                      'item' => 'minecraft:magenta_wool',
                    ),
                    3 => 
                    array (
                      'item' => 'minecraft:light_blue_wool',
                    ),
                    4 => 
                    array (
                      'item' => 'minecraft:yellow_wool',
                    ),
                    5 => 
                    array (
                      'item' => 'minecraft:lime_wool',
                    ),
                    6 => 
                    array (
                      'item' => 'minecraft:pink_wool',
                    ),
                    7 => 
                    array (
                      'item' => 'minecraft:gray_wool',
                    ),
                    8 => 
                    array (
                      'item' => 'minecraft:light_gray_wool',
                    ),
                    9 => 
                    array (
                      'item' => 'minecraft:cyan_wool',
                    ),
                    10 => 
                    array (
                      'item' => 'minecraft:purple_wool',
                    ),
                    11 => 
                    array (
                      'item' => 'minecraft:blue_wool',
                    ),
                    12 => 
                    array (
                      'item' => 'minecraft:brown_wool',
                    ),
                    13 => 
                    array (
                      'item' => 'minecraft:green_wool',
                    ),
                    14 => 
                    array (
                      'item' => 'minecraft:red_wool',
                    ),
                    15 => 
                    array (
                      'item' => 'minecraft:black_wool',
                    ),
                  ),
                ),
              ),
              'trader_exp' => 5,
              'max_uses' => 16,
              'reward_exp' => true,
            ),
            1 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                  'price_multiplier' => 0.05,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'choice' => 
                  array (
                    0 => 
                    array (
                      'item' => 'minecraft:white_carpet',
                      'quantity' => 4,
                    ),
                    1 => 
                    array (
                      'item' => 'minecraft:orange_carpet',
                      'quantity' => 4,
                    ),
                    2 => 
                    array (
                      'item' => 'minecraft:magenta_carpet',
                      'quantity' => 4,
                    ),
                    3 => 
                    array (
                      'item' => 'minecraft:light_blue_carpet',
                      'quantity' => 4,
                    ),
                    4 => 
                    array (
                      'item' => 'minecraft:yellow_carpet',
                      'quantity' => 4,
                    ),
                    5 => 
                    array (
                      'item' => 'minecraft:lime_carpet',
                      'quantity' => 4,
                    ),
                    6 => 
                    array (
                      'item' => 'minecraft:pink_carpet',
                      'quantity' => 4,
                    ),
                    7 => 
                    array (
                      'item' => 'minecraft:gray_carpet',
                      'quantity' => 4,
                    ),
                    8 => 
                    array (
                      'item' => 'minecraft:light_gray_carpet',
                      'quantity' => 4,
                    ),
                    9 => 
                    array (
                      'item' => 'minecraft:cyan_carpet',
                      'quantity' => 4,
                    ),
                    10 => 
                    array (
                      'item' => 'minecraft:purple_carpet',
                      'quantity' => 4,
                    ),
                    11 => 
                    array (
                      'item' => 'minecraft:blue_carpet',
                      'quantity' => 4,
                    ),
                    12 => 
                    array (
                      'item' => 'minecraft:brown_carpet',
                      'quantity' => 4,
                    ),
                    13 => 
                    array (
                      'item' => 'minecraft:green_carpet',
                      'quantity' => 4,
                    ),
                    14 => 
                    array (
                      'item' => 'minecraft:red_carpet',
                      'quantity' => 4,
                    ),
                    15 => 
                    array (
                      'item' => 'minecraft:black_carpet',
                      'quantity' => 4,
                    ),
                  ),
                ),
              ),
              'trader_exp' => 5,
              'max_uses' => 16,
              'reward_exp' => true,
            ),
          ),
        ),
      ),
    ),
    2 => 
    array (
      'total_exp_required' => 70,
      'groups' => 
      array (
        0 => 
        array (
          'num_to_select' => 1,
          'trades' => 
          array (
            0 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'choice' => 
                  array (
                    0 => 
                    array (
                      'item' => 'minecraft:red_dye',
                      'quantity' => 12,
                      'price_multiplier' => 0.05,
                    ),
                    1 => 
                    array (
                      'item' => 'minecraft:light_gray_dye',
                      'quantity' => 12,
                      'price_multiplier' => 0.05,
                    ),
                    2 => 
                    array (
                      'item' => 'minecraft:pink_dye',
                      'quantity' => 12,
                      'price_multiplier' => 0.05,
                    ),
                    3 => 
                    array (
                      'item' => 'minecraft:yellow_dye',
                      'quantity' => 12,
                      'price_multiplier' => 0.05,
                    ),
                    4 => 
                    array (
                      'item' => 'minecraft:orange_dye',
                      'quantity' => 12,
                      'price_multiplier' => 0.05,
                    ),
                  ),
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                ),
              ),
              'trader_exp' => 20,
              'max_uses' => 16,
              'reward_exp' => true,
            ),
          ),
        ),
        1 => 
        array (
          'num_to_select' => 1,
          'trades' => 
          array (
            0 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 3,
                  'price_multiplier' => 0.05,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'choice' => 
                  array (
                    0 => 
                    array (
                      'item' => 'minecraft:bed',
                      'functions' => 
                      array (
                        0 => 
                        array (
                          'function' => 'random_aux_value',
                          'values' => 
                          array (
                            'min' => 0,
                            'max' => 15,
                          ),
                        ),
                      ),
                    ),
                  ),
                ),
              ),
              'trader_exp' => 10,
              'max_uses' => 12,
              'reward_exp' => true,
            ),
          ),
        ),
      ),
    ),
    3 => 
    array (
      'total_exp_required' => 150,
      'groups' => 
      array (
        0 => 
        array (
          'num_to_select' => 1,
          'trades' => 
          array (
            0 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'choice' => 
                  array (
                    0 => 
                    array (
                      'item' => 'minecraft:green_dye',
                      'quantity' => 12,
                      'price_multiplier' => 0.05,
                    ),
                    1 => 
                    array (
                      'item' => 'minecraft:brown_dye',
                      'quantity' => 12,
                      'price_multiplier' => 0.05,
                    ),
                    2 => 
                    array (
                      'item' => 'minecraft:blue_dye',
                      'quantity' => 12,
                      'price_multiplier' => 0.05,
                    ),
                    3 => 
                    array (
                      'item' => 'minecraft:purple_dye',
                      'quantity' => 12,
                      'price_multiplier' => 0.05,
                    ),
                    4 => 
                    array (
                      'item' => 'minecraft:cyan_dye',
                      'quantity' => 12,
                      'price_multiplier' => 0.05,
                    ),
                    5 => 
                    array (
                      'item' => 'minecraft:magenta_dye',
                      'quantity' => 12,
                      'price_multiplier' => 0.05,
                    ),
                  ),
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                ),
              ),
              'trader_exp' => 30,
              'max_uses' => 16,
              'reward_exp' => true,
            ),
          ),
        ),
        1 => 
        array (
          'num_to_select' => 1,
          'trades' => 
          array (
            0 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 3,
                  'price_multiplier' => 0.05,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:banner',
                  'quantity' => 1,
                  'functions' => 
                  array (
                    0 => 
                    array (
                      'function' => 'random_aux_value',
                      'values' => 
                      array (
                        'min' => 0,
                        'max' => 15,
                      ),
                    ),
                  ),
                ),
              ),
              'trader_exp' => 15,
              'max_uses' => 12,
              'reward_exp' => true,
            ),
          ),
        ),
      ),
    ),
    4 => 
    array (
      'total_exp_required' => 250,
      'groups' => 
      array (
        0 => 
        array (
          'num_to_select' => 1,
          'trades' => 
          array (
            0 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 2,
                  'price_multiplier' => 0.05,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:painting',
                  'quantity' => 3,
                ),
              ),
              'trader_exp' => 30,
              'max_uses' => 12,
              'reward_exp' => true,
            ),
          ),
        ),
      ),
    ),
  ),
),
			'stone_mason_trades.json' => array (
  'format_version' => '1.26.10',
  'tiers' => 
  array (
    0 => 
    array (
      'groups' => 
      array (
        0 => 
        array (
          'num_to_select' => -1,
          'trades' => 
          array (
            0 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'choice' => 
                  array (
                    0 => 
                    array (
                      'quantity' => 
                      array (
                        'min' => 10,
                        'max' => 10,
                      ),
                      'item' => 'minecraft:clay_ball',
                      'functions' => 
                      array (
                      ),
                      'price_multiplier' => 0.05,
                    ),
                  ),
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'choice' => 
                  array (
                    0 => 
                    array (
                      'item' => 'minecraft:emerald',
                      'functions' => 
                      array (
                      ),
                    ),
                  ),
                ),
              ),
              'trader_exp' => 2,
              'max_uses' => 16,
            ),
            1 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'choice' => 
                  array (
                    0 => 
                    array (
                      'item' => 'minecraft:emerald',
                      'functions' => 
                      array (
                      ),
                      'price_multiplier' => 0.05,
                    ),
                  ),
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'choice' => 
                  array (
                    0 => 
                    array (
                      'quantity' => 
                      array (
                        'min' => 10,
                        'max' => 10,
                      ),
                      'item' => 'minecraft:brick',
                      'functions' => 
                      array (
                      ),
                    ),
                  ),
                ),
              ),
              'max_uses' => 16,
            ),
          ),
        ),
      ),
      'total_exp_required' => 0,
    ),
    1 => 
    array (
      'groups' => 
      array (
        0 => 
        array (
          'num_to_select' => -1,
          'trades' => 
          array (
            0 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'choice' => 
                  array (
                    0 => 
                    array (
                      'quantity' => 
                      array (
                        'min' => 20,
                        'max' => 20,
                      ),
                      'item' => 'minecraft:stone',
                      'functions' => 
                      array (
                      ),
                      'price_multiplier' => 0.05,
                    ),
                  ),
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'choice' => 
                  array (
                    0 => 
                    array (
                      'item' => 'minecraft:emerald',
                      'functions' => 
                      array (
                      ),
                    ),
                  ),
                ),
              ),
              'trader_exp' => 10,
              'max_uses' => 16,
            ),
            1 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'choice' => 
                  array (
                    0 => 
                    array (
                      'item' => 'minecraft:emerald',
                      'functions' => 
                      array (
                      ),
                      'price_multiplier' => 0.05,
                    ),
                  ),
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'choice' => 
                  array (
                    0 => 
                    array (
                      'quantity' => 
                      array (
                        'min' => 4,
                        'max' => 4,
                      ),
                      'item' => 'minecraft:chiseled_stone_bricks',
                      'functions' => 
                      array (
                      ),
                    ),
                  ),
                ),
              ),
              'trader_exp' => 5,
              'max_uses' => 16,
            ),
          ),
        ),
      ),
      'total_exp_required' => 10,
    ),
    2 => 
    array (
      'groups' => 
      array (
        0 => 
        array (
          'num_to_select' => -1,
          'trades' => 
          array (
            0 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'choice' => 
                  array (
                    0 => 
                    array (
                      'quantity' => 
                      array (
                        'min' => 16,
                        'max' => 16,
                      ),
                      'item' => 'minecraft:granite',
                      'functions' => 
                      array (
                      ),
                      'price_multiplier' => 0.05,
                    ),
                    1 => 
                    array (
                      'quantity' => 
                      array (
                        'min' => 16,
                        'max' => 16,
                      ),
                      'item' => 'minecraft:diorite',
                      'functions' => 
                      array (
                      ),
                      'price_multiplier' => 0.05,
                    ),
                    2 => 
                    array (
                      'quantity' => 
                      array (
                        'min' => 16,
                        'max' => 16,
                      ),
                      'item' => 'minecraft:andesite',
                      'functions' => 
                      array (
                      ),
                      'price_multiplier' => 0.05,
                    ),
                  ),
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'choice' => 
                  array (
                    0 => 
                    array (
                      'item' => 'minecraft:emerald',
                      'functions' => 
                      array (
                      ),
                    ),
                  ),
                ),
              ),
              'trader_exp' => 20,
              'max_uses' => 16,
            ),
            1 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'choice' => 
                  array (
                    0 => 
                    array (
                      'item' => 'minecraft:emerald',
                      'functions' => 
                      array (
                      ),
                      'price_multiplier' => 0.05,
                    ),
                  ),
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'choice' => 
                  array (
                    0 => 
                    array (
                      'quantity' => 
                      array (
                        'min' => 4,
                        'max' => 4,
                      ),
                      'item' => 'minecraft:polished_granite',
                      'functions' => 
                      array (
                      ),
                    ),
                    1 => 
                    array (
                      'quantity' => 
                      array (
                        'min' => 4,
                        'max' => 4,
                      ),
                      'item' => 'minecraft:polished_diorite',
                      'functions' => 
                      array (
                      ),
                    ),
                    2 => 
                    array (
                      'quantity' => 
                      array (
                        'min' => 4,
                        'max' => 4,
                      ),
                      'item' => 'minecraft:polished_andesite',
                      'functions' => 
                      array (
                      ),
                    ),
                    3 => 
                    array (
                      'quantity' => 
                      array (
                        'min' => 4,
                        'max' => 4,
                      ),
                      'item' => 'minecraft:dripstone_block',
                      'functions' => 
                      array (
                      ),
                    ),
                  ),
                ),
              ),
              'trader_exp' => 10,
              'max_uses' => 16,
            ),
          ),
        ),
      ),
      'total_exp_required' => 70,
    ),
    3 => 
    array (
      'groups' => 
      array (
        0 => 
        array (
          'num_to_select' => -1,
          'trades' => 
          array (
            0 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'choice' => 
                  array (
                    0 => 
                    array (
                      'quantity' => 
                      array (
                        'min' => 12,
                        'max' => 12,
                      ),
                      'item' => 'minecraft:quartz',
                      'functions' => 
                      array (
                      ),
                      'price_multiplier' => 0.05,
                    ),
                  ),
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'choice' => 
                  array (
                    0 => 
                    array (
                      'item' => 'minecraft:emerald',
                      'functions' => 
                      array (
                      ),
                    ),
                  ),
                ),
              ),
              'trader_exp' => 30,
              'max_uses' => 12,
            ),
            1 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'choice' => 
                  array (
                    0 => 
                    array (
                      'item' => 'minecraft:emerald',
                      'functions' => 
                      array (
                      ),
                      'price_multiplier' => 0.05,
                    ),
                  ),
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'choice' => 
                  array (
                    0 => 
                    array (
                      'item' => 'minecraft:white_terracotta',
                      'functions' => 
                      array (
                      ),
                    ),
                    1 => 
                    array (
                      'item' => 'minecraft:orange_terracotta',
                      'functions' => 
                      array (
                      ),
                    ),
                    2 => 
                    array (
                      'item' => 'minecraft:magenta_terracotta',
                      'functions' => 
                      array (
                      ),
                    ),
                    3 => 
                    array (
                      'item' => 'minecraft:light_blue_terracotta',
                      'functions' => 
                      array (
                      ),
                    ),
                    4 => 
                    array (
                      'item' => 'minecraft:yellow_terracotta',
                      'functions' => 
                      array (
                      ),
                    ),
                    5 => 
                    array (
                      'item' => 'minecraft:lime_terracotta',
                      'functions' => 
                      array (
                      ),
                    ),
                    6 => 
                    array (
                      'item' => 'minecraft:pink_terracotta',
                      'functions' => 
                      array (
                      ),
                    ),
                    7 => 
                    array (
                      'item' => 'minecraft:gray_terracotta',
                      'functions' => 
                      array (
                      ),
                    ),
                    8 => 
                    array (
                      'item' => 'minecraft:light_gray_terracotta',
                      'functions' => 
                      array (
                      ),
                    ),
                    9 => 
                    array (
                      'item' => 'minecraft:cyan_terracotta',
                      'functions' => 
                      array (
                      ),
                    ),
                    10 => 
                    array (
                      'item' => 'minecraft:purple_terracotta',
                      'functions' => 
                      array (
                      ),
                    ),
                    11 => 
                    array (
                      'item' => 'minecraft:blue_terracotta',
                      'functions' => 
                      array (
                      ),
                    ),
                    12 => 
                    array (
                      'item' => 'minecraft:brown_terracotta',
                      'functions' => 
                      array (
                      ),
                    ),
                    13 => 
                    array (
                      'item' => 'minecraft:green_terracotta',
                      'functions' => 
                      array (
                      ),
                    ),
                    14 => 
                    array (
                      'item' => 'minecraft:red_terracotta',
                      'functions' => 
                      array (
                      ),
                    ),
                    15 => 
                    array (
                      'item' => 'minecraft:black_terracotta',
                      'functions' => 
                      array (
                      ),
                    ),
                    16 => 
                    array (
                      'item' => 'minecraft:black_glazed_terracotta',
                      'functions' => 
                      array (
                      ),
                    ),
                    17 => 
                    array (
                      'item' => 'minecraft:blue_glazed_terracotta',
                      'functions' => 
                      array (
                      ),
                    ),
                    18 => 
                    array (
                      'item' => 'minecraft:brown_glazed_terracotta',
                      'functions' => 
                      array (
                      ),
                    ),
                    19 => 
                    array (
                      'item' => 'minecraft:cyan_glazed_terracotta',
                      'functions' => 
                      array (
                      ),
                    ),
                    20 => 
                    array (
                      'item' => 'minecraft:gray_glazed_terracotta',
                      'functions' => 
                      array (
                      ),
                    ),
                    21 => 
                    array (
                      'item' => 'minecraft:green_glazed_terracotta',
                      'functions' => 
                      array (
                      ),
                    ),
                    22 => 
                    array (
                      'item' => 'minecraft:light_blue_glazed_terracotta',
                      'functions' => 
                      array (
                      ),
                    ),
                    23 => 
                    array (
                      'item' => 'minecraft:lime_glazed_terracotta',
                      'functions' => 
                      array (
                      ),
                    ),
                    24 => 
                    array (
                      'item' => 'minecraft:magenta_glazed_terracotta',
                      'functions' => 
                      array (
                      ),
                    ),
                    25 => 
                    array (
                      'item' => 'minecraft:orange_glazed_terracotta',
                      'functions' => 
                      array (
                      ),
                    ),
                    26 => 
                    array (
                      'item' => 'minecraft:pink_glazed_terracotta',
                      'functions' => 
                      array (
                      ),
                    ),
                    27 => 
                    array (
                      'item' => 'minecraft:purple_glazed_terracotta',
                      'functions' => 
                      array (
                      ),
                    ),
                    28 => 
                    array (
                      'item' => 'minecraft:red_glazed_terracotta',
                      'functions' => 
                      array (
                      ),
                    ),
                    29 => 
                    array (
                      'item' => 'minecraft:silver_glazed_terracotta',
                      'functions' => 
                      array (
                      ),
                    ),
                    30 => 
                    array (
                      'item' => 'minecraft:white_glazed_terracotta',
                      'functions' => 
                      array (
                      ),
                    ),
                    31 => 
                    array (
                      'item' => 'minecraft:yellow_glazed_terracotta',
                      'functions' => 
                      array (
                      ),
                    ),
                  ),
                ),
              ),
              'trader_exp' => 15,
              'max_uses' => 12,
            ),
          ),
        ),
      ),
      'total_exp_required' => 150,
    ),
    4 => 
    array (
      'groups' => 
      array (
        0 => 
        array (
          'num_to_select' => -1,
          'trades' => 
          array (
            0 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'choice' => 
                  array (
                    0 => 
                    array (
                      'item' => 'minecraft:emerald',
                      'functions' => 
                      array (
                      ),
                      'price_multiplier' => 0.05,
                    ),
                  ),
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'choice' => 
                  array (
                    0 => 
                    array (
                      'item' => 'minecraft:quartz_block',
                      'functions' => 
                      array (
                      ),
                    ),
                    1 => 
                    array (
                      'item' => 'minecraft:quartz_pillar',
                      'functions' => 
                      array (
                      ),
                    ),
                  ),
                ),
              ),
              'trader_exp' => 30,
              'max_uses' => 12,
            ),
          ),
        ),
      ),
      'total_exp_required' => 250,
    ),
  ),
),
			'tool_smith_trades.json' => array (
  'tiers' => 
  array (
    0 => 
    array (
      'total_exp_required' => 0,
      'groups' => 
      array (
        0 => 
        array (
          'num_to_select' => 1,
          'trades' => 
          array (
            0 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:coal:0',
                  'quantity' => 15,
                  'price_multiplier' => 0.05,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                ),
              ),
              'trader_exp' => 2,
              'max_uses' => 16,
              'reward_exp' => true,
            ),
          ),
        ),
        1 => 
        array (
          'num_to_select' => 1,
          'trades' => 
          array (
            0 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                  'price_multiplier' => 0.2,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:stone_axe',
                  'quantity' => 1,
                ),
              ),
              'trader_exp' => 1,
              'max_uses' => 12,
              'reward_exp' => true,
            ),
            1 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                  'price_multiplier' => 0.2,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:stone_shovel',
                  'quantity' => 1,
                ),
              ),
              'trader_exp' => 1,
              'max_uses' => 12,
              'reward_exp' => true,
            ),
            2 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                  'price_multiplier' => 0.2,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:stone_pickaxe',
                  'quantity' => 1,
                ),
              ),
              'trader_exp' => 1,
              'max_uses' => 12,
              'reward_exp' => true,
            ),
            3 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                  'price_multiplier' => 0.2,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:stone_hoe',
                  'quantity' => 1,
                ),
              ),
              'trader_exp' => 1,
              'max_uses' => 12,
              'reward_exp' => true,
            ),
          ),
        ),
      ),
    ),
    1 => 
    array (
      'total_exp_required' => 10,
      'groups' => 
      array (
        0 => 
        array (
          'num_to_select' => 1,
          'trades' => 
          array (
            0 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:iron_ingot',
                  'quantity' => 4,
                  'price_multiplier' => 0.05,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                ),
              ),
              'trader_exp' => 10,
              'max_uses' => 12,
              'reward_exp' => true,
            ),
          ),
        ),
        1 => 
        array (
          'num_to_select' => 1,
          'trades' => 
          array (
            0 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 36,
                  'price_multiplier' => 0.2,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:bell',
                  'quantity' => 1,
                ),
              ),
              'trader_exp' => 5,
              'max_uses' => 12,
              'reward_exp' => true,
            ),
          ),
        ),
      ),
    ),
    2 => 
    array (
      'total_exp_required' => 70,
      'groups' => 
      array (
        0 => 
        array (
          'num_to_select' => 1,
          'trades' => 
          array (
            0 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:flint',
                  'quantity' => 30,
                  'price_multiplier' => 0.05,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                ),
              ),
              'trader_exp' => 20,
              'max_uses' => 12,
              'reward_exp' => true,
            ),
          ),
        ),
        1 => 
        array (
          'num_to_select' => 1,
          'trades' => 
          array (
            0 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                  'price_multiplier' => 0.2,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:iron_axe',
                  'quantity' => 1,
                  'functions' => 
                  array (
                    0 => 
                    array (
                      'function' => 'enchant_with_levels',
                      'treasure' => false,
                      'levels' => 
                      array (
                        'min' => 5,
                        'max' => 19,
                      ),
                    ),
                  ),
                ),
              ),
              'trader_exp' => 10,
              'max_uses' => 3,
              'reward_exp' => true,
            ),
            1 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 2,
                  'price_multiplier' => 0.2,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:iron_shovel',
                  'quantity' => 1,
                  'functions' => 
                  array (
                    0 => 
                    array (
                      'function' => 'enchant_with_levels',
                      'treasure' => false,
                      'levels' => 
                      array (
                        'min' => 5,
                        'max' => 19,
                      ),
                    ),
                  ),
                ),
              ),
              'trader_exp' => 10,
              'max_uses' => 3,
              'reward_exp' => true,
            ),
            2 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 3,
                  'price_multiplier' => 0.2,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:iron_pickaxe',
                  'quantity' => 1,
                  'functions' => 
                  array (
                    0 => 
                    array (
                      'function' => 'enchant_with_levels',
                      'treasure' => false,
                      'levels' => 
                      array (
                        'min' => 5,
                        'max' => 19,
                      ),
                    ),
                  ),
                ),
              ),
              'trader_exp' => 10,
              'max_uses' => 3,
              'reward_exp' => true,
            ),
            3 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 4,
                  'price_multiplier' => 0.2,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:diamond_hoe',
                  'quantity' => 1,
                ),
              ),
              'trader_exp' => 10,
              'max_uses' => 3,
              'reward_exp' => true,
            ),
          ),
        ),
      ),
    ),
    3 => 
    array (
      'total_exp_required' => 150,
      'groups' => 
      array (
        0 => 
        array (
          'num_to_select' => 1,
          'trades' => 
          array (
            0 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:diamond',
                  'quantity' => 1,
                  'price_multiplier' => 0.05,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                ),
              ),
              'trader_exp' => 30,
              'max_uses' => 12,
              'reward_exp' => true,
            ),
          ),
        ),
        1 => 
        array (
          'num_to_select' => 1,
          'trades' => 
          array (
            0 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 12,
                  'price_multiplier' => 0.2,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:diamond_axe',
                  'quantity' => 1,
                  'functions' => 
                  array (
                    0 => 
                    array (
                      'function' => 'enchant_with_levels',
                      'treasure' => false,
                      'levels' => 
                      array (
                        'min' => 5,
                        'max' => 19,
                      ),
                    ),
                  ),
                ),
              ),
              'trader_exp' => 15,
              'max_uses' => 3,
              'reward_exp' => true,
            ),
            1 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 5,
                  'price_multiplier' => 0.2,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:diamond_shovel',
                  'quantity' => 1,
                  'functions' => 
                  array (
                    0 => 
                    array (
                      'function' => 'enchant_with_levels',
                      'treasure' => false,
                      'levels' => 
                      array (
                        'min' => 5,
                        'max' => 19,
                      ),
                    ),
                  ),
                ),
              ),
              'trader_exp' => 15,
              'max_uses' => 3,
              'reward_exp' => true,
            ),
          ),
        ),
      ),
    ),
    4 => 
    array (
      'total_exp_required' => 250,
      'groups' => 
      array (
        0 => 
        array (
          'num_to_select' => 1,
          'trades' => 
          array (
            0 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 13,
                  'price_multiplier' => 0.2,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:diamond_pickaxe',
                  'quantity' => 1,
                  'functions' => 
                  array (
                    0 => 
                    array (
                      'function' => 'enchant_with_levels',
                      'treasure' => false,
                      'levels' => 
                      array (
                        'min' => 5,
                        'max' => 19,
                      ),
                    ),
                  ),
                ),
              ),
              'trader_exp' => 30,
              'max_uses' => 3,
              'reward_exp' => true,
            ),
          ),
        ),
      ),
    ),
  ),
),
			'wandering_trader_trades.json' => array (
  'tiers' => 
  array (
    0 => 
    array (
      'groups' => 
      array (
        0 => 
        array (
          'num_to_select' => 2,
          'trades' => 
          array (
            0 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:potion:0',
                  'quantity' => 1,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                ),
              ),
              'max_uses' => 2,
            ),
            1 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:water_bucket',
                  'quantity' => 1,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 2,
                ),
              ),
              'max_uses' => 2,
            ),
            2 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:milk_bucket',
                  'quantity' => 1,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 2,
                ),
              ),
              'max_uses' => 2,
            ),
            3 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:fermented_spider_eye',
                  'quantity' => 1,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 3,
                ),
              ),
              'max_uses' => 2,
            ),
            4 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:baked_potato',
                  'quantity' => 4,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                ),
              ),
              'max_uses' => 2,
            ),
            5 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:hay_block',
                  'quantity' => 1,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                ),
              ),
              'max_uses' => 2,
            ),
          ),
        ),
        1 => 
        array (
          'num_to_select' => 2,
          'trades' => 
          array (
            0 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:packed_ice',
                  'quantity' => 1,
                ),
              ),
              'max_uses' => 6,
            ),
            1 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 6,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:blue_ice',
                  'quantity' => 1,
                ),
              ),
              'max_uses' => 6,
            ),
            2 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:gunpowder',
                  'quantity' => 4,
                ),
              ),
              'max_uses' => 2,
            ),
            3 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 3,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:podzol',
                  'quantity' => 3,
                ),
              ),
              'max_uses' => 6,
            ),
            4 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:acacia_log',
                  'quantity' => 8,
                ),
              ),
              'max_uses' => 4,
            ),
            5 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:poplar_log',
                  'quantity' => 8,
                ),
              ),
              'max_uses' => 4,
            ),
            6 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:birch_log:2',
                  'quantity' => 8,
                ),
              ),
              'max_uses' => 4,
            ),
            7 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:dark_oak_log:1',
                  'quantity' => 8,
                ),
              ),
              'max_uses' => 4,
            ),
            8 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:jungle_log:3',
                  'quantity' => 8,
                ),
              ),
              'max_uses' => 4,
            ),
            9 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:oak_log',
                  'quantity' => 8,
                ),
              ),
              'max_uses' => 4,
            ),
            10 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:spruce_log:1',
                  'quantity' => 8,
                ),
              ),
              'max_uses' => 4,
            ),
            11 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:cherry_log',
                  'quantity' => 8,
                ),
              ),
              'max_uses' => 4,
            ),
            12 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:pale_oak_log',
                  'quantity' => 8,
                ),
              ),
              'max_uses' => 4,
            ),
            13 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:mangrove_log',
                  'quantity' => 8,
                ),
              ),
              'max_uses' => 4,
            ),
            14 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                  'price_multiplier' => 0.2,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:iron_pickaxe',
                  'quantity' => 1,
                  'functions' => 
                  array (
                    0 => 
                    array (
                      'function' => 'enchant_with_levels',
                      'treasure' => false,
                      'levels' => 
                      array (
                        'min' => 5,
                        'max' => 19,
                      ),
                    ),
                  ),
                ),
              ),
              'trader_exp' => 1,
              'max_uses' => 1,
              'reward_exp' => true,
            ),
            15 => 
            array (
              'max_uses' => 1,
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 5,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:potion:8',
                  'quantity' => 1,
                ),
              ),
            ),
          ),
        ),
        2 => 
        array (
          'num_to_select' => 5,
          'trades' => 
          array (
            0 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 3,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:tropical_fish_bucket',
                  'quantity' => 1,
                ),
              ),
              'max_uses' => 4,
            ),
            1 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 3,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:pufferfish_bucket',
                  'quantity' => 1,
                ),
              ),
              'max_uses' => 4,
            ),
            2 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 2,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:sea_pickle',
                  'quantity' => 1,
                ),
              ),
              'max_uses' => 5,
            ),
            3 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 4,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:slime_ball',
                  'quantity' => 1,
                ),
              ),
              'max_uses' => 5,
            ),
            4 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 2,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:glowstone',
                  'quantity' => 1,
                ),
              ),
              'max_uses' => 5,
            ),
            5 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 5,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:nautilus_shell',
                  'quantity' => 1,
                ),
              ),
              'max_uses' => 5,
            ),
            6 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:fern',
                  'quantity' => 1,
                ),
              ),
              'max_uses' => 12,
            ),
            7 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:tall_dry_grass',
                  'quantity' => 1,
                ),
              ),
              'max_uses' => 12,
            ),
            8 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:sugar_cane',
                  'quantity' => 1,
                ),
              ),
              'max_uses' => 8,
            ),
            9 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:pumpkin',
                  'quantity' => 1,
                ),
              ),
              'max_uses' => 4,
            ),
            10 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 3,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:kelp',
                  'quantity' => 1,
                ),
              ),
              'max_uses' => 12,
            ),
            11 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 3,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:cactus',
                  'quantity' => 1,
                ),
              ),
              'max_uses' => 8,
            ),
            12 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:dandelion',
                  'quantity' => 1,
                ),
              ),
              'max_uses' => 12,
            ),
            13 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:poppy',
                  'quantity' => 1,
                ),
              ),
              'max_uses' => 12,
            ),
            14 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:blue_orchid',
                  'quantity' => 1,
                ),
              ),
              'max_uses' => 8,
            ),
            15 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:allium',
                  'quantity' => 1,
                ),
              ),
              'max_uses' => 12,
            ),
            16 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:azure_bluet',
                  'quantity' => 1,
                ),
              ),
              'max_uses' => 12,
            ),
            17 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:red_tulip',
                  'quantity' => 1,
                ),
              ),
              'max_uses' => 12,
            ),
            18 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:orange_tulip',
                  'quantity' => 1,
                ),
              ),
              'max_uses' => 12,
            ),
            19 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:white_tulip',
                  'quantity' => 1,
                ),
              ),
              'max_uses' => 12,
            ),
            20 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:pink_tulip',
                  'quantity' => 1,
                ),
              ),
              'max_uses' => 12,
            ),
            21 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:oxeye_daisy',
                  'quantity' => 1,
                ),
              ),
              'max_uses' => 12,
            ),
            22 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:cornflower',
                  'quantity' => 1,
                ),
              ),
              'max_uses' => 12,
            ),
            23 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:wildflowers',
                  'quantity' => 1,
                ),
              ),
              'max_uses' => 12,
            ),
            24 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 3,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:firefly_bush',
                  'quantity' => 1,
                ),
              ),
              'max_uses' => 8,
            ),
            25 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:lily_of_the_valley',
                  'quantity' => 1,
                ),
              ),
              'max_uses' => 7,
            ),
            26 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:open_eyeblossom',
                  'quantity' => 1,
                ),
              ),
              'max_uses' => 7,
            ),
            27 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:wheat_seeds',
                  'quantity' => 1,
                ),
              ),
              'max_uses' => 12,
            ),
            28 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:beetroot_seeds',
                  'quantity' => 1,
                ),
              ),
              'max_uses' => 12,
            ),
            29 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:pumpkin_seeds',
                  'quantity' => 1,
                ),
              ),
              'max_uses' => 12,
            ),
            30 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:melon_seeds',
                  'quantity' => 1,
                ),
              ),
              'max_uses' => 12,
            ),
            31 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 5,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:acacia_sapling',
                  'quantity' => 1,
                ),
              ),
              'max_uses' => 8,
            ),
            32 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 5,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:birch_sapling',
                  'quantity' => 1,
                ),
              ),
              'max_uses' => 8,
            ),
            33 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 5,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:dark_oak_sapling',
                  'quantity' => 1,
                ),
              ),
              'max_uses' => 8,
            ),
            34 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 5,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:jungle_sapling',
                  'quantity' => 1,
                ),
              ),
              'max_uses' => 8,
            ),
            35 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 5,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:oak_sapling',
                  'quantity' => 1,
                ),
              ),
              'max_uses' => 8,
            ),
            36 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 5,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:spruce_sapling',
                  'quantity' => 1,
                ),
              ),
              'max_uses' => 8,
            ),
            37 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 5,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:cherry_sapling',
                  'quantity' => 1,
                ),
              ),
              'max_uses' => 8,
            ),
            38 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 5,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:pale_oak_sapling',
                  'quantity' => 1,
                ),
              ),
              'max_uses' => 8,
            ),
            39 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 5,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:poplar_sapling',
                  'quantity' => 1,
                ),
              ),
              'max_uses' => 8,
            ),
            40 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 5,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:mangrove_propagule',
                  'quantity' => 1,
                ),
              ),
              'max_uses' => 8,
            ),
            41 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:red_dye',
                  'quantity' => 3,
                ),
              ),
              'max_uses' => 12,
            ),
            42 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:white_dye',
                  'quantity' => 3,
                ),
              ),
              'max_uses' => 12,
            ),
            43 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:blue_dye',
                  'quantity' => 3,
                ),
              ),
              'max_uses' => 12,
            ),
            44 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:pink_dye',
                  'quantity' => 3,
                ),
              ),
              'max_uses' => 12,
            ),
            45 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:black_dye',
                  'quantity' => 3,
                ),
              ),
              'max_uses' => 12,
            ),
            46 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:green_dye',
                  'quantity' => 3,
                ),
              ),
              'max_uses' => 12,
            ),
            47 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:light_gray_dye',
                  'quantity' => 3,
                ),
              ),
              'max_uses' => 12,
            ),
            48 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:magenta_dye',
                  'quantity' => 3,
                ),
              ),
              'max_uses' => 12,
            ),
            49 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:yellow_dye',
                  'quantity' => 3,
                ),
              ),
              'max_uses' => 12,
            ),
            50 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:gray_dye',
                  'quantity' => 3,
                ),
              ),
              'max_uses' => 12,
            ),
            51 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:purple_dye',
                  'quantity' => 3,
                ),
              ),
              'max_uses' => 12,
            ),
            52 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:light_blue_dye',
                  'quantity' => 3,
                ),
              ),
              'max_uses' => 12,
            ),
            53 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:lime_dye',
                  'quantity' => 3,
                ),
              ),
              'max_uses' => 12,
            ),
            54 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:orange_dye',
                  'quantity' => 3,
                ),
              ),
              'max_uses' => 12,
            ),
            55 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:brown_dye',
                  'quantity' => 3,
                ),
              ),
              'max_uses' => 12,
            ),
            56 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:cyan_dye',
                  'quantity' => 3,
                ),
              ),
              'max_uses' => 12,
            ),
            57 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 3,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:brain_coral_block',
                  'quantity' => 1,
                ),
              ),
              'max_uses' => 8,
            ),
            58 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 3,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:bubble_coral_block',
                  'quantity' => 1,
                ),
              ),
              'max_uses' => 8,
            ),
            59 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 3,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:fire_coral_block',
                  'quantity' => 1,
                ),
              ),
              'max_uses' => 8,
            ),
            60 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 3,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:horn_coral_block',
                  'quantity' => 1,
                ),
              ),
              'max_uses' => 8,
            ),
            61 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 3,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:tube_coral_block',
                  'quantity' => 1,
                ),
              ),
              'max_uses' => 8,
            ),
            62 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:vine',
                  'quantity' => 3,
                ),
              ),
              'max_uses' => 4,
            ),
            63 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:pale_hanging_moss',
                  'quantity' => 3,
                ),
              ),
              'max_uses' => 4,
            ),
            64 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:brown_mushroom',
                  'quantity' => 3,
                ),
              ),
              'max_uses' => 4,
            ),
            65 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:red_mushroom',
                  'quantity' => 3,
                ),
              ),
              'max_uses' => 4,
            ),
            66 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:shelf_mushroom',
                  'quantity' => 3,
                ),
              ),
              'max_uses' => 12,
            ),
            67 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:waterlily',
                  'quantity' => 5,
                ),
              ),
              'max_uses' => 2,
            ),
            68 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:small_dripleaf_block',
                  'quantity' => 2,
                ),
              ),
              'max_uses' => 5,
            ),
            69 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:sand',
                  'quantity' => 8,
                ),
              ),
              'max_uses' => 8,
            ),
            70 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:red_sand',
                  'quantity' => 4,
                ),
              ),
              'max_uses' => 6,
            ),
            71 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:pointed_dripstone',
                  'quantity' => 2,
                ),
              ),
              'max_uses' => 5,
            ),
            72 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:dirt_with_roots',
                  'quantity' => 2,
                ),
              ),
              'max_uses' => 5,
            ),
            73 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:moss_block',
                  'quantity' => 2,
                ),
              ),
              'max_uses' => 5,
            ),
            74 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:pale_moss_block',
                  'quantity' => 2,
                ),
              ),
              'max_uses' => 5,
            ),
            75 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 2,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:golden_dandelion',
                  'quantity' => 1,
                ),
              ),
              'max_uses' => 12,
            ),
            76 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:name_tag',
                  'quantity' => 1,
                ),
              ),
              'max_uses' => 5,
            ),
            77 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:sulfur_spike',
                  'quantity' => 2,
                ),
              ),
              'max_uses' => 5,
            ),
          ),
        ),
      ),
    ),
  ),
),
			'weapon_smith_trades.json' => array (
  'tiers' => 
  array (
    0 => 
    array (
      'total_exp_required' => 0,
      'groups' => 
      array (
        0 => 
        array (
          'num_to_select' => 1,
          'trades' => 
          array (
            0 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:coal:0',
                  'quantity' => 15,
                  'price_multiplier' => 0.05,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                ),
              ),
              'trader_exp' => 2,
              'max_uses' => 16,
              'reward_exp' => true,
            ),
          ),
        ),
        1 => 
        array (
          'num_to_select' => 1,
          'trades' => 
          array (
            0 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 3,
                  'price_multiplier' => 0.2,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:iron_axe',
                  'quantity' => 1,
                ),
              ),
              'trader_exp' => 1,
              'max_uses' => 12,
              'reward_exp' => true,
            ),
          ),
        ),
        2 => 
        array (
          'num_to_select' => 1,
          'trades' => 
          array (
            0 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 2,
                  'price_multiplier' => 0.2,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:iron_sword',
                  'quantity' => 1,
                  'functions' => 
                  array (
                    0 => 
                    array (
                      'function' => 'enchant_with_levels',
                      'treasure' => false,
                      'levels' => 
                      array (
                        'min' => 5,
                        'max' => 19,
                      ),
                    ),
                  ),
                ),
              ),
              'trader_exp' => 5,
              'max_uses' => 3,
              'reward_exp' => true,
            ),
          ),
        ),
      ),
    ),
    1 => 
    array (
      'total_exp_required' => 10,
      'groups' => 
      array (
        0 => 
        array (
          'num_to_select' => 1,
          'trades' => 
          array (
            0 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:iron_ingot',
                  'quantity' => 4,
                  'price_multiplier' => 0.05,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                ),
              ),
              'trader_exp' => 10,
              'max_uses' => 12,
              'reward_exp' => true,
            ),
          ),
        ),
        1 => 
        array (
          'num_to_select' => 1,
          'trades' => 
          array (
            0 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 36,
                  'price_multiplier' => 0.2,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:bell',
                  'quantity' => 1,
                ),
              ),
              'trader_exp' => 5,
              'max_uses' => 12,
              'reward_exp' => true,
            ),
          ),
        ),
      ),
    ),
    2 => 
    array (
      'total_exp_required' => 70,
      'groups' => 
      array (
        0 => 
        array (
          'num_to_select' => 1,
          'trades' => 
          array (
            0 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:flint',
                  'quantity' => 24,
                  'price_multiplier' => 0.05,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                ),
              ),
              'trader_exp' => 20,
              'max_uses' => 12,
              'reward_exp' => true,
            ),
          ),
        ),
      ),
    ),
    3 => 
    array (
      'total_exp_required' => 150,
      'groups' => 
      array (
        0 => 
        array (
          'num_to_select' => 1,
          'trades' => 
          array (
            0 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:diamond',
                  'quantity' => 1,
                  'price_multiplier' => 0.05,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 1,
                ),
              ),
              'trader_exp' => 30,
              'max_uses' => 12,
              'reward_exp' => true,
            ),
          ),
        ),
        1 => 
        array (
          'num_to_select' => 1,
          'trades' => 
          array (
            0 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 12,
                  'price_multiplier' => 0.2,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:diamond_axe',
                  'quantity' => 1,
                  'functions' => 
                  array (
                    0 => 
                    array (
                      'function' => 'enchant_with_levels',
                      'treasure' => false,
                      'levels' => 
                      array (
                        'min' => 5,
                        'max' => 19,
                      ),
                    ),
                  ),
                ),
              ),
              'trader_exp' => 15,
              'max_uses' => 3,
              'reward_exp' => true,
            ),
          ),
        ),
      ),
    ),
    4 => 
    array (
      'total_exp_required' => 250,
      'groups' => 
      array (
        0 => 
        array (
          'num_to_select' => 1,
          'trades' => 
          array (
            0 => 
            array (
              'wants' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:emerald',
                  'quantity' => 8,
                  'price_multiplier' => 0.2,
                ),
              ),
              'gives' => 
              array (
                0 => 
                array (
                  'item' => 'minecraft:diamond_sword',
                  'quantity' => 1,
                  'functions' => 
                  array (
                    0 => 
                    array (
                      'function' => 'enchant_with_levels',
                      'treasure' => false,
                      'levels' => 
                      array (
                        'min' => 5,
                        'max' => 19,
                      ),
                    ),
                  ),
                ),
              ),
              'trader_exp' => 30,
              'max_uses' => 3,
              'reward_exp' => true,
            ),
          ),
        ),
      ),
    ),
  ),
),
			default => null,
		};
	}
}
