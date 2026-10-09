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

use pocketmine\block\piston\PistonMovement;
use pocketmine\block\piston\PistonMoveRules;
use pocketmine\block\piston\PistonStructureCalculator;
use pocketmine\block\tile\Chest;
use pocketmine\block\tile\Container;
use pocketmine\block\tile\MovingBlock as MovingBlockTile;
use pocketmine\block\tile\PistonArm;
use pocketmine\block\utils\AnyFacing;
use pocketmine\block\utils\DelayedRedstoneReceiver;
use pocketmine\block\utils\PoweredByRedstone;
use pocketmine\data\runtime\RuntimeDataDescriber;
use pocketmine\item\Item;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\BlockTransaction;
use pocketmine\world\redstone\RedstoneEngine;
use pocketmine\world\sound\PistonExtendSound;
use pocketmine\world\sound\PistonRetractSound;
use pocketmine\world\World;
use function get_class;

class Piston extends Transparent implements AnyFacing, PoweredByRedstone, DelayedRedstoneReceiver{
	protected int $facing = Facing::DOWN;
	protected bool $extended = false;
	protected bool $powered = false;
	private bool $moving = false;

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
			$headPos = $this->position->getSide($this->facing);
			$world = $this->position->getWorld();
			$arm = $world->getChunk($this->position->getFloorX() >> 4, $this->position->getFloorZ() >> 4)?->getTile($this->position->getFloorX() & 15, $this->position->getFloorY(), $this->position->getFloorZ() & 15);
			if($arm instanceof PistonArm && $arm->isMoving()){
				return $arm->isExtended();
			}
			if(!$world->isInWorld($headPos->getFloorX(), $headPos->getFloorY(), $headPos->getFloorZ()) || !$world->isChunkLoaded($headPos->getFloorX() >> 4, $headPos->getFloorZ() >> 4)){
				$tile = $arm;
				if($tile instanceof PistonArm){
					return $tile->isExtended();
				}
				return $this->extended;
			}
			$headBlock = $world->getBlock($headPos);
			return $headBlock instanceof PistonHead && $headBlock->getFacing() === $this->facing && $headBlock->isSticky() === $this->isSticky();
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

	private function receivesPower(RedstoneEngine $engine) : bool{
		foreach(Facing::ALL as $face){
			if($face !== $this->facing && $engine->getPowerFrom($this->position->getSide($face), Facing::opposite($face)) > 0){
				return true;
			}
		}
		return false;
	}

	public function readStateFromWorld() : Block{
		parent::readStateFromWorld();
		if($this->position->isValid()){
			$this->extended = $this->isExtended();
			$tile = $this->position->getWorld()->getTile($this->position);
			$this->moving = $tile instanceof PistonArm && $tile->isMoving();
			$engine = $this->position->getWorld()->getRedstoneEngine();
			if($engine !== null){
				$this->powered = $this->receivesPower($engine);
			}
		}

		return $this;
	}

	protected function recalculateCollisionBoxes() : array{
		if(!$this->extended && !$this->moving){
			return [AxisAlignedBB::one()];
		}
		return [match($this->facing){
			Facing::DOWN => new AxisAlignedBB(0, 0.25, 0, 1, 1, 1),
			Facing::UP => new AxisAlignedBB(0, 0, 0, 1, 0.75, 1),
			Facing::NORTH => new AxisAlignedBB(0, 0, 0.25, 1, 1, 1),
			Facing::SOUTH => new AxisAlignedBB(0, 0, 0, 1, 1, 0.75),
			Facing::WEST => new AxisAlignedBB(0.25, 0, 0, 1, 1, 1),
			Facing::EAST => new AxisAlignedBB(0, 0, 0, 0.75, 1, 1)
		}];
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
		$tile = $engine->getWorld()->getTile($this->position);
		if($tile === null){
			$this->extended = $this->isExtended();
			$engine->updateReceiverState($this);
		}
		if($tile instanceof PistonArm && $tile->isMoving()){
			$engine->schedule($this->position, 1);
			return;
		}
		$isPowered = $this->receivesPower($engine);
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

	public function writeStateToWorld() : void{
		parent::writeStateToWorld();
		$tile = $this->position->getWorld()->getTile($this->position);
		if($tile instanceof PistonArm){
			if(!$tile->isMoving() || $tile->getMovementDirection() !== ($tile->isExtended() ? $this->facing : Facing::opposite($this->facing))){
				$tile->setExtended($this->extended, $this->isSticky());
			}
		}
	}

	private function scheduleAnimation(int $delay = 1) : void{
		$world = $this->position->getWorld();
		if(($engine = $world->getRedstoneEngine()) !== null){
			$engine->schedule($this->position, $delay);
		}else{
			$world->scheduleDelayedBlockUpdate($this->position, $delay);
		}
	}

	public function onRedstoneScheduledUpdate(RedstoneEngine $engine) : void{
		$tile = $engine->getWorld()->getTile($this->position);
		if($tile instanceof PistonArm && $tile->isMoving()){
			if(PistonMovement::tick($tile)){
				if(!$tile->isClosed() && $engine->getWorld()->getTile($this->position) === $tile){
					$this->onRedstoneUpdate($engine);
				}
			}else{
				$this->scheduleAnimation();
			}
		}elseif($tile instanceof PistonArm){
			$this->onRedstoneUpdate($engine);
		}
	}

	public function onScheduledUpdate() : void{
		$world = $this->position->getWorld();
		$tile = $world->getTile($this->position);
		if($tile instanceof PistonArm){
			$engine = $world->getRedstoneEngine();
			if($tile->isMoving()){
				if($engine === null){
					PistonMovement::tick($tile);
				}
				if($tile->isMoving()){
					$this->scheduleAnimation();
				}
			}else{
				$world->setBlock($this->position, $this, false);
				$engine?->onNeighbourUpdate($this);
			}
		}
	}

	protected function createPistonHead() : PistonHead{
		return VanillaBlocks::PISTON_HEAD()->setFacing($this->facing)->setSticky($this->isSticky());
	}

	/**
	 * @return Block[]
	 */
	public function getAffectedBlocks() : array{
		if($this->isExtended() && $this->position->isValid()){
			$headPos = $this->position->getSide($this->facing);
			$world = $this->position->getWorld();
			if(!$world->isChunkLoaded($headPos->getFloorX() >> 4, $headPos->getFloorZ() >> 4)){
				return parent::getAffectedBlocks();
			}
			$head = $world->getBlock($headPos);
			if($head instanceof PistonHead && $head->getFacing() === $this->facing){
				return [$this, $head];
			}
		}

		return parent::getAffectedBlocks();
	}

	public function onNearbyBlockChange() : void{
		if($this->extended && $this->position->isValid()){
			$arm = $this->position->getWorld()->getTile($this->position);
			if($arm instanceof PistonArm && $arm->isMoving()){
				return;
			}
			$headPos = $this->position->getSide($this->facing);
			$world = $this->position->getWorld();
			if(!$world->isInWorld($headPos->getFloorX(), $headPos->getFloorY(), $headPos->getFloorZ()) || !$world->isChunkLoaded($headPos->getFloorX() >> 4, $headPos->getFloorZ() >> 4)){
				return;
			}
			$head = $world->getBlock($headPos);
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
			if($tile === null || !PistonMoveRules::isTileSupported($tile) || get_class($tile) !== $block->getIdInfo()->getTileClass()){
				return false;
			}
		}
		return true;
	}

	protected function moveBlocks(World $world, array $blocksToMove, int $direction) : bool{
		$arm = $world->getTile($this->position);
		if(!$arm instanceof PistonArm || !$arm->isMoving()){
			return false;
		}
		$snapshots = [];
		foreach($blocksToMove as $source){
			$snapshots[] = [$source, $world->getBlock($source), $world->getTile($source)];
		}
		$closed = true;
		foreach($snapshots as [$source, $block, $tile]){
			if($tile instanceof Container && !$tile->closeViewersForMovement()){
				$closed = false;
			}
		}
		if(!$closed){
			$this->cancelPreparation($arm);
			$this->scheduleAnimation();
			return false;
		}
		foreach($snapshots as [$source, $block, $tile]){
			if($tile instanceof Chest && $tile->isPaired()){
				$tile->unpair();
			}
			if($tile instanceof Container){
				foreach($tile->getRealInventory()->getViewers() as $viewer){
					$viewer->removeCurrentWindow();
				}
			}
		}
		if($arm->isClosed() || !$world->isChunkLoaded($this->position->getFloorX() >> 4, $this->position->getFloorZ() >> 4) || $world->getTile($this->position) !== $arm || $world->getBlock($this->position)->getStateId() !== $this->getStateId()){
			return false;
		}
		foreach($snapshots as [$source, $block, $tile]){
			$target = $source->getSide($direction);
			if(!$world->isChunkLoaded($source->getFloorX() >> 4, $source->getFloorZ() >> 4) || !$world->isChunkLoaded($target->getFloorX() >> 4, $target->getFloorZ() >> 4) || $world->getBlock($source)->getStateId() !== $block->getStateId() || $world->getTile($source) !== $tile || ($tile instanceof Container && $tile->getRealInventory()->getViewers() !== [])){
				$this->cancelPreparation($arm);
				return false;
			}
		}
		$calculator = new PistonStructureCalculator($world, $this->position, $this->facing, $arm->isExtended());
		if(($arm->isExtended() || $this->isSticky()) && (!$calculator->calculate() || $calculator->getBlocksToMove() != $blocksToMove || $calculator->getBlocksToDestroy() !== [])){
			$this->cancelPreparation($arm);
			return false;
		}
		$payloads = [];
		foreach($snapshots as [$source, $block, $tile]){
			$payloads[] = [$source, $block, $tile?->saveNBT()];
			$arm->addAttachedBlock($source);
		}
		$arm->broadcastMovement(true);
		foreach($payloads as [$source]){
			$world->setBlock($source, VanillaBlocks::AIR(), false);
		}
		$displacedPositions = [];
		foreach($payloads as [$source, $block, $payload]){
			$target = $source->getSide($direction);
			$world->setBlock($target, VanillaBlocks::MOVING_BLOCK(), false);
			$tile = $world->getTile($target);
			if(!$tile instanceof MovingBlockTile){
				throw new \LogicException("Moving block tile was not created");
			}
			$tile->initialize($block, $payload, $arm);
			$world->invalidateBlockCache($target);
			$displacedPositions[] = $target;
		}
		return true;
	}

	private function cancelPreparation(PistonArm $arm) : void{
		$world = $this->position->getWorld();
		if(!$arm->isClosed() && $world->isChunkLoaded($this->position->getFloorX() >> 4, $this->position->getFloorZ() >> 4) && $world->getTile($this->position) === $arm){
			$base = $world->getBlock($this->position);
			if($base instanceof self){
				$headPos = $this->position->getSide($base->getFacing());
				$head = $world->isChunkLoaded($headPos->getFloorX() >> 4, $headPos->getFloorZ() >> 4) ? $world->getBlock($headPos) : null;
				$arm->setExtended($head instanceof PistonHead && $head->getFacing() === $base->getFacing() && $head->isSticky() === $base->isSticky(), $base->isSticky());
				$world->invalidateBlockCache($this->position);
			}
		}
	}

	public function extend() : bool{
		$world = $this->position->getWorld();
		$calculator = new PistonStructureCalculator($world, $this->position, $this->facing, true);
		if(!$calculator->calculate()){
			return false;
		}

		$arm = $world->getTile($this->position);
		if(!$arm instanceof PistonArm || $arm->isMoving() || $this->isExtended()){
			return false;
		}
		$blocksToMove = $calculator->getBlocksToMove();
		if(!$this->validateTilesSupport($world, $blocksToMove)){
			return false;
		}

		foreach($calculator->getBlocksToDestroy() as $destroyPos){
			foreach(Facing::ALL as $side){
				$neighbor = $destroyPos->getSide($side);
				if($world->isInWorld($neighbor->getFloorX(), $neighbor->getFloorY(), $neighbor->getFloorZ()) && !$world->isChunkLoaded($neighbor->getFloorX() >> 4, $neighbor->getFloorZ() >> 4)){
					return false;
				}
			}
		}
		$arm->beginMovement(true, $this->facing);
		foreach($calculator->getBlocksToDestroy() as $destroyPos){
			if(!$world->useBreakOn($destroyPos)){
				$arm->setExtended(false, $this->isSticky());
				return false;
			}
		}

		$prepared = $this->moveBlocks($world, $blocksToMove, $this->facing);
		if(!$prepared || $arm->isClosed() || !$arm->isMoving()){
			$this->cancelPreparation($arm);
			return false;
		}

		$headPos = $this->position->getSide($this->facing);
		$world->setBlock($headPos, $this->createPistonHead(), true);

		$this->extended = true;
		$world->setBlock($this->position, $this, true);
		$world->invalidateBlockCache($headPos);
		$this->scheduleAnimation();
		$world->addSound($this->position, new PistonExtendSound());

		return true;
	}

	public function retract() : bool{
		$world = $this->position->getWorld();
		$arm = $world->getTile($this->position);
		if(!$arm instanceof PistonArm || $arm->isMoving()){
			return false;
		}
		$headPos = $this->position->getSide($this->facing);
		if(!$world->isInWorld($headPos->getFloorX(), $headPos->getFloorY(), $headPos->getFloorZ()) || !$world->isChunkLoaded($headPos->getFloorX() >> 4, $headPos->getFloorZ() >> 4)){
			return false;
		}
		if(!$this->isExtended()){
			return true;
		}
		$blocksToMove = [];
		if($this->isSticky()){
			$calculator = new PistonStructureCalculator($world, $this->position, $this->facing, false);
			if($calculator->calculate() && $this->validateTilesSupport($world, $calculator->getBlocksToMove())){
				$blocksToMove = $calculator->getBlocksToMove();
			}
		}
		$arm->beginMovement(false, $this->facing);
		$prepared = $this->moveBlocks($world, $blocksToMove, Facing::opposite($this->facing));
		if(!$prepared || $arm->isClosed() || !$arm->isMoving()){
			$this->cancelPreparation($arm);
			return false;
		}
		$this->extended = false;
		$world->setBlock($this->position, $this, true);
		$world->invalidateBlockCache($headPos);
		$this->scheduleAnimation();
		$world->addSound($this->position, new PistonRetractSound());
		return true;
	}

}
