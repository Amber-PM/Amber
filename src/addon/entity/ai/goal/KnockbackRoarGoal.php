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

use pocketmine\addon\entity\ai\Goal;
use pocketmine\addon\entity\EntityFilter;
use pocketmine\addon\entity\FilterContext;
use pocketmine\entity\Living;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use function sqrt;

/**
 * minecraft:behavior.knockback_roar: a roar that throws back and hurts everything around the mob (the ravager's,
 * and many boss mobs'), then fires on_roar_end.
 */
final class KnockbackRoarGoal extends Goal{
	private int $roarAt = 0;
	private int $endAt = 0;
	private int $readyAt = 0;
	private bool $roared = false;

	public function getFlags() : int{ return self::FLAG_MOVE | self::FLAG_LOOK; }

	public function canStart() : bool{
		$target = $this->mob->getTargetEntity();
		return $this->now() >= $this->readyAt && $target !== null && self::isValidTarget($target)
			&& $target->getPosition()->distance($this->mob->getPosition()) <= $this->float("knockback_range", 4) + 1;
	}

	public function start() : void{
		$this->navigator()->stop();
		$this->roared = false;
		$this->roarAt = $this->now() + (int) ($this->float("attack_time", 0.5) * 20);
		$this->endAt = $this->now() + (int) ($this->float("duration", 1) * 20);
	}

	public function canContinue() : bool{
		return $this->now() < $this->endAt;
	}

	public function tick() : void{
		if($this->roared || $this->now() < $this->roarAt){
			return;
		}
		$this->roared = true;
		$range = $this->float("knockback_range", 4);
		$horizontal = $this->float("knockback_horizontal_strength", 4) * 0.2;
		$vertical = $this->float("knockback_vertical_strength", 4) * 0.1;
		$damage = $this->float("knockback_damage", 6);
		$pos = $this->mob->getPosition();
		foreach($this->mob->getWorld()->getNearbyEntities($this->mob->getBoundingBox()->expandedCopy($range, $range / 2, $range), $this->mob) as $entity){
			if(!$entity instanceof Living || !$entity->isAlive()){
				continue;
			}
			$context = new FilterContext($this->mob, $entity);
			if(!EntityFilter::test($this->data["knockback_filters"] ?? null, $context, $this->mob->reporter())){
				continue;
			}
			$dx = $entity->getPosition()->x - $pos->x;
			$dz = $entity->getPosition()->z - $pos->z;
			$length = sqrt($dx * $dx + $dz * $dz);
			if($length > $range){
				continue;
			}
			if($damage > 0 && EntityFilter::test($this->data["damage_filters"] ?? null, $context, $this->mob->reporter())){
				$entity->attack(new EntityDamageByEntityEvent($this->mob, $entity, EntityDamageEvent::CAUSE_ENTITY_ATTACK, $damage, [], 0.0));
			}
			if($length > 0.01){
				$entity->knockBack($dx / $length, $dz / $length, $horizontal, $vertical);
			}
		}
	}

	public function stop() : void{
		$this->readyAt = $this->now() + (int) ($this->float("cooldown_time", 0.1) * 20);
		$this->mob->triggerEventDefinition($this->data["on_roar_end"] ?? null);
	}
}
