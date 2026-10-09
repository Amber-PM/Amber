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

use pocketmine\block\BlockTypeIds;
use pocketmine\block\tile\MovingBlock;
use pocketmine\block\tile\PistonArm;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use function abs;
use function max;
use function min;
use function spl_object_id;

final class PistonEntityMovement{
	private array $boxes = [];
	private array $honey = [];
	private array $slime = [];
	private array $checked = [];
	private array $origins = [];
	private ?AxisAlignedBB $area = null;
	private ?\Generator $entities = null;

	public function __construct(private PistonArm $arm, array $positions){
		$world = $arm->getPosition()->getWorld();
		[$dx, $dy, $dz] = Facing::OFFSET[$arm->getMovementDirection()];
		foreach($positions as $pos){
			$tile = $world->getTile($pos);
			$block = $world->getBlock($pos);
			$this->origins[] = [$pos, $tile, $block->getStateId()];
			$type = $tile instanceof MovingBlock ? $tile->getCarriedBlock()->getTypeId() : null;
			foreach($world->getBlock($pos)->getCollisionBoxes() as $box){
				$swept = $box->addCoord($dx * 0.5, $dy * 0.5, $dz * 0.5);
				$this->boxes[] = $swept;
				if($type === BlockTypeIds::HONEY_BLOCK){
					$this->honey[] = clone $box;
					$swept = $swept->expandedCopy(0.02, 0.05, 0.02);
				}elseif($type === BlockTypeIds::SLIME){
					$this->slime[] = $swept;
				}
				$area = $this->area;
				$this->area = $area === null ? clone $swept : new AxisAlignedBB(min($area->minX, $swept->minX), min($area->minY, $swept->minY), min($area->minZ, $swept->minZ), max($area->maxX, $swept->maxX), max($area->maxY, $swept->maxY), max($area->maxZ, $swept->maxZ));
			}
		}
		if($this->area !== null){
			$this->entities = $world->iterateEntityCandidates($this->area);
		}
	}

	public function isCurrent() : bool{
		$world = $this->arm->getPosition()->getWorld();
		foreach($this->origins as [$pos, $tile, $stateId]){
			if(!$world->isChunkLoaded($pos->getFloorX() >> 4, $pos->getFloorZ() >> 4) || $world->getTile($pos) !== $tile || $world->getBlock($pos)->getStateId() !== $stateId){
				return false;
			}
		}
		return true;
	}

	public function process() : bool{
		if($this->entities === null || $this->area === null){
			return true;
		}
		$world = $this->arm->getPosition()->getWorld();
		$engine = $world->getRedstoneEngine();
		$limit = $engine === null ? 256 : min(256, max(1, $engine->getRemainingBudget() + 1) * 16);
		$scanned = 0;
		[$dx, $dy, $dz] = Facing::OFFSET[$direction = $this->arm->getMovementDirection()];
		while($scanned < $limit && $this->entities->valid()){
			$entity = $this->entities->current();
			$this->entities->next();
			++$scanned;
			$id = spl_object_id($entity);
			if(isset($this->checked[$id]) || $entity->isClosed() || !$entity->isAlive() || $entity->getWorld() !== $world){
				continue;
			}
			$entityBox = $entity->getBoundingBox();
			if(!$entityBox->intersectsWith($this->area)){
				continue;
			}
			$this->checked[$id] = true;
			$distance = 0.0;
			foreach($this->boxes as $box){
				if($box->intersectsWith($entityBox)){
					$distance = max($distance, match($direction){
						Facing::DOWN => $entityBox->maxY - $box->minY,
						Facing::UP => $box->maxY - $entityBox->minY,
						Facing::NORTH => $entityBox->maxZ - $box->minZ,
						Facing::SOUTH => $box->maxZ - $entityBox->minZ,
						Facing::WEST => $entityBox->maxX - $box->minX,
						Facing::EAST => $box->maxX - $entityBox->minX
					});
				}
			}
			$carried = false;
			if(!($entity instanceof Player && $entity->isFlying())){
				foreach($this->honey as $box){
					$overlapX = $entityBox->maxX > $box->minX && $entityBox->minX < $box->maxX;
					$overlapY = $entityBox->maxY > $box->minY && $entityBox->minY < $box->maxY;
					$overlapZ = $entityBox->maxZ > $box->minZ && $entityBox->minZ < $box->maxZ;
					$touchesX = abs($entityBox->maxX - $box->minX) < 0.02 || abs($entityBox->minX - $box->maxX) < 0.02;
					$touchesZ = abs($entityBox->maxZ - $box->minZ) < 0.02 || abs($entityBox->minZ - $box->maxZ) < 0.02;
					if(
						(abs($entityBox->minY - $box->maxY) < 0.02 && $overlapX && $overlapZ) ||
						($overlapY && (($touchesX && $overlapZ) || ($touchesZ && $overlapX)))
					){
						$carried = true;
						$distance = max(0.5, $distance);
						break;
					}
				}
			}
			if($distance > 0.0){
				$distance = min(0.5, $distance) + ($carried ? 0.0 : 0.01);
				$entity->moveByPiston(new Vector3($dx * $distance, $dy * $distance, $dz * $distance));
				foreach($this->slime as $box){
					if($box->intersectsWith($entityBox) && !($entity instanceof Player && $entity->isFlying())){
						$motion = $entity->getMotion();
						$entity->setMotion($motion->withComponents($dx !== 0 ? (float) $dx : null, $dy !== 0 ? (float) $dy : null, $dz !== 0 ? (float) $dz : null));
						break;
					}
				}
				if($this->arm->isClosed() || !$this->arm->isMoving()){
					break;
				}
			}
		}
		$engine?->consumeBudget(max(0, (int) (($scanned + 15) / 16) - 1));
		return !$this->entities->valid();
	}
}
