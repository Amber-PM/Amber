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

namespace pocketmine\block\piston;

use pocketmine\block\Chest;
use pocketmine\block\PistonHead;
use pocketmine\block\tile\MovingBlock as MovingBlockTile;
use pocketmine\block\tile\PistonArm;
use pocketmine\block\VanillaBlocks;

final class PistonMovement{
	public static function tick(PistonArm $arm) : bool{
		if($arm->isClosed() || !$arm->isMoving() || !self::isAvailable($arm)){
			return false;
		}
		$world = $arm->getPosition()->getWorld();
		$direction = $arm->getMovementDirection();
		if($arm->getMovementId() === ""){
			return self::finish($arm);
		}
		$positions = [];
		for($i = 0; ($source = $arm->getAttachedBlock($i)) !== null; ++$i){
			$destination = $source->getSide($direction);
			$tile = $world->getTile($destination);
			if($tile instanceof MovingBlockTile && $tile->belongsTo($arm)){
				$positions[] = $destination;
			}
		}
		$base = $world->getBlock($arm->getPosition());
		if($base instanceof \pocketmine\block\Piston){
			$head = $arm->getPosition()->getSide($base->getFacing());
			if($world->getBlock($head) instanceof PistonHead){
				$positions[] = $head;
			}
		}
		$job = $arm->getEntityMovement();
		if($job === null || !$job->isCurrent()){
			$job = new PistonEntityMovement($arm, $positions);
			$arm->setEntityMovement($job);
		}
		if(!$job->process() || $arm->isClosed() || !$arm->isMoving()){
			return false;
		}
		$arm->setEntityMovement(null);
		$arm->advanceProgress();
		$progress = $arm->isExtended() ? $arm->getProgress() : 1.0 - $arm->getProgress();
		foreach($positions as $pos){
			$tile = $world->getTile($pos);
			if($tile instanceof MovingBlockTile && $tile->belongsTo($arm)){
				$tile->setProgress($progress);
			}
			$world->invalidateBlockCache($pos);
		}
		$world->invalidateBlockCache($arm->getPosition());
		if($progress >= 1.0){
			return self::finish($arm);
		}
		$arm->broadcastMovement();
		return false;
	}

	private static function isAvailable(PistonArm $arm) : bool{
		$world = $arm->getPosition()->getWorld();
		if(!$world->isChunkLoaded($arm->getPosition()->getFloorX() >> 4, $arm->getPosition()->getFloorZ() >> 4) || $world->getTile($arm->getPosition()) !== $arm){
			return false;
		}
		$base = $world->getBlock($arm->getPosition());
		if(!$base instanceof \pocketmine\block\Piston){
			return false;
		}
		$head = $arm->getPosition()->getSide($base->getFacing());
		if(!$world->isChunkLoaded($head->getFloorX() >> 4, $head->getFloorZ() >> 4)){
			return false;
		}
		for($i = 0; ($source = $arm->getAttachedBlock($i)) !== null; ++$i){
			$dest = $source->getSide($arm->getMovementDirection());
			if(!$world->isInWorld($dest->getFloorX(), $dest->getFloorY(), $dest->getFloorZ()) || !$world->isChunkLoaded($dest->getFloorX() >> 4, $dest->getFloorZ() >> 4) || !$world->isChunkLoaded($source->getFloorX() >> 4, $source->getFloorZ() >> 4)){
				return false;
			}
		}
		return true;
	}

	public static function finish(PistonArm $arm, bool $interrupted = false) : bool{
		if(!$interrupted && !self::isAvailable($arm)){
			return false;
		}
		$world = $arm->getPosition()->getWorld();
		$direction = $arm->getMovementDirection();
		$extended = $arm->isExtended();
		$restored = [];
		for($i = 0; ($source = $arm->getAttachedBlock($i)) !== null; ++$i){
			$dest = $source->getSide($direction);
			if(!$world->isChunkLoaded($dest->getFloorX() >> 4, $dest->getFloorZ() >> 4)){
				continue;
			}
			$tile = $world->getTile($dest);
			if($tile instanceof MovingBlockTile && $tile->belongsTo($arm) && $tile->finish()){
				$restored[] = $dest;
			}
			if($world->isChunkLoaded($source->getFloorX() >> 4, $source->getFloorZ() >> 4)){
				$world->notifyNeighbourBlockUpdate($source);
			}
		}
		$base = $world->getBlock($arm->getPosition());
		if($base instanceof \pocketmine\block\Piston){
			$headPos = $arm->getPosition()->getSide($base->getFacing());
			if(!$extended && $world->isChunkLoaded($headPos->getFloorX() >> 4, $headPos->getFloorZ() >> 4) && ($head = $world->getBlock($headPos)) instanceof PistonHead && $head->getFacing() === $base->getFacing()){
				$world->setBlock($headPos, VanillaBlocks::AIR());
			}
			$arm->setExtended($extended, $base->isSticky());
			$world->invalidateBlockCache($headPos);
		}
		$world->invalidateBlockCache($arm->getPosition());
		foreach($restored as $pos){
			$block = $world->getBlock($pos);
			if($block instanceof Chest){
				$block->onPostPlace();
			}
		}
		$arm->broadcastMovement();
		return true;
	}

}
