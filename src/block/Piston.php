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

namespace pocketmine\block;

use pocketmine\block\piston\PistonMoveRules;
use pocketmine\block\piston\PistonStructureCalculator;
use pocketmine\block\tile\Chest;
use pocketmine\block\tile\Spawnable;
use pocketmine\block\tile\Tile;
use pocketmine\block\tile\TileFactory;
use pocketmine\block\utils\AnyFacing;
use pocketmine\block\utils\PoweredByRedstone;
use pocketmine\block\utils\RedstoneReceiver;
use pocketmine\block\VanillaBlocks;
use pocketmine\data\runtime\RuntimeDataDescriber;
use pocketmine\item\Item;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\player\Player;
use pocketmine\world\BlockTransaction;
use pocketmine\world\redstone\RedstoneEngine;
use pocketmine\world\sound\PistonExtendSound;
use pocketmine\world\sound\PistonRetractSound;
use pocketmine\world\World;


class Piston extends Transparent implements AnyFacing, PoweredByRedstone, RedstoneReceiver{
	protected int $facing = Facing::DOWN;
	protected bool $extended = false;
	protected bool $powered = false;

	protected function describeBlockOnlyState(RuntimeDataDescriber $w) : void{
		$w->facing($this->facing);
	}

	public function getFacing() : int{
		return $this->facing;
	}

	/** @return $this */
	public function setFacing(int $facing) : self{
		Facing::validate($facing);
		$this->facing = $facing;
		return $this;
	}

	public function isExtended() : bool{
		if($this->position->isValid()){
			$headBlock = $this->position->getWorld()->getBlock($this->position->getSide($this->facing));
			return $headBlock instanceof PistonHead && $headBlock->getFacing() === $this->facing;
		}
		return $this->extended;
	}

	/** @return $this */
	public function setExtended(bool $extended) : self{
		$this->extended = $extended;
		return $this;
	}

	public function isPowered() : bool{
		return $this->powered;
	}

	/** @return $this */
	public function setPowered(bool $powered) : self{
		$this->powered = $powered;
		return $this;
	}

	public function isSticky() : bool{
		return false;
	}

	public function readStateFromWorld() : Block{
		parent::readStateFromWorld();
		if($this->position->isValid()){
			$this->extended = $this->isExtended();
			$engine = $this->position->getWorld()->getRedstoneEngine();
			if($engine !== null){
				$this->powered = $engine->getReceivedPower($this->position) > 0;
			}
		}

		return $this;
	}

	public function place(BlockTransaction $tx, Item $item, Block $blockReplace, Block $blockClicked, int $face, Vector3 $clickVector, ?Player $player = null) : bool{
		if($player !== null){
			$pitch = $player->getLocation()->pitch;
			if($pitch > 45){
				$this->facing = Facing::UP;
			}elseif($pitch < -45){
				$this->facing = Facing::DOWN;
			}else{
				$this->facing = Facing::opposite($player->getHorizontalFacing());
			}
		}

		return parent::place($tx, $item, $blockReplace, $blockClicked, $face, $clickVector, $player);
	}

	public function onRedstoneUpdate(RedstoneEngine $engine) : void{
		$isPowered = $engine->getReceivedPower($this->position) > 0;
		$isExtended = $this->isExtended();
		if($isPowered !== $this->powered || $isPowered !== $isExtended){
			$this->powered = $isPowered;
			if($isPowered && !$isExtended){
				$this->extend();
			}elseif(!$isPowered && $isExtended){
				$this->retract();
			}else{
				$this->extended = $isExtended;
				$engine->getWorld()->setBlock($this->position, $this);
			}
		}
	}

	protected function createPistonHead() : PistonHead{
		return (new PistonHead(new BlockIdentifier(BlockTypeIds::PISTON_HEAD), "Piston Head", new BlockTypeInfo(BlockBreakInfo::instant())))
			->setFacing($this->facing)
			->setSticky($this->isSticky());
	}

	/**
	 * @return Block[]
	 */
	public function getAffectedBlocks() : array{
		if($this->isExtended() && $this->position->isValid()){
			$headPos = $this->position->getSide($this->facing);
			$head = $this->position->getWorld()->getBlock($headPos);
			if($head instanceof PistonHead && $head->getFacing() === $this->facing){
				return [$this, $head];
			}
		}

		return parent::getAffectedBlocks();
	}

	public function onNearbyBlockChange() : void{
		if($this->extended && $this->position->isValid()){
			$headPos = $this->position->getSide($this->facing);
			$head = $this->position->getWorld()->getBlock($headPos);
			if(!$head instanceof PistonHead || $head->getFacing() !== $this->facing){
				$this->position->getWorld()->useBreakOn($this->position);
			}
		}
	}

	/**
	 * @param Vector3[] $blocks
	 */
	protected function validateTilesSupport(World $world, array $blocks) : bool{
		foreach($blocks as $pos){
			$block = $world->getBlock($pos);
			if($block->getIdInfo()->getTileClass() === null){
				continue;
			}
			$tile = $world->getTile($pos);
			if($tile !== null && !PistonMoveRules::isTileSupported($tile)){
				return false;
			}
		}
		return true;
	}

	/**
	 * @param Vector3[] $blocksToMove
	 * @return list<Vector3>
	 */
	protected function moveBlocks(World $world, array $blocksToMove, int $direction) : array{
		/** @var array<string, CompoundTag> $tilePayloads */
		$tilePayloads = [];

		foreach($blocksToMove as $movePos){
			$block = $world->getBlock($movePos);
			if($block->getIdInfo()->getTileClass() === null){
				continue;
			}
			$tile = $world->getTile($movePos);
			if($tile !== null){
				if($tile instanceof Chest && $tile->isPaired()){
					$tile->unpair();
				}
				$posKey = $movePos->getFloorX() . ":" . $movePos->getFloorY() . ":" . $movePos->getFloorZ();
				$tilePayloads[$posKey] = $tile->saveNBT();
			}
		}

		$displacedPositions = [];
		foreach($blocksToMove as $movePos){
			$targetPos = $movePos->getSide($direction);
			$block = $world->getBlock($movePos);
			$posKey = $movePos->getFloorX() . ":" . $movePos->getFloorY() . ":" . $movePos->getFloorZ();
			$savedTileNbt = $tilePayloads[$posKey] ?? null;

			$world->setBlock($movePos, VanillaBlocks::AIR(), false);
			$world->setBlock($targetPos, $block, true);

			if($savedTileNbt !== null){
				$savedTileNbt->setInt(Tile::TAG_X, $targetPos->getFloorX());
				$savedTileNbt->setInt(Tile::TAG_Y, $targetPos->getFloorY());
				$savedTileNbt->setInt(Tile::TAG_Z, $targetPos->getFloorZ());

				$newTile = $world->getTile($targetPos);
				if($newTile !== null){
					$newTile->readSaveData($savedTileNbt);
					if($newTile instanceof Spawnable){
						$newTile->clearSpawnCompoundCache();
					}
				}else{
					$tile = TileFactory::getInstance()->createFromData($world, $savedTileNbt);
					if($tile !== null){
						$world->addTile($tile);
					}
				}
			}

			$displacedPositions[] = $targetPos;
		}

		return $displacedPositions;
	}

	public function extend() : bool{
		$world = $this->position->getWorld();
		$calculator = new PistonStructureCalculator($world, $this->position, $this->facing, true);
		if(!$calculator->calculate()){
			return false;
		}

		$blocksToMove = $calculator->getBlocksToMove();
		if(!$this->validateTilesSupport($world, $blocksToMove)){
			return false;
		}

		foreach($calculator->getBlocksToDestroy() as $destroyPos){
			$world->useBreakOn($destroyPos);
		}

		$displacedPositions = $this->moveBlocks($world, $blocksToMove, $this->facing);

		$headPos = $this->position->getSide($this->facing);
		$world->setBlock($headPos, $this->createPistonHead(), true);
		$displacedPositions[] = $headPos;

		$this->displaceEntities($world, $displacedPositions, $this->facing);

		$this->extended = true;
		$world->setBlock($this->position, $this, true);
		$world->addSound($this->position, new PistonExtendSound());

		return true;
	}

	public function retract() : bool{
		$world = $this->position->getWorld();
		$headPos = $this->position->getSide($this->facing);
		$headBlock = $world->getBlock($headPos);

		$this->extended = false;
		$world->setBlock($this->position, $this, false);

		if($headBlock instanceof PistonHead){
			$world->setBlock($headPos, VanillaBlocks::AIR(), true);
		}

		if($this->isSticky()){
			$calculator = new PistonStructureCalculator($world, $this->position, $this->facing, false);
			if($calculator->calculate()){
				$blocksToMove = $calculator->getBlocksToMove();
				if($this->validateTilesSupport($world, $blocksToMove)){
					$pullDir = Facing::opposite($this->facing);
					$displacedPositions = $this->moveBlocks($world, $blocksToMove, $pullDir);
					$this->displaceEntities($world, $displacedPositions, $pullDir);
				}
			}
		}

		$world->setBlock($this->position, $this, true);
		$world->addSound($this->position, new PistonRetractSound());

		return true;
	}

	/**
	 * @param Vector3[] $positions
	 */
	protected function displaceEntities(World $world, array $positions, int $facing) : void{
		[$dx, $dy, $dz] = Facing::OFFSET[$facing];
		$offset = new Vector3($dx, $dy, $dz);
		/** @var array<int, bool> $checkedEntities */
		$checkedEntities = [];
		foreach($positions as $pos){
			$bb = new AxisAlignedBB($pos->x, $pos->y, $pos->z, $pos->x + 1, $pos->y + 1, $pos->z + 1);
			foreach($world->getNearbyEntities($bb) as $entity){
				$id = $entity->getId();
				if(!isset($checkedEntities[$id]) && $entity->isAlive()){
					$checkedEntities[$id] = true;
					$entity->teleport($entity->getPosition()->addVector($offset));
				}
			}
		}
	}
}
