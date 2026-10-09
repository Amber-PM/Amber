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

use pocketmine\block\utils\DelayedRedstoneReceiver;
use pocketmine\block\utils\StaticSupportTrait;
use pocketmine\block\utils\SupportType;
use pocketmine\entity\Entity;
use pocketmine\event\block\PressurePlateUpdateEvent;
use pocketmine\math\Axis;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Facing;
use pocketmine\world\redstone\RedstoneEngine;
use pocketmine\world\sound\PressurePlateActivateSound;
use pocketmine\world\sound\PressurePlateDeactivateSound;
use function count;
use const PHP_INT_MAX;

abstract class PressurePlate extends Transparent implements DelayedRedstoneReceiver{
	use StaticSupportTrait;

	private readonly int $deactivationDelayTicks;

	public function __construct(
		BlockIdentifier $idInfo,
		string $name,
		BlockTypeInfo $typeInfo,
		int $deactivationDelayTicks = 20 //TODO: make this mandatory in PM6
	){
		parent::__construct($idInfo, $name, $typeInfo);
		$this->deactivationDelayTicks = $deactivationDelayTicks;
	}

	public function isSolid() : bool{
		return false;
	}

	protected function recalculateCollisionBoxes() : array{
		return [];
	}

	public function getSupportType(int $facing) : SupportType{
		return SupportType::NONE;
	}

	private function canBeSupportedAt(Block $block) : bool{
		return $block->getAdjacentSupportType(Facing::DOWN) !== SupportType::NONE;
	}

	public function hasEntityCollision() : bool{
		return true;
	}

	public function onEntityInside(Entity $entity) : bool{
		if(!$this->hasOutputSignal()){
			$this->scheduleUpdate(1);
		}
		return true;
	}

	private function scheduleUpdate(int $delay) : void{
		$world = $this->position->getWorld();
		$engine = $world->getRedstoneEngine();
		if($engine !== null){
			$engine->schedule($this->position, $delay, $this->getStateId());
		}else{
			$world->scheduleDelayedBlockUpdate($this->position, $delay);
		}
	}

	protected function getMaximumSignalEntityCount() : int{
		return PHP_INT_MAX;
	}

	public function onRedstoneScheduledUpdate(RedstoneEngine $engine) : void{
		$this->onScheduledUpdate();
	}

	public function onRedstoneUpdate(RedstoneEngine $engine) : void{
		if($this->hasOutputSignal() && !$engine->isScheduled($this->position)){
			$this->scheduleUpdate($this->deactivationDelayTicks);
		}
	}

	/**
	 * Returns the AABB that entities must intersect to activate the pressure plate.
	 * Note that this is not the same as the collision box (pressure plate doesn't have one), nor the visual bounding
	 * box. The activation area has a height of 0.25 blocks.
	 */
	protected function getActivationBox() : AxisAlignedBB{
		return AxisAlignedBB::one()
			->squash(Axis::X, 1 / 8)
			->squash(Axis::Z, 1 / 8)
			->trim(Facing::UP, 3 / 4)
			->offset($this->position->x, $this->position->y, $this->position->z);
	}

	/**
	 * TODO: make this abstract in PM6
	 */
	protected function hasOutputSignal() : bool{
		return false;
	}

	/**
	 * TODO: make this abstract in PM6
	 *
	 * @param Entity[] $entities
	 *
	 * @return mixed[]
	 * @phpstan-return array{Block, ?bool}
	 */
	protected function calculatePlateState(array $entities) : array{
		return [$this, null];
	}

	/**
	 * Filters entities which don't affect the pressure plate state from the given list.
	 *
	 * @param Entity[] $entities
	 * @return Entity[]
	 */
	protected function filterIrrelevantEntities(array $entities) : array{
		return $entities;
	}

	public function onScheduledUpdate() : void{
		$world = $this->position->getWorld();

		$intersectionAABB = $this->getActivationBox();
		$engine = $world->getRedstoneEngine();
		if($engine !== null && $engine->isTicking()){
			$activatingEntities = $engine->collectEntities($this, $intersectionAABB,
				PressurePlateUpdateEvent::hasHandlers() ? PHP_INT_MAX : $this->getMaximumSignalEntityCount(),
				fn(Entity $entity) : bool => $this->filterIrrelevantEntities([$entity]) !== []
			);
			if($activatingEntities === null){
				$this->scheduleUpdate(1);
				return;
			}
		}else{
			$activatingEntities = $this->filterIrrelevantEntities($world->getNearbyEntities($intersectionAABB));
		}

		//if an irrelevant entity is inside the full cube space of the pressure plate but not activating the plate,
		//it will cause scheduled updates on the plate every tick. We don't want to fire events in this case if the
		//plate is already deactivated.
		if(count($activatingEntities) > 0 || $this->hasOutputSignal()){
			[$newState, $pressedChange] = $this->calculatePlateState($activatingEntities);

			//always call this, in case there are new entities on the plate
			if(PressurePlateUpdateEvent::hasHandlers()){
				$ev = new PressurePlateUpdateEvent($this, $newState, $activatingEntities);
				$ev->call();
				$newState = $ev->isCancelled() ? null : $ev->getNewState();
			}
			if($newState !== null){
				$world->setBlock($this->position, $newState);
				if($pressedChange !== null){
					$world->addSound($this->position, $pressedChange ?
						new PressurePlateActivateSound($this) :
						new PressurePlateDeactivateSound($this)
					);
				}
			}
			if($pressedChange ?? $this->hasOutputSignal()){
				$this->scheduleUpdate($this->deactivationDelayTicks);
			}
		}
	}
}
