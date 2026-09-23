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

namespace pocketmine\inventory;

use pocketmine\crafting\CraftingManagerFromDataHelper;
use pocketmine\data\bedrock\BedrockDataFiles;
use pocketmine\inventory\json\CreativeGroupData;
use pocketmine\item\Item;
use pocketmine\lang\KnownTranslationKeys;
use pocketmine\lang\Translatable;
use pocketmine\utils\DestructorCallbackTrait;
use pocketmine\utils\ObjectSet;
use pocketmine\utils\SingletonTrait;
use Symfony\Component\Filesystem\Path;
use function array_filter;
use function array_map;

final class CreativeInventory{
	use SingletonTrait;
	use DestructorCallbackTrait;

	private const EDUCATION_GROUP_NAMES = [
		KnownTranslationKeys::ITEMGROUP_NAME_ELEMENT => true,
		KnownTranslationKeys::ITEMGROUP_NAME_CHEMISTRYTABLE => true,
		KnownTranslationKeys::ITEMGROUP_NAME_COMPOUNDS => true,
		KnownTranslationKeys::ITEMGROUP_NAME_PRODUCTS => true
	];

	/**
	 * @var CreativeInventoryEntry[]
	 * @phpstan-var array<int, CreativeInventoryEntry>
	 */
	private array $creative = [];
	/** @var array<int, true> */
	private array $builtInEducationEntryIndexes = [];

	/** @phpstan-var ObjectSet<\Closure() : void> */
	private ObjectSet $contentChangedCallbacks;

	private function __construct(){
		$this->contentChangedCallbacks = new ObjectSet();

		foreach([
			"construction" => CreativeCategory::CONSTRUCTION,
			"nature" => CreativeCategory::NATURE,
			"equipment" => CreativeCategory::EQUIPMENT,
			"items" => CreativeCategory::ITEMS,
		] as $categoryId => $categoryEnum){
			$groups = CraftingManagerFromDataHelper::loadJsonArrayOfObjectsFile(
				Path::join(BedrockDataFiles::CREATIVE, $categoryId . ".json"),
				CreativeGroupData::class
			);

			foreach($groups as $groupData){
				$isEducationGroup = isset(self::EDUCATION_GROUP_NAMES[$groupData->group_name]);
				$icon = $groupData->group_icon === null ? null : CraftingManagerFromDataHelper::deserializeItemStack($groupData->group_icon);

				$group = $icon === null ? null : new CreativeGroup(
					new Translatable($groupData->group_name),
					$icon
				);

				$items = array_filter(array_map(static fn($itemStack) => CraftingManagerFromDataHelper::deserializeItemStack($itemStack), $groupData->items));

				foreach($items as $item){
					$index = count($this->creative);
					$this->add($item, $categoryEnum, $group);
					if($isEducationGroup){
						$this->builtInEducationEntryIndexes[$index] = true;
					}
				}
			}
		}
	}

	/**
	 * Removes all previously added items from the creative menu.
	 * Note: Players who are already online when this is called will not see this change.
	 */
	public function clear() : void{
		$this->creative = [];
		$this->builtInEducationEntryIndexes = [];
		$this->onContentChange();
	}

	/**
	 * Removes built-in Education entries from the creative menu during server startup.
	 * @internal
	 */
	public function removeEducationEditionContent() : void{
		$changed = false;
		foreach($this->builtInEducationEntryIndexes as $index => $_){
			if(isset($this->creative[$index])){
				unset($this->creative[$index]);
				$changed = true;
			}
		}
		$this->builtInEducationEntryIndexes = [];
		if($changed){
			$this->onContentChange();
		}
	}

	/**
	 * @return Item[]
	 * @phpstan-return array<int, Item>
	 */
	public function getAll() : array{
		return array_map(fn(CreativeInventoryEntry $entry) => $entry->getItem(), $this->creative);
	}

	/**
	 * @return CreativeInventoryEntry[]
	 * @phpstan-return array<int, CreativeInventoryEntry>
	 */
	public function getAllEntries() : array{
		return $this->creative;
	}

	public function getItem(int $index) : ?Item{
		return $this->getEntry($index)?->getItem();
	}

	public function getEntry(int $index) : ?CreativeInventoryEntry{
		return $this->creative[$index] ?? null;
	}

	public function getItemIndex(Item $item) : int{
		foreach($this->creative as $i => $d){
			if($d->matchesItem($item)){
				return $i;
			}
		}

		return -1;
	}

	/**
	 * Adds an item to the creative menu.
	 * Note: Players who are already online when this is called will not see this change.
	 */
	public function add(Item $item, CreativeCategory $category = CreativeCategory::ITEMS, ?CreativeGroup $group = null) : void{
		$this->creative[] = new CreativeInventoryEntry($item, $category, $group);
		$this->onContentChange();
	}

	/**
	 * Removes an item from the creative menu.
	 * Note: Players who are already online when this is called will not see this change.
	 */
	public function remove(Item $item) : void{
		$index = $this->getItemIndex($item);
		if($index !== -1){
			unset($this->creative[$index]);
			unset($this->builtInEducationEntryIndexes[$index]);
			$this->onContentChange();
		}
	}

	public function contains(Item $item) : bool{
		return $this->getItemIndex($item) !== -1;
	}

	/** @phpstan-return ObjectSet<\Closure() : void> */
	public function getContentChangedCallbacks() : ObjectSet{
		return $this->contentChangedCallbacks;
	}

	private function onContentChange() : void{
		foreach($this->contentChangedCallbacks as $callback){
			$callback();
		}
	}
}
