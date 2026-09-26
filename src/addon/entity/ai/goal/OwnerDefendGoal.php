<?php

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketMine Team
 * @link http://www.pocketmine.net/
 *
 *
 */

declare(strict_types=1);

namespace pocketmine\addon\entity\ai\goal;

use pocketmine\addon\entity\AddonEntity;
use pocketmine\addon\entity\CombatMemory;
use pocketmine\addon\entity\ai\Goal;
use pocketmine\entity\Entity;

/**
 * minecraft:behavior.owner_hurt_by_target / owner_hurt_target: tamed mobs fight for their owner.
 */
final class OwnerDefendGoal extends Goal{
	private int $handled = -1;
	private ?Entity $target = null;

	public function __construct(AddonEntity $mob, array $data, int $priority, private bool $ownerHurt){
		parent::__construct($mob, $data, $priority);
	}

	public function getFlags() : int{ return self::FLAG_TARGET; }

	public function canStart() : bool{
		$owner = $this->mob->isTamed() && !$this->mob->isSitting() ? $this->mob->getOwningEntity() : null;
		if($owner === null){
			return false;
		}
		$memory = $this->ownerHurt ? CombatMemory::lastHurtBy($owner) : CombatMemory::lastAttacked($owner);
		if($memory === null || $memory[1] === $this->handled || $this->now() - $memory[1] > 40 || $memory[0] === $this->mob || !self::isValidTarget($memory[0])){
			return false;
		}
		$this->handled = $memory[1];
		$this->target = $memory[0];
		return true;
	}

	public function start() : void{
		$this->mob->setTargetEntity($this->target);
	}

	public function canContinue() : bool{
		return self::isValidTarget($this->target) && $this->mob->getTargetEntity() === $this->target;
	}

	public function stop() : void{
		if($this->mob->getTargetEntity() === $this->target){
			$this->mob->setTargetEntity(null);
		}
		$this->target = null;
	}
}
