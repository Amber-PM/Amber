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

namespace pocketmine\world\hopper;

use pocketmine\block\Hopper;
use pocketmine\block\ShulkerBox as ShulkerBoxBlock;
use pocketmine\block\tile\Barrel;
use pocketmine\block\tile\Chest;
use pocketmine\block\tile\Container;
use pocketmine\block\tile\Dispenser as TileDispenser;
use pocketmine\block\tile\Dropper as TileDropper;
use pocketmine\block\tile\Furnace;
use pocketmine\block\tile\Hopper as HopperTile;
use pocketmine\block\tile\ShulkerBox;
use pocketmine\block\tile\Tile;
use pocketmine\entity\object\ItemEntity;
use pocketmine\inventory\Inventory;
use pocketmine\item\Item;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Facing;
use pocketmine\world\World;
use function min;
use function range;

/**
 * Moves items for the hoppers of one world: every transfer interval each unlocked hopper pushes one item into the
 * container it faces, pulls one item from the container above it, and picks up dropped items lying on it.
 *
 * Chests, barrels, shulker boxes, hoppers, furnaces, dispensers and droppers are supported. Furnaces take items to smelt from above and fuel
 * from the side, and give up only their output.
 */
final class HopperTicker{
	/** Game ticks between transfers, as in the game. */
	public const TRANSFER_INTERVAL = 8;

	private const FURNACE_INPUT = 0;
	private const FURNACE_FUEL = 1;
	private const FURNACE_OUTPUT = 2;

	/** @var array<int, true> hashes of the hopper tiles in loaded chunks */
	private array $hoppers = [];
	private int $moved = 0;

	public function __construct(private World $world){}

	public function onTileAdded(Tile $tile) : void{
		if($tile instanceof HopperTile){
			$pos = $tile->getPosition();
			$this->hoppers[World::blockHash($pos->getFloorX(), $pos->getFloorY(), $pos->getFloorZ())] = true;
		}
	}

	/** Items moved by hoppers since the world was loaded. */
	public function getMovedCount() : int{ return $this->moved; }

	public function tick(int $currentTick) : void{
		if($currentTick % self::TRANSFER_INTERVAL !== 0){
			return;
		}
		foreach($this->hoppers as $hash => $_){
			World::getBlockXYZ($hash, $x, $y, $z);
			if(!$this->world->isChunkLoaded($x >> 4, $z >> 4)){
				unset($this->hoppers[$hash]); //re-added with the tile when the chunk loads again
				continue;
			}
			$tile = $this->world->getTileAt($x, $y, $z);
			$block = $this->world->getBlockAt($x, $y, $z);
			if(!$tile instanceof HopperTile || !$block instanceof Hopper){
				unset($this->hoppers[$hash]);
				continue;
			}
			if($block->isPowered()){
				continue;
			}
			$inventory = $tile->getInventory();
			$this->push($inventory, $x, $y, $z, $block->getFacing());
			if(!$this->pullFromAbove($inventory, $x, $y, $z)){
				$this->collectItems($inventory, $x, $y, $z);
			}
		}
	}

	private function push(Inventory $hopper, int $x, int $y, int $z, int $facing) : void{
		[$dx, $dy, $dz] = Facing::OFFSET[$facing];
		if(!$this->world->isChunkLoaded(($x + $dx) >> 4, ($z + $dz) >> 4)){
			return; //getTileAt() would load the chunk
		}
		$target = $this->world->getTileAt($x + $dx, $y + $dy, $z + $dz);
		if(!self::isSupported($target)){
			return;
		}
		for($slot = 0, $size = $hopper->getSize(); $slot < $size; ++$slot){
			$item = $hopper->getItem($slot);
			if($item->isNull()){
				continue;
			}
			if(self::insertOne($target, $item, Facing::opposite($facing))){
				$hopper->setItem($slot, $item->setCount($item->getCount() - 1));
				++$this->moved;
				return;
			}
		}
	}

	private function pullFromAbove(Inventory $hopper, int $x, int $y, int $z) : bool{
		$source = $this->world->getTileAt($x, $y + 1, $z);
		if(!self::isSupported($source)){
			return $source instanceof Container;
		}
		if($source instanceof HopperTile){
			$above = $this->world->getBlockAt($x, $y + 1, $z);
			if($above instanceof Hopper && $above->getFacing() === Facing::DOWN){
				return true; //it pushes into this one itself
			}
		}
		$inventory = $source->getInventory();
		$slots = $source instanceof Furnace ? [self::FURNACE_OUTPUT] : range(0, $inventory->getSize() - 1);
		foreach($slots as $slot){
			$item = $inventory->getItem($slot);
			if($item->isNull()){
				continue;
			}
			$one = (clone $item)->setCount(1);
			if($hopper->canAddItem($one)){
				$hopper->addItem($one);
				$inventory->setItem($slot, $item->setCount($item->getCount() - 1));
				++$this->moved;
				return true;
			}
		}
		return true;
	}

	private function collectItems(Inventory $hopper, int $x, int $y, int $z) : void{
		$above = $this->world->getBlockAt($x, $y + 1, $z);
		if($above->isSolid() && $above->isFullCube()){
			return;
		}
		$area = new AxisAlignedBB($x, $y + 0.625, $z, $x + 1, $y + 2, $z + 1);
		foreach($this->world->getNearbyEntities($area) as $entity){
			if(!$entity instanceof ItemEntity || $entity->isFlaggedForDespawn() || $entity->isClosed()){
				continue;
			}
			$item = $entity->getItem();
			$count = min($item->getCount(), $hopper->getAddableItemQuantity($item));
			if($count <= 0){
				continue;
			}
			$hopper->addItem((clone $item)->setCount($count));
			if($count >= $item->getCount()){
				$entity->flagForDespawn();
			}else{
				$entity->setStackSize($item->getCount() - $count);
			}
			$this->moved += $count;
			return;
		}
	}

	/**
	 * @phpstan-assert-if-true Chest|Barrel|ShulkerBox|HopperTile|Furnace|TileDispenser|TileDropper $tile
	 */
	private static function isSupported(?Tile $tile) : bool{
		return $tile instanceof Chest ||
			$tile instanceof Barrel ||
			$tile instanceof ShulkerBox ||
			$tile instanceof HopperTile ||
			$tile instanceof Furnace ||
			$tile instanceof TileDispenser ||
			$tile instanceof TileDropper;
	}

	/**
	 * Puts one of the item into the container, coming in through its face $side.
	 */
	public static function insertOne(Container $target, Item $item, int $side) : bool{
		$one = (clone $item)->setCount(1);
		$inventory = $target->getInventory();
		if($target instanceof Furnace){
			if($side === Facing::UP){
				$slot = self::FURNACE_INPUT;
			}elseif($side !== Facing::DOWN && $item->getFuelTime() > 0){
				$slot = self::FURNACE_FUEL;
			}else{
				return false;
			}
			$existing = $inventory->getItem($slot);
			if($existing->isNull()){
				$inventory->setItem($slot, $one);
				return true;
			}
			if($existing->canStackWith($one) && $existing->getCount() < min($inventory->getMaxStackSize(), $existing->getMaxStackSize())){
				$inventory->setItem($slot, $existing->setCount($existing->getCount() + 1));
				return true;
			}
			return false;
		}
		if($target instanceof ShulkerBox && $item->getBlock() instanceof ShulkerBoxBlock){
			return false;
		}
		if(!$inventory->canAddItem($one)){
			return false;
		}
		$inventory->addItem($one);
		return true;
	}
}
