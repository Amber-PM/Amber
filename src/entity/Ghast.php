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

use pocketmine\entity\Location;
use pocketmine\entity\projectile\LargeFireball;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\player\Player;
use function atan2;
use function mt_rand;
use function sqrt;
use const M_PI;

class Ghast extends Living{

	public static function getNetworkTypeId() : string{
		return EntityIds::GHAST;
	}

	protected float $gravity = 0.0;
	protected int $attackCooldown = 60;
	protected int $chargeTicks = 0;
	protected bool $charging = false;
	protected ?Player $target = null;

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(4.0, 4.0);
	}

	public function getName() : string{
		return "Ghast";
	}

	protected function addAttributes() : void{
		parent::addAttributes();
		$this->setMaxHealth(10);
		$this->setHealth(10.0);
	}

	public function calculateFallDamage(float $fallDistance) : float{
		return 0.0;
	}

	public function isCharging() : bool{
		return $this->charging;
	}

	public function setCharging(bool $charging) : void{
		$this->charging = $charging;
		$this->networkPropertiesDirty = true;
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);
		$properties->setGenericFlag(EntityMetadataFlags::CHARGE_ATTACK, $this->charging);
	}

	public function attack(EntityDamageEvent $source) : void{
		if($source->getCause() === EntityDamageEvent::CAUSE_FIRE
			|| $source->getCause() === EntityDamageEvent::CAUSE_FIRE_TICK
			|| $source->getCause() === EntityDamageEvent::CAUSE_LAVA
		){
			$source->cancel();
			return;
		}

		parent::attack($source);
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		$hasUpdate = parent::entityBaseTick($tickDiff);

		if(!$this->isAlive()){
			return $hasUpdate;
		}

		// Target acquisition
		if($this->target === null || !$this->target->isAlive() || $this->target->isCreative() || $this->target->isSpectator() || $this->location->distanceSquared($this->target->getLocation()) > 4096){
			$this->findNearestTarget();
		}

		if($this->target !== null && $this->target->isAlive()){
			$targetPos = $this->target->getLocation();
			$xDist = $targetPos->x - $this->location->x;
			$zDist = $targetPos->z - $this->location->z;
			$yDist = ($targetPos->y + $this->target->getEyeHeight()) - ($this->location->y + 2.0);
			$horizontal = sqrt($xDist * $xDist + $zDist * $zDist);

			$this->location->yaw = atan2($zDist, $xDist) * 180 / M_PI - 90;
			$this->location->pitch = -atan2($yDist, $horizontal) * 180 / M_PI;

			// Attack charging & firing
			if($this->attackCooldown <= 0){
				if(!$this->charging){
					$this->setCharging(true);
					$this->chargeTicks = 20; // 1 second charge
				}else{
					--$this->chargeTicks;
					if($this->chargeTicks <= 0){
						$this->shootFireball($targetPos);
						$this->setCharging(false);
						$this->attackCooldown = 60; // 3 second cooldown
					}
				}
			}else{
				$this->attackCooldown -= $tickDiff;
			}
		}else{
			if($this->charging){
				$this->setCharging(false);
			}
			// Slow peaceful hovering
			if(mt_rand(0, 40) === 0){
				$this->motion = new Vector3(mt_rand(-5, 5) / 100, mt_rand(-2, 2) / 100, mt_rand(-5, 5) / 100);
			}
		}

		return $hasUpdate;
	}

	private function findNearestTarget() : void{
		$closest = null;
		$closestDist = 4096.0; // 64 blocks squared
		foreach($this->getWorld()->getPlayers() as $player){
			if(!$player->isAlive() || $player->isCreative() || $player->isSpectator()){
				continue;
			}
			$dist = $this->location->distanceSquared($player->getLocation());
			if($dist < $closestDist){
				$closestDist = $dist;
				$closest = $player;
			}
		}
		$this->target = $closest;
	}

	private function shootFireball(Vector3 $targetPos) : void{
		$spawnPos = $this->location->add(0, 1.5, 0);
		$dir = $targetPos->add(0, 0.5, 0)->subtractVector($spawnPos)->normalize();
		$fireball = new LargeFireball(Location::fromObject($spawnPos, $this->getWorld()), $this);
		$fireball->setMotion($dir->multiply(1.0));
		$fireball->spawnToAll();
	}

	public function getDrops() : array{
		return [
			VanillaItems::GUNPOWDER()->setCount(mt_rand(0, 2)),
			VanillaItems::GHAST_TEAR()->setCount(mt_rand(0, 1))
		];
	}

	public function getXpDropAmount() : int{
		return 5;
	}
}
