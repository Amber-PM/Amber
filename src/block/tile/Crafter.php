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

namespace pocketmine\block\tile;

use pocketmine\block\Block;
use pocketmine\block\Crafter as BlockCrafter;
use pocketmine\block\inventory\CrafterInventory;
use pocketmine\crafting\CraftingGrid;
use pocketmine\entity\Location;
use pocketmine\entity\object\ItemEntity;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\Server;
use pocketmine\world\sound\ClickFailSound;
use pocketmine\world\sound\ClickSound;
use pocketmine\world\World;

class Crafter extends Spawnable implements Container, Nameable{
	use ContainerTrait;
	use NameableTrait;

	private CrafterInventory $inventory;
	private bool $triggered = false;

	public function __construct(World $world, Vector3 $pos){
		parent::__construct($world, $pos);
		$this->inventory = new CrafterInventory($this->position);
	}

	public function readSaveData(CompoundTag $nbt) : void{
		$this->loadItems($nbt);
		$this->loadName($nbt);
		$this->inventory->setDisabledSlots($nbt->getInt("disabled_slots", 0));
		$this->triggered = $nbt->getByte("triggered", 0) === 1;
	}

	protected function writeSaveData(CompoundTag $nbt) : void{
		$this->saveItems($nbt);
		$this->saveName($nbt);
		$nbt->setInt("disabled_slots", $this->inventory->getDisabledSlots());
		$nbt->setByte("triggered", $this->triggered ? 1 : 0);
	}

	public function close() : void{
		if(!$this->closed){
			$this->inventory->removeAllViewers();
			parent::close();
		}
	}

	public function getDefaultName() : string{
		return "Crafter";
	}

	public function getInventory() : CrafterInventory{
		return $this->inventory;
	}

	public function getRealInventory() : CrafterInventory{
		return $this->inventory;
	}

	public function isTriggered() : bool{
		return $this->triggered;
	}

	public function setTriggered(bool $triggered) : void{
		$this->triggered = $triggered;
	}

	/**
	 * Executes a single automated crafting operation.
	 *
	 * @return bool True if a recipe was matched and crafted, false otherwise
	 */
	public function craft() : bool{
		$block = $this->getBlock();
		$facing = $block instanceof BlockCrafter ? $block->getFacing() : Facing::NORTH;

		$grid = new class(CraftingGrid::SIZE_BIG) extends CraftingGrid{};

		$hasItems = false;
		for($slot = 0; $slot < 9; ++$slot){
			if(!$this->inventory->isSlotDisabled($slot)){
				$item = $this->inventory->getItem($slot);
				if(!$item->isNull()){
					$hasItems = true;
					$grid->setItem($slot, clone $item);
					continue;
				}
			}
			$grid->setItem($slot, VanillaItems::AIR());
		}

		if(!$hasItems){
			$this->position->getWorld()->addSound($this->position->add(0.5, 0.5, 0.5), new ClickFailSound());
			return false;
		}

		$craftingManager = Server::getInstance()->getCraftingManager();
		$matchedRecipe = null;
		foreach($craftingManager->getCraftingRecipeIndex() as $recipe){
			if($recipe->matchesCraftingGrid($grid)){
				$matchedRecipe = $recipe;
				break;
			}
		}

		if($matchedRecipe === null){
			$this->position->getWorld()->addSound($this->position->add(0.5, 0.5, 0.5), new ClickFailSound());
			return false;
		}

		$results = $matchedRecipe->getResultsFor($grid);
		if(count($results) === 0){
			$this->position->getWorld()->addSound($this->position->add(0.5, 0.5, 0.5), new ClickFailSound());
			return false;
		}

		// Consume 1 ingredient from each active slot in grid
		for($slot = 0; $slot < 9; ++$slot){
			if(!$this->inventory->isSlotDisabled($slot)){
				$item = $this->inventory->getItem($slot);
				if(!$item->isNull()){
					$item->pop();
					if($item->getCount() === 0){
						$this->inventory->setItem($slot, VanillaItems::AIR());
					}else{
						$this->inventory->setItem($slot, $item);
					}
				}
			}
		}

		// Eject results into world or adjacent container
		$targetPos = $this->position->getSide($facing);
		$targetTile = $this->position->getWorld()->getTile($targetPos);
		$targetInventory = $targetTile instanceof Container ? $targetTile->getInventory() : null;

		foreach($results as $result){
			$toEject = clone $result;
			if($targetInventory !== null){
				$excess = $targetInventory->addItem($toEject);
				if(count($excess) === 0){
					continue;
				}
				$toEject = $excess[0];
			}

			// Dispense into world as ItemEntity
			$facingVector = new Vector3(...Facing::OFFSET[$facing]);
			$spawnPos = $this->position->add(0.5, 0.5, 0.5)->addVector($facingVector->multiply(0.7));
			$motion = $facingVector->multiply(0.3)->add(
				(mt_rand(-10, 10) / 100.0) * 0.1,
				0.1,
				(mt_rand(-10, 10) / 100.0) * 0.1
			);

			$itemEntity = new ItemEntity(Location::fromObject($spawnPos, $this->position->getWorld()), $toEject);
			$itemEntity->setMotion($motion);
			$itemEntity->spawnToAll();
		}

		$this->position->getWorld()->addSound($this->position->add(0.5, 0.5, 0.5), new ClickSound());
		return true;
	}
}
