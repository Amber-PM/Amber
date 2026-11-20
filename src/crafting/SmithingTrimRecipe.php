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

namespace pocketmine\crafting;

use pocketmine\item\Armor;
use pocketmine\item\ArmorTrim;
use pocketmine\item\Item;

final class SmithingTrimRecipe implements CraftingRecipe{

	public function __construct(
		private RecipeIngredient $template,
		private RecipeIngredient $input,
		private RecipeIngredient $addition
	){}

	public function getTemplate() : RecipeIngredient{
		return $this->template;
	}

	public function getInput() : RecipeIngredient{
		return $this->input;
	}

	public function getAddition() : RecipeIngredient{
		return $this->addition;
	}

	public function applyTrim(Item $input, Item $template, Item $addition) : ?Item{
		if(!$input instanceof Armor){
			return null;
		}
		$trim = ArmorTrim::fromItems($template, $addition);
		if($trim === null){
			return null;
		}
		$result = clone $input;
		$result->setTrim($trim);
		return $result;
	}

	/**
	 * @return RecipeIngredient[]
	 */
	public function getIngredientList() : array{
		return [$this->template, $this->input, $this->addition];
	}

	/**
	 * @return Item[]
	 */
	public function getResultsFor(CraftingGrid $grid) : array{
		return [];
	}

	public function matchesCraftingGrid(CraftingGrid $grid) : bool{
		return false;
	}
}
