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

use pocketmine\block\Water;
use pocketmine\entity\Location;
use pocketmine\entity\projectile\SmallFireball;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\player\Player;
use pocketmine\world\particle\FlameParticle;
use pocketmine\world\particle\SmokeParticle;
use function atan2;
use function cos;
use function floor;
use function mt_rand;
use function sin;
use function sqrt;
use const M_PI;

class Blaze extends Living{

	public static function getNetworkTypeId() : string{
		return EntityIds::BLAZE;
	}

	protected float $gravity = 0.04;
	protected int $burstCooldown = 60;
	protected int $burstShotsRemaining = 0;
	protected int $burstShotDelay = 0;
	protected ?Player $target = null;

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(1.8, 0.6);
	}

	public function getName() : string{
		return "Blaze";
	}

	protected function addAttributes() : void{
		parent::addAttributes();
		$this->setMaxHealth(20);
		$this->setHealth(20.0);
	}

	public function calculateFallDamage(float $fallDistance) : float{
		return 0.0;
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

		// Water damage
		$block = $this->getWorld()->getBlockAt((int) floor($this->location->x), (int) floor($this->location->y), (int) floor($this->location->z));
		if($this->isUnderwater() || $block instanceof Water){
			$this->attack(new EntityDamageEvent($this, EntityDamageEvent::CAUSE_DROWNING, 1.0));
		}

		// Ambient particles
		if(mt_rand(0, 3) === 0){
			$this->getWorld()->addParticle($this->location->add((mt_rand(-5, 5) / 10), mt_rand(0, 15) / 10, (mt_rand(-5, 5) / 10)), new FlameParticle());
			$this->getWorld()->addParticle($this->location->add((mt_rand(-5, 5) / 10), mt_rand(0, 15) / 10, (mt_rand(-5, 5) / 10)), new SmokeParticle());
		}

		// Target acquisition
		if($this->target === null || !$this->target->isAlive() || $this->target->isCreative() || $this->target->isSpectator() || $this->location->distanceSquared($this->target->getLocation()) > 2304){
			$this->findNearestTarget();
		}

		if($this->target !== null && $this->target->isAlive()){
			$distSq = $this->location->distanceSquared($this->target->getLocation());

			// Look at target
			$targetPos = $this->target->getLocation();
			$xDist = $targetPos->x - $this->location->x;
			$zDist = $targetPos->z - $this->location->z;
			$yDist = ($targetPos->y + $this->target->getEyeHeight()) - ($this->location->y + $this->getEyeHeight());
			$horizontal = sqrt($xDist * $xDist + $zDist * $zDist);

			$this->location->yaw = atan2($zDist, $xDist) * 180 / M_PI - 90;
			$this->location->pitch = -atan2($yDist, $horizontal) * 180 / M_PI;

			// Hovering levitation towards target altitude
			if($this->location->y < $targetPos->y + 1.5){
				$this->motion = $this->motion->add(0, 0.04, 0);
			}

			// Melee attack if within 2 blocks
			if($distSq <= 4.0){
				if($this->burstCooldown <= 0){
					$ev = new EntityDamageByEntityEvent($this, $this->target, EntityDamageEvent::CAUSE_ENTITY_ATTACK, 6.0);
					$this->target->attack($ev);
					$this->burstCooldown = 20;
				}
			}elseif($distSq <= 2304.0){
				// Ranged fireball attack
				if($this->burstShotsRemaining > 0){
					if($this->burstShotDelay <= 0){
						$this->shootFireball($targetPos);
						--$this->burstShotsRemaining;
						$this->burstShotDelay = 6;
						if($this->burstShotsRemaining === 0){
							$this->burstCooldown = mt_rand(60, 100);
						}
					}else{
						--$this->burstShotDelay;
					}
				}elseif($this->burstCooldown <= 0){
					$this->burstShotsRemaining = 3;
					$this->burstShotDelay = 0;
				}
			}
		}

		if($this->burstCooldown > 0){
			$this->burstCooldown -= $tickDiff;
		}

		return $hasUpdate;
	}

	private function findNearestTarget() : void{
		$closest = null;
		$closestDist = 2304.0; // 48 blocks squared
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
		$spawnPos = $this->location->add(0, 1.2, 0);
		$dir = $targetPos->add(0, 0.5, 0)->subtractVector($spawnPos)->normalize();
		$fireball = new SmallFireball(Location::fromObject($spawnPos, $this->getWorld()), $this);
		$fireball->setMotion($dir->multiply(0.8));
		$fireball->spawnToAll();
	}

	public function getDrops() : array{
		return [
			VanillaItems::BLAZE_ROD()->setCount(mt_rand(0, 1))
		];
	}

	public function getXpDropAmount() : int{
		return 10;
	}
}
