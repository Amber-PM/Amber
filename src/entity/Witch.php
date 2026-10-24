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

use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\entity\Location;
use pocketmine\entity\projectile\SplashPotion;
use pocketmine\item\PotionType;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\player\Player;
use function atan2;
use function count;
use function mt_rand;
use function sqrt;
use const M_PI;

class Witch extends Living{

	public static function getNetworkTypeId() : string{
		return EntityIds::WITCH;
	}

	protected int $attackCooldown = 40;
	protected int $drinkingTicks = 0;
	protected ?Living $target = null;

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(1.9, 0.6);
	}

	public function getName() : string{
		return "Witch";
	}

	protected function addAttributes() : void{
		parent::addAttributes();
		$this->setMaxHealth(26);
		$this->setHealth(26.0);
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		$hasUpdate = parent::entityBaseTick($tickDiff);

		if(!$this->isAlive()){
			return $hasUpdate;
		}

		// Defensive potion drinking
		if($this->drinkingTicks > 0){
			$this->drinkingTicks -= $tickDiff;
			return $hasUpdate;
		}

		if($this->isOnFire() && !$this->getEffects()->has(VanillaEffects::FIRE_RESISTANCE())){
			$this->getEffects()->add(new EffectInstance(VanillaEffects::FIRE_RESISTANCE(), 600)); // 30s
			$this->drinkingTicks = 30;
			return $hasUpdate;
		}

		if($this->getHealth() < 15.0 && !$this->getEffects()->has(VanillaEffects::REGENERATION())){
			$this->getEffects()->add(new EffectInstance(VanillaEffects::REGENERATION(), 600, 1));
			$this->drinkingTicks = 30;
			return $hasUpdate;
		}

		if($this->isUnderwater() && !$this->getEffects()->has(VanillaEffects::WATER_BREATHING())){
			$this->getEffects()->add(new EffectInstance(VanillaEffects::WATER_BREATHING(), 600));
			$this->drinkingTicks = 30;
			return $hasUpdate;
		}

		// Target acquisition
		if($this->target === null || !$this->target->isAlive() || $this->location->distanceSquared($this->target->getLocation()) > 256){
			$this->findNearestTarget();
		}

		if($this->target !== null && $this->target->isAlive()){
			$tPos = $this->target->getLocation();
			$distSq = $this->location->distanceSquared($tPos);

			// Look at target
			$xDist = $tPos->x - $this->location->x;
			$zDist = $tPos->z - $this->location->z;
			$yDist = ($tPos->y + $this->target->getEyeHeight()) - ($this->location->y + $this->getEyeHeight());
			$horizontal = sqrt($xDist * $xDist + $zDist * $zDist);

			$this->location->yaw = atan2($zDist, $xDist) * 180 / M_PI - 90;
			$this->location->pitch = -atan2($yDist, $horizontal) * 180 / M_PI;

			if($this->attackCooldown <= 0 && $distSq <= 256.0){
				$this->throwPotion($tPos, $distSq);
				$this->attackCooldown = mt_rand(40, 60);
			}
		}

		if($this->attackCooldown > 0){
			$this->attackCooldown -= $tickDiff;
		}

		return $hasUpdate;
	}

	private function throwPotion(Vector3 $targetPos, float $distSq) : void{
		$potionType = PotionType::HARMING;
		if($distSq > 64.0 && !$this->target->getEffects()->has(VanillaEffects::SLOWNESS())){
			$potionType = PotionType::SLOWNESS;
		}elseif($this->target->getHealth() >= 8.0 && !$this->target->getEffects()->has(VanillaEffects::POISON())){
			$potionType = PotionType::POISON;
		}elseif(mt_rand(0, 3) === 0 && !$this->target->getEffects()->has(VanillaEffects::WEAKNESS())){
			$potionType = PotionType::WEAKNESS;
		}

		$spawnPos = $this->location->add(0, 1.5, 0);
		$diff = $targetPos->add(0, 0.5, 0)->subtractVector($spawnPos);
		$dist = sqrt($diff->x * $diff->x + $diff->z * $diff->z);

		// Arc calculation
		$motionY = $diff->y / 15 + $dist * 0.05 + 0.2;
		$motionX = $diff->x / 15;
		$motionZ = $diff->z / 15;

		$potion = new SplashPotion(Location::fromObject($spawnPos, $this->getWorld()), $this, $potionType);
		$potion->setMotion(new Vector3($motionX, $motionY, $motionZ));
		$potion->spawnToAll();
	}

	private function findNearestTarget() : void{
		$closest = null;
		$closestDist = 256.0; // 16 blocks squared
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

	public function getDrops() : array{
		$drops = [];
		$possible = [
			VanillaItems::GLASS_BOTTLE(),
			VanillaItems::GLOWSTONE_DUST(),
			VanillaItems::GUNPOWDER(),
			VanillaItems::REDSTONE_DUST(),
			VanillaItems::SPIDER_EYE(),
			VanillaItems::SUGAR(),
			VanillaItems::STICK()
		];
		$rolls = mt_rand(1, 3);
		for($i = 0; $i < $rolls; ++$i){
			$item = $possible[mt_rand(0, count($possible) - 1)];
			$drops[] = (clone $item)->setCount(mt_rand(1, 2));
		}
		return $drops;
	}

	public function getXpDropAmount() : int{
		return 5;
	}
}
