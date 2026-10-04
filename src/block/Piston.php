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

use pocketmine\block\piston\PistonStructureCalculator;
use pocketmine\block\utils\AnyFacing;
use pocketmine\block\utils\PoweredByRedstone;
use pocketmine\block\utils\RedstoneReceiver;
use pocketmine\data\runtime\RuntimeDataDescriber;
use pocketmine\item\Item;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\BlockTransaction;
use pocketmine\world\redstone\RedstoneEngine;
use pocketmine\world\sound\PistonExtendSound;
use pocketmine\world\sound\PistonRetractSound;
use pocketmine\block\VanillaBlocks;


class Piston extends Transparent implements AnyFacing, PoweredByRedstone, RedstoneReceiver{
	protected int $facing = Facing::DOWN;
	protected bool $extended = false;
	protected bool $powered = false;

	protected function describeBlockOnlyState(RuntimeDataDescriber $w) : void{
		$w->facing($this->facing);
		$w->bool($this->extended);
		$w->bool($this->powered);
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
		if($isPowered !== $this->powered){
			$this->powered = $isPowered;
			if($isPowered && !$this->extended){
				$this->extend();
			}elseif(!$isPowered && $this->extended){
				$this->retract();
			}else{
				$engine->getWorld()->setBlock($this->position, $this);
			}
		}
	}

	protected function createPistonHead() : PistonHead{
		return (new PistonHead(new BlockIdentifier(BlockTypeIds::PISTON_HEAD), "Piston Head", new BlockTypeInfo(BlockBreakInfo::instant())))
			->setFacing($this->facing)
			->setSticky($this->isSticky());
	}

	public function extend() : bool{
		$world = $this->position->getWorld();
		$calculator = new PistonStructureCalculator($world, $this->position, $this->facing, true);
		if(!$calculator->calculate()){
			return false;
		}

		foreach($calculator->getBlocksToDestroy() as $destroyPos){
			$world->useBreakOn($destroyPos);
		}

		foreach($calculator->getBlocksToMove() as $movePos){
			$targetPos = $movePos->getSide($this->facing);
			$block = $world->getBlock($movePos);
			$world->setBlock($movePos, VanillaBlocks::AIR(), false);
			$world->setBlock($targetPos, $block, true);
		}

		$headPos = $this->position->getSide($this->facing);
		$world->setBlock($headPos, $this->createPistonHead(), true);

		$this->extended = true;
		$world->setBlock($this->position, $this, true);
		$world->addSound($this->position, new PistonExtendSound());

		return true;
	}

	public function retract() : bool{
		$world = $this->position->getWorld();
		$headPos = $this->position->getSide($this->facing);
		$headBlock = $world->getBlock($headPos);

		if($headBlock instanceof PistonHead){
			$world->setBlock($headPos, VanillaBlocks::AIR(), true);
		}

		if($this->isSticky()){
			$calculator = new PistonStructureCalculator($world, $this->position, $this->facing, false);
			if($calculator->calculate()){
				$pullDir = Facing::opposite($this->facing);
				foreach($calculator->getBlocksToMove() as $movePos){
					$targetPos = $movePos->getSide($pullDir);
					$block = $world->getBlock($movePos);
					$world->setBlock($movePos, VanillaBlocks::AIR(), false);
					$world->setBlock($targetPos, $block, true);
				}
			}
		}

		$this->extended = false;
		$world->setBlock($this->position, $this, true);
		$world->addSound($this->position, new PistonRetractSound());

		return true;
	}
}
