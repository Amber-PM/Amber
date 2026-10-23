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
use pocketmine\block\MovingBlock as MovingBlockType;
use pocketmine\block\piston\PistonMoveRules;
use pocketmine\block\VanillaBlocks;
use pocketmine\data\bedrock\block\BlockStateDeserializeException;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\nbt\NBT;
use pocketmine\nbt\NbtDataException;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\world\format\io\GlobalBlockStateHandlers;
use function is_finite;
use function max;
use function min;

final class MovingBlock extends Spawnable{
	private ?Block $carriedBlock = null;
	private ?CompoundTag $carriedTile = null;
	private ?Vector3 $pistonPosition = null;
	private string $movementId = "";
	private int $direction = Facing::DOWN;
	private bool $expanding = true;
	private float $progress = 0.0;
	private array $collisionBoxes = [];

	public function initialize(Block $block, ?CompoundTag $tile, PistonArm $arm) : void{
		$this->carriedBlock = clone $block;
		$this->collisionBoxes = [];
		$origin = $block->getPosition();
		foreach($block->getCollisionBoxes() as $box){
			$this->collisionBoxes[] = $box->offsetCopy(-$origin->x, -$origin->y, -$origin->z);
		}
		$this->carriedTile = $tile === null ? null : clone $tile;
		$this->pistonPosition = $arm->getPosition()->asVector3();
		$this->movementId = $arm->getMovementId();
		$this->direction = $arm->getMovementDirection();
		$this->expanding = $arm->isExtended();
		$this->clearSpawnCompoundCache();
	}

	public function getCarriedBlock() : Block{
		return clone ($this->carriedBlock ?? VanillaBlocks::AIR());
	}

	public function getLocalCollisionBoxes() : array{
		$boxes = [];
		foreach($this->collisionBoxes as $box){
			$boxes[] = clone $box;
		}
		return $boxes;
	}

	public function belongsTo(PistonArm $arm) : bool{
		return $this->movementId !== "" && $this->movementId === $arm->getMovementId() && $this->pistonPosition !== null && $this->pistonPosition->equals($arm->getPosition());
	}

	public function getOwner() : ?PistonArm{
		$pos = $this->pistonPosition;
		$world = $this->position->getWorld();
		if($pos === null || !$world->isInWorld($pos->getFloorX(), $pos->getFloorY(), $pos->getFloorZ()) || !$world->isChunkLoaded($pos->getFloorX() >> 4, $pos->getFloorZ() >> 4)){
			return null;
		}
		$arm = $world->getTile($pos);
		return $arm instanceof PistonArm && $this->belongsTo($arm) && $arm->isMoving() ? $arm : null;
	}

	public function isOwnerChunkLoaded() : bool{
		return $this->pistonPosition === null || $this->position->getWorld()->isChunkLoaded($this->pistonPosition->getFloorX() >> 4, $this->pistonPosition->getFloorZ() >> 4);
	}

	public function getMovementOffset() : Vector3{
		$arm = $this->getOwner();
		$progress = $arm === null ? $this->progress : ($this->expanding ? $arm->getProgress() : 1.0 - $arm->getProgress());
		[$x, $y, $z] = Facing::OFFSET[$this->direction];
		return new Vector3($x * ($progress - 1.0), $y * ($progress - 1.0), $z * ($progress - 1.0));
	}

	public function setProgress(float $progress) : void{
		$this->progress = $progress;
		$this->clearSpawnCompoundCache();
	}

	public function finish() : bool{
		$world = $this->position->getWorld();
		if($this->isClosed() || !$world->isChunkLoaded($this->position->getFloorX() >> 4, $this->position->getFloorZ() >> 4) || $world->getTile($this->position) !== $this || !$world->getBlock($this->position) instanceof MovingBlockType){
			return false;
		}
		$block = $this->getCarriedBlock();
		$saved = $this->carriedTile === null ? null : clone $this->carriedTile;
		$world->setBlock($this->position, $block, true);
		$tile = $world->getTile($this->position);
		if($saved !== null && $tile !== null && $tile::class === $block->getIdInfo()->getTileClass()){
			$saved->setInt(self::TAG_X, $this->position->getFloorX())->setInt(self::TAG_Y, $this->position->getFloorY())->setInt(self::TAG_Z, $this->position->getFloorZ());
			$tile->readSaveData($saved);
			if($tile instanceof Spawnable){
				$tile->clearSpawnCompoundCache();
			}
		}
		return true;
	}

	protected function onBlockDestroyedHook() : void{
		if($this->finish()){
			$this->position->getWorld()->getTile($this->position)?->onBlockDestroyed();
		}
	}

	public function readSaveData(CompoundTag $nbt) : void{
		$state = $nbt->getCompoundTag("movingBlock");
		if($state === null){
			throw new NbtDataException("Moving block state is missing");
		}
		try{
			$this->carriedBlock = GlobalBlockStateHandlers::getDeserializer()->deserializeBlock(GlobalBlockStateHandlers::getUpgrader()->upgradeBlockStateNbt($state));
		}catch(BlockStateDeserializeException $e){
			throw new NbtDataException($e->getMessage(), 0, $e);
		}
		$this->collisionBoxes = [];
		$boxes = $nbt->getListTag("AmberCollisionBoxes", CompoundTag::class);
		if($boxes !== null){
			if($boxes->count() > 32){
				throw new NbtDataException("Too many moving collision boxes");
			}
			foreach($boxes as $box){
				$values = [];
				foreach(["minX", "minY", "minZ", "maxX", "maxY", "maxZ"] as $key){
					$value = $box->getFloat($key);
					if(!is_finite($value) || $value < -1.0 || $value > 2.0){
						throw new NbtDataException("Invalid moving collision box coordinate");
					}
					$values[] = $value;
				}
				if($values[0] > $values[3] || $values[1] > $values[4] || $values[2] > $values[5]){
					throw new NbtDataException("Inverted moving collision box");
				}
				$this->collisionBoxes[] = new AxisAlignedBB(...$values);
			}
		}else{
			$this->collisionBoxes = $this->carriedBlock->getCollisionBoxes();
		}
		$tileClass = $this->carriedBlock->getIdInfo()->getTileClass();
		if($this->carriedBlock instanceof MovingBlockType || ($tileClass !== null && !PistonMoveRules::isTileClassSupported($tileClass))){
			throw new NbtDataException("Unsupported moving block payload");
		}
		$this->carriedTile = $nbt->getCompoundTag("movingEntity");
		$this->pistonPosition = new Vector3($nbt->getInt("pistonPosX"), $nbt->getInt("pistonPosY"), $nbt->getInt("pistonPosZ"));
		$this->movementId = $nbt->getString("AmberMovementId", "");
		$this->direction = $nbt->getInt("AmberDirection", Facing::DOWN);
		if(!isset(Facing::OFFSET[$this->direction])){
			throw new NbtDataException("Invalid moving block direction");
		}
		$this->expanding = $nbt->getByte("expanding", 1) !== 0;
		$progress = $nbt->getFloat("AmberProgress", 0.0);
		$this->progress = is_finite($progress) ? max(0.0, min(1.0, $progress)) : 0.0;
		$this->clearSpawnCompoundCache();
	}

	protected function writeSaveData(CompoundTag $nbt) : void{
		$boxes = new ListTag([], NBT::TAG_Compound);
		foreach($this->collisionBoxes as $box){
			$boxes->push(CompoundTag::create()->setFloat("minX", $box->minX)->setFloat("minY", $box->minY)->setFloat("minZ", $box->minZ)->setFloat("maxX", $box->maxX)->setFloat("maxY", $box->maxY)->setFloat("maxZ", $box->maxZ));
		}
		$nbt->setTag("AmberCollisionBoxes", $boxes);
		$nbt->setTag("movingBlock", GlobalBlockStateHandlers::getSerializer()->serializeBlock($this->getCarriedBlock())->toNbt());
		if($this->carriedTile !== null){
			$nbt->setTag("movingEntity", clone $this->carriedTile);
		}
		$pos = $this->pistonPosition ?? $this->position;
		$nbt->setInt("pistonPosX", $pos->getFloorX())->setInt("pistonPosY", $pos->getFloorY())->setInt("pistonPosZ", $pos->getFloorZ());
		$nbt->setByte("expanding", $this->expanding ? 1 : 0);
		$nbt->setString("AmberMovementId", $this->movementId)->setInt("AmberDirection", $this->direction)->setFloat("AmberProgress", $this->progress);
	}

	protected function addAdditionalSpawnData(CompoundTag $nbt, TypeConverter $typeConverter) : void{
		$nbt->setTag("movingBlock", $typeConverter->getBlockTranslator()->internalIdToNetworkStateData($this->getCarriedBlock()->getStateId())->toVanillaNbt());
		if($this->carriedTile !== null){
			$visual = clone $this->carriedTile;
			$visual->removeTag(Container::TAG_ITEMS, Container::TAG_LOCK, "AmberMovementId", "AttachedBlocks");
			$nbt->setTag("movingEntity", $visual);
		}
		$pos = $this->pistonPosition ?? $this->position;
		$nbt->setInt("pistonPosX", $pos->getFloorX())->setInt("pistonPosY", $pos->getFloorY())->setInt("pistonPosZ", $pos->getFloorZ());
		$nbt->setByte("expanding", $this->expanding ? 1 : 0);
	}
}
