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
use pocketmine\addon\entity\EntityFilter;
use pocketmine\addon\entity\FilterContext;
use pocketmine\addon\entity\ai\Goal;
use pocketmine\entity\Entity;

/**
 * minecraft:behavior.hurt_by_target: fight back against whatever hurt the mob.
 */
final class HurtByTargetGoal extends Goal{
	private int $handledHurt = -1;
	private ?Entity $target = null;

	public function getFlags() : int{ return self::FLAG_TARGET; }

	public function canStart() : bool{
		$hurt = $this->mob->getLastHurtTick();
		$damager = $this->mob->getLastDamager();
		if($hurt === $this->handledHurt || $this->now() - $hurt > 20 || !self::isValidTarget($damager)){
			return false;
		}
		$this->handledHurt = $hurt;
		$types = $this->entityTypes(32.0);
		if($types !== []){
			$ok = false;
			foreach($types as $type){
				if(EntityFilter::test($type["filters"], new FilterContext($this->mob, $damager), $this->mob->reporter())){
					$ok = true;
					break;
				}
			}
			if(!$ok){
				return false;
			}
		}
		$this->target = $damager;
		return true;
	}

	public function start() : void{
		$this->mob->setTargetEntity($this->target);
		if((bool) ($this->data["alert_same_type"] ?? false)){
			foreach($this->mob->getWorld()->getNearbyEntities($this->mob->getBoundingBox()->expandedCopy(10, 4, 10), $this->mob) as $other){
				if($other instanceof AddonEntity && $other->getAddonIdentifier() === $this->mob->getAddonIdentifier() && $other->getTargetEntity() === null){
					$other->setTargetEntity($this->target);
				}
			}
		}
	}

	public function canContinue() : bool{
		$target = $this->mob->getTargetEntity();
		return self::isValidTarget($target) && $target === $this->target
			&& $target->getPosition()->distanceSquared($this->mob->getPosition()) < 32 ** 2;
	}

	public function stop() : void{
		if($this->mob->getTargetEntity() === $this->target){
			$this->mob->setTargetEntity(null);
		}
		$this->target = null;
	}
}
