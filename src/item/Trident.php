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
use pocketmine\block\BlockTypeIds;
use pocketmine\entity\Entity;
use pocketmine\entity\Living;
use pocketmine\entity\Location;
use pocketmine\entity\projectile\Trident as TridentEntity;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\ProjectileLaunchEvent;
use pocketmine\item\enchantment\ImpalingEnchantment;
use pocketmine\item\enchantment\VanillaEnchantments;
use pocketmine\player\Player;
use pocketmine\world\sound\TridentThrowSound;
use function min;

class Trident extends Tool implements Releasable{
	private const DAMAGE_ON_THROW = 1;

	public function getMaxDurability() : int{
		return 251;
	}

	public function onReleaseUsing(Player $player, array &$returnedItems) : ItemUseResult{
		$location = $player->getLocation();

		$diff = $player->getItemUseDuration();
		if($diff < 10){
			return ItemUseResult::FAIL;
		}

		$riptide = $this->getEnchantment(VanillaEnchantments::RIPTIDE());
		if($riptide !== null){
			$inWaterOrRain = $player->isUnderwater() ||
				$player->getWorld()->getBlock($player->getPosition())->getTypeId() === BlockTypeIds::WATER ||
				$player->getWorld()->isRaining();
			if(!$inWaterOrRain){
				return ItemUseResult::FAIL;
			}
			if($player->hasFiniteResources()){
				$this->applyDamage(self::DAMAGE_ON_THROW);
			}
			$speed = 0.75 * $riptide->getLevel() + 1.25;
			$motion = $player->getDirectionVector()->multiply($speed);
			$player->setMotion($motion);
			$player->resetFallDistance();

			$meleeDamage = (float) $this->getAttackPoints();
			$impaling = $this->getEnchantment(VanillaEnchantments::IMPALING());
			foreach($player->getWorld()->getNearbyEntities($player->getBoundingBox()->expandedCopy(1.5, 1.5, 1.5), $player) as $nearby){
				if($nearby instanceof Living && $nearby->isAlive()){
					$damage = $meleeDamage;
					if($impaling !== null && $impaling->getType() instanceof ImpalingEnchantment && $impaling->getType()->isApplicableTo($nearby)){
						$damage += $impaling->getType()->getDamageBonus($impaling->getLevel());
					}
					$ev = new EntityDamageByEntityEvent($player, $nearby, EntityDamageEvent::CAUSE_ENTITY_ATTACK, $damage);
					$nearby->attack($ev);
				}
			}

			$location->getWorld()->addSound($location, new TridentThrowSound());
			return ItemUseResult::SUCCESS;
		}

		$item = $this->pop();
		if($player->hasFiniteResources()){
			$item->applyDamage(self::DAMAGE_ON_THROW);
		}
		if($item->isNull()){
			return ItemUseResult::FAIL;
		}
		$entity = new TridentEntity(Location::fromObject(
			$player->getEyePos(),
			$player->getWorld(),
			($location->yaw > 180 ? 360 : 0) - $location->yaw,
			-$location->pitch
		), $item, $player);
		$p = $diff / 20;
		$baseForce = min((($p ** 2) + $p * 2) / 3, 1) * 2.4;
		$entity->setMotion($player->getDirectionVector()->multiply($baseForce));

		$ev = new ProjectileLaunchEvent($entity);
		$ev->call();
		if($ev->isCancelled()){
			$ev->getEntity()->flagForDespawn();
			return ItemUseResult::FAIL;
		}
		$ev->getEntity()->spawnToAll();
		$location->getWorld()->addSound($location, new TridentThrowSound());

		return ItemUseResult::SUCCESS;
	}

	public function getAttackPoints() : int{
		return 9;
	}

	public function canStartUsingItem(Player $player) : bool{
		return $this->damage < $this->getMaxDurability() - self::DAMAGE_ON_THROW;
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
