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

namespace pocketmine\item;

use pocketmine\block\Block;
use pocketmine\entity\Entity;
use pocketmine\entity\Living;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\enchantment\DensityEnchantment;
use pocketmine\item\enchantment\VanillaEnchantments;
use pocketmine\item\enchantment\WindBurstEnchantment;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\sound\ExplodeSound;
use function max;

class Mace extends Tool{

	public function getMaxDurability() : int{
		return 500;
	}

	public function getAttackPoints() : int{
		return 6;
	}

	public function calculateSmashDamage(Player $attacker, Entity $victim) : float{
		$baseDamage = (float) $this->getAttackPoints();
		$fallDistance = $attacker->getFallDistance();

		if($fallDistance <= 1.5){
			return $baseDamage;
		}

		$density = $this->getEnchantment(VanillaEnchantments::DENSITY());
		$densityBonus = 0.0;
		if($density !== null && $density->getType() instanceof DensityEnchantment){
			$densityBonus = $density->getType()->getSmashBonusPerBlock($density->getLevel());
		}

		$multiplier = 1.0 + $densityBonus;
		$extraDamage = ($fallDistance - 1.5) * $multiplier;
		$totalDamage = $baseDamage + $extraDamage;

		// Reset fall distance on smash hit
		$attacker->resetFallDistance();

		// Shockwave knockback to nearby mobs within 5 blocks
		$world = $attacker->getWorld();
		$world->addSound($victim->getLocation(), new ExplodeSound());

		$bb = $victim->getBoundingBox()->expandedCopy(5.0, 2.0, 5.0);
		foreach($world->getNearbyEntities($bb, $victim) as $nearby){
			if($nearby instanceof Living && $nearby !== $attacker && $nearby->isAlive()){
				$diff = $nearby->getPosition()->subtractVector($victim->getPosition());
				$dist = $diff->length();
				if($dist > 0.001){
					$horizontalForce = max(0.2, (5.0 - $dist) / 5.0 * 1.2);
					$nearby->setMotion(new Vector3($diff->x / $dist * $horizontalForce, 0.4, $diff->z / $dist * $horizontalForce));
				}
			}
		}

		// Wind Burst upward bounce
		$windBurst = $this->getEnchantment(VanillaEnchantments::WIND_BURST());
		if($windBurst !== null && $windBurst->getType() instanceof WindBurstEnchantment){
			$bounceY = 0.45 + (0.25 * $windBurst->getLevel());
			$attacker->setMotion(new Vector3($attacker->getMotion()->x, $bounceY, $attacker->getMotion()->z));
		}

		return $totalDamage;
	}

	public function onAttackEntity(Entity $victim, array &$returnedItems) : bool{
		return $this->applyDamage(1);
	}

	public function onDestroyBlock(Block $block, array &$returnedItems) : bool{
		if(!$block->getBreakInfo()->breaksInstantly()){
			return $this->applyDamage(2);
		}
		return false;
	}
}
