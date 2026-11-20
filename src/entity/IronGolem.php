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

namespace pocketmine\entity;

use pocketmine\block\VanillaBlocks;
use pocketmine\entity\animation\ArmSwingAnimation;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\Item;
use pocketmine\item\ItemTypeIds;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\player\Player;
use function atan2;
use function min;
use function mt_rand;
use const M_PI;

class IronGolem extends Living{

	public static function getNetworkTypeId() : string{
		return EntityIds::IRON_GOLEM;
	}

	protected int $attackCooldown = 0;
	protected ?Living $target = null;

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(2.9, 1.4);
	}

	public function getName() : string{
		return "Iron Golem";
	}

	protected function addAttributes() : void{
		parent::addAttributes();
		$this->setMaxHealth(100);
		$this->setHealth(100.0);
	}

	public function calculateFallDamage(float $fallDistance) : float{
		return 0.0;
	}

	public function onInteract(Player $player, Vector3 $clickVector) : bool{
		$hand = $player->getInventory()->getItemInHand();
		if($hand->getTypeId() === ItemTypeIds::IRON_INGOT && $this->getHealth() < $this->getMaxHealth()){
			$this->setHealth(min($this->getMaxHealth(), $this->getHealth() + 25.0));
			if($player->hasFiniteResources()){
				$hand->pop();
				$player->getInventory()->setItemInHand($hand);
			}
			return true;
		}

		return false;
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		$hasUpdate = parent::entityBaseTick($tickDiff);

		if(!$this->isAlive()){
			return $hasUpdate;
		}

		if($this->attackCooldown > 0){
			$this->attackCooldown -= $tickDiff;
		}

		// Target acquisition: monsters (excluding creepers)
		if($this->target === null || !$this->target->isAlive() || $this->location->distanceSquared($this->target->getLocation()) > 256){
			$this->findNearestMonsterTarget();
		}

		if($this->target !== null && $this->target->isAlive()){
			$tPos = $this->target->getLocation();
			$distSq = $this->location->distanceSquared($tPos);

			// Look at target
			$xDist = $tPos->x - $this->location->x;
			$zDist = $tPos->z - $this->location->z;
			$this->location->yaw = atan2($zDist, $xDist) * 180 / M_PI - 90;

			// Attack if in melee reach
			if($distSq <= 6.25 && $this->attackCooldown <= 0){
				$this->broadcastAnimation(new ArmSwingAnimation($this));
				$damage = (float) mt_rand(7, 21);
				$ev = new EntityDamageByEntityEvent($this, $this->target, EntityDamageEvent::CAUSE_ENTITY_ATTACK, $damage);
				$this->target->attack($ev);
				if(!$ev->isCancelled()){
					// Fling target into the air
					$curMotion = $this->target->getMotion();
					$this->target->setMotion(new Vector3($curMotion->x, $curMotion->y + 0.45, $curMotion->z));
				}
				$this->attackCooldown = 20;
			}
		}

		return $hasUpdate;
	}

	private function findNearestMonsterTarget() : void{
		$closest = null;
		$closestDist = 256.0; // 16 blocks squared
		foreach($this->getWorld()->getNearbyEntities($this->boundingBox->expandedCopy(16, 8, 16)) as $entity){
			if(!($entity instanceof Living) || !$entity->isAlive() || $entity === $this || $entity instanceof IronGolem || $entity instanceof Player || $entity instanceof Villager){
				continue;
			}
			// Creepers are ignored by Iron Golems
			if($entity->getName() === "Creeper"){
				continue;
			}
			$dist = $this->location->distanceSquared($entity->getLocation());
			if($dist < $closestDist){
				$closestDist = $dist;
				$closest = $entity;
			}
		}
		$this->target = $closest;
	}

	public function getDrops() : array{
		return [
			VanillaItems::IRON_INGOT()->setCount(mt_rand(3, 5)),
			VanillaBlocks::POPPY()->asItem()->setCount(mt_rand(0, 2))
		];
	}

	public function getXpDropAmount() : int{
		return 0; // Iron Golems drop 0 XP in vanilla
	}
}
