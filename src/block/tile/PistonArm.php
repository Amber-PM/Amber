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
use pocketmine\block\Piston;
use pocketmine\block\piston\PistonEntityMovement;
use pocketmine\block\piston\PistonMovement;
use pocketmine\block\piston\PistonStructureCalculator;
use pocketmine\data\bedrock\block\BlockStateNames;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\nbt\NBT;
use pocketmine\nbt\NbtDataException;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\network\mcpe\protocol\BlockActorDataPacket;
use pocketmine\network\mcpe\protocol\types\BlockPosition;
use pocketmine\world\format\io\GlobalBlockStateHandlers;
use function abs;
use function bin2hex;
use function is_finite;
use function max;
use function min;
use function random_bytes;

final class PistonArm extends Spawnable{
	private bool $sticky = false;
	private int $state = 0;
	private float $progress = 0.0;
	private float $lastProgress = 0.0;
	private ?CompoundTag $movement = null;
	private ?PistonEntityMovement $entityMovement = null;

	public function isMoving() : bool{
		return $this->state === 1 || $this->state === 3;
	}

	public function isExtended() : bool{
		return $this->state === 1 || $this->state === 2;
	}

	public function setExtended(bool $extended, bool $sticky) : void{
		$this->movement = null;
		$this->entityMovement = null;
		$this->sticky = $sticky;
		$this->state = $extended ? 2 : 0;
		$this->progress = $this->lastProgress = $extended ? 1.0 : 0.0;
		$this->clearSpawnCompoundCache();
	}

	public function beginMovement(bool $extending, int $facing) : void{
		$this->entityMovement = null;
		$this->state = $extending ? 1 : 3;
		$this->progress = $this->lastProgress = $extending ? 0.0 : 1.0;
		$this->movement = CompoundTag::create()
			->setString("id", bin2hex(random_bytes(16)))
			->setInt("direction", $extending ? $facing : Facing::opposite($facing))
			->setTag("blocks", new ListTag([], NBT::TAG_Int));
		$this->clearSpawnCompoundCache();
	}

	public function getMovementId() : string{
		return $this->movement?->getString("id", "") ?? "";
	}

	public function getMovementDirection() : int{
		return $this->movement?->getInt("direction", Facing::DOWN) ?? Facing::DOWN;
	}

	public function getProgress() : float{
		return $this->progress;
	}

	public function addAttachedBlock(Vector3 $source) : void{
		$list = $this->movement?->getListTag("blocks", IntTag::class);
		if($list === null || $list->count() >= 36){
			throw new \LogicException("Invalid piston movement size");
		}
		$list->push(new IntTag($source->getFloorX()));
		$list->push(new IntTag($source->getFloorY()));
		$list->push(new IntTag($source->getFloorZ()));
		$this->clearSpawnCompoundCache();
	}

	public function getAttachedBlock(int $index) : ?Vector3{
		$list = $this->movement?->getListTag("blocks", IntTag::class);
		if($index < 0 || $list === null || $index * 3 + 2 >= $list->count()){
			return null;
		}
		return new Vector3($list->get($index * 3)->getValue(), $list->get($index * 3 + 1)->getValue(), $list->get($index * 3 + 2)->getValue());
	}

	public function getEntityMovement() : ?PistonEntityMovement{
		return $this->entityMovement;
	}

	public function setEntityMovement(?PistonEntityMovement $movement) : void{
		$this->entityMovement = $movement;
	}

	public function close() : void{
		$this->entityMovement = null;
		parent::close();
	}

	public function advanceProgress() : void{
		$this->lastProgress = $this->progress;
		$this->progress = $this->isExtended() ? min(1.0, $this->progress + 0.5) : max(0.0, $this->progress - 0.5);
		$this->clearSpawnCompoundCache();
	}

	public function broadcastMovement(bool $initial = false) : void{
		$world = $this->position->getWorld();
		TypeConverter::broadcastByTypeConverter($world->getViewersForPosition($this->position), function(TypeConverter $converter) use ($world, $initial) : array{
			return $initial ? $world->createBlockUpdatePackets($converter, [$this->position]) : [BlockActorDataPacket::create(BlockPosition::fromVector3($this->position), $this->getSerializedSpawnCompound($converter))];
		});
	}

	protected function onBlockDestroyedHook() : void{
		if($this->isMoving()){
			PistonMovement::finish($this, true);
		}
	}

	public function readSaveData(CompoundTag $nbt) : void{
		$this->entityMovement = null;
		$this->sticky = $nbt->getByte("Sticky", 0) !== 0;
		$this->state = $nbt->getByte("State", 0);
		if($this->state < 0 || $this->state > 3){
			$this->state = 0;
		}
		$this->progress = $nbt->getFloat("Progress", $this->state === 2 ? 1.0 : 0.0);
		$this->lastProgress = $nbt->getFloat("LastProgress", $this->progress);
		if(!is_finite($this->progress) || !is_finite($this->lastProgress)){
			throw new NbtDataException("Invalid piston progress");
		}
		$this->progress = max(0.0, min(1.0, $this->progress));
		$this->lastProgress = max(0.0, min(1.0, $this->lastProgress));
		$this->movement = $nbt->getCompoundTag("AmberMovement");
		if($this->movement !== null){
			$list = $this->movement->getListTag("blocks", IntTag::class);
			if($list === null || $list->count() > 36 || $list->count() % 3 !== 0 || !isset(Facing::OFFSET[$this->getMovementDirection()])){
				throw new NbtDataException("Invalid piston movement data");
			}
			foreach($list as $coordinate){
				if($coordinate->getValue() < -30000000 || $coordinate->getValue() > 30000000){
					throw new NbtDataException("Invalid piston movement coordinate");
				}
			}
		}
		$range = PistonStructureCalculator::MAX_BLOCK_PUSH_LIMIT + ($this->isExtended() ? 0 : 1);
		for($i = 0; ($source = $this->getAttachedBlock($i)) !== null; ++$i){
			if(abs($source->x - $this->position->x) + abs($source->y - $this->position->y) + abs($source->z - $this->position->z) > $range){
				throw new NbtDataException("Piston attachment outside movement range");
			}
		}

		$this->clearSpawnCompoundCache();
	}

	protected function writeSaveData(CompoundTag $nbt) : void{
		$nbt->setByte("Sticky", $this->sticky ? 1 : 0);
		$nbt->setByte("State", $this->state);
		$nbt->setByte("NewState", $this->state);
		$nbt->setFloat("Progress", $this->progress);
		$nbt->setFloat("LastProgress", $this->lastProgress);
		$nbt->setTag("AttachedBlocks", clone ($this->movement?->getListTag("blocks", IntTag::class) ?? new ListTag([], NBT::TAG_Int)));
		if($this->movement !== null){
			$nbt->setTag("AmberMovement", clone $this->movement);
		}
		$nbt->setTag("BreakBlocks", new ListTag([], NBT::TAG_Int));
	}

	protected function addAdditionalSpawnData(CompoundTag $nbt, TypeConverter $typeConverter) : void{
		$this->writeSaveData($nbt);
		$nbt->removeTag("AmberMovement");
	}

	public function getRenderUpdateBugWorkaroundStateProperties(Block $block) : array{
		if(!$block instanceof Piston){
			return [];
		}
		$direction = GlobalBlockStateHandlers::getSerializer()->serializeBlock($block)->getStates()[BlockStateNames::FACING_DIRECTION];
		return $direction instanceof IntTag ? [BlockStateNames::FACING_DIRECTION => new IntTag($direction->getValue() ^ 1)] : [];
	}
}
