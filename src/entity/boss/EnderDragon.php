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

namespace pocketmine\entity\boss;

use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Living;
use pocketmine\entity\Location;
use pocketmine\entity\object\EndCrystal;
use pocketmine\entity\object\ExperienceOrb;
use pocketmine\event\entity\EntityRegainHealthEvent;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\NetworkBroadcastUtils;
use pocketmine\network\mcpe\protocol\BossEventPacket;
use pocketmine\network\mcpe\protocol\types\BossBarColor;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\player\Player;
use pocketmine\world\sound\DragonBreathSound;
use function min;

class EnderDragon extends Living{

	public const PHASE_CIRCLING = 0;
	public const PHASE_STRAFING = 1;
	public const PHASE_CHARGING_PLAYER = 2;
	public const PHASE_LANDING = 3;
	public const PHASE_PERCHED = 4;
	public const PHASE_TAKE_OFF = 5;
	public const PHASE_DYING = 6;

	public static function getNetworkTypeId() : string{ return EntityIds::ENDER_DRAGON; }

	protected int $phase = self::PHASE_CIRCLING;
	protected int $phaseTicks = 0;
	protected ?EndCrystal $healingCrystal = null;

	public function __construct(Location $location, ?CompoundTag $nbt = null){
		parent::__construct($location, $nbt);
		$this->setMaxHealth(200);
		$this->setHealth(200);
	}

	public function getName() : string{
		return "Ender Dragon";
	}

	public function getMaxHealth() : int{
		return 200;
	}

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(8.0, 16.0);
	}

	protected function getInitialDragMultiplier() : float{ return 0.0; }

	protected function getInitialGravity() : float{ return 0.0; }

	public function getPhase() : int{
		return $this->phase;
	}

	public function setPhase(int $phase) : void{
		$this->phase = $phase;
		$this->phaseTicks = 0;
	}

	public function getHealingCrystal() : ?EndCrystal{
		return $this->healingCrystal;
	}

	public function tickCrystalHealing(EndCrystal $crystal) : void{
		if($crystal->isAlive() && !$crystal->isClosed() && $this->location->distance($crystal->getLocation()) <= 48.0){
			$this->healingCrystal = $crystal;
			$newHealth = min($this->getMaxHealth(), $this->getHealth() + 0.2);
			$this->setHealth($newHealth);
		}else{
			$this->healingCrystal = null;
		}
	}

	public function syncBossBarTo(Player $player) : void{
		$pk = BossEventPacket::show(
			$this->getId(),
			$this->getName(),
			$this->getHealth() / $this->getMaxHealth(),
			false,
			BossBarColor::PURPLE
		);
		$player->getNetworkSession()->sendDataPacket($pk);
	}

	public function hideBossBarFrom(Player $player) : void{
		$pk = BossEventPacket::hide($this->getId());
		$player->getNetworkSession()->sendDataPacket($pk);
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		if($this->closed){
			return false;
		}

		$hasUpdate = parent::entityBaseTick($tickDiff);
		$this->phaseTicks += $tickDiff;

		if($this->healingCrystal !== null){
			if($this->healingCrystal->isClosed() || !$this->healingCrystal->isAlive()){
				$this->healingCrystal = null;
			}else{
				$this->tickCrystalHealing($this->healingCrystal);
			}
		}

		switch($this->phase){
			case self::PHASE_CIRCLING:
				if($this->phaseTicks > 200){
					$this->setPhase(self::PHASE_STRAFING);
				}
				break;
			case self::PHASE_STRAFING:
				if($this->phaseTicks > 100){
					$this->setPhase(self::PHASE_LANDING);
				}
				break;
			case self::PHASE_LANDING:
				if($this->phaseTicks > 60){
					$this->setPhase(self::PHASE_PERCHED);
				}
				break;
			case self::PHASE_PERCHED:
				if($this->phaseTicks % 40 === 0){
					$this->broadcastSound(new DragonBreathSound());
				}
				if($this->phaseTicks > 160){
					$this->setPhase(self::PHASE_TAKE_OFF);
				}
				break;
			case self::PHASE_TAKE_OFF:
				if($this->phaseTicks > 40){
					$this->setPhase(self::PHASE_CIRCLING);
				}
				break;
			case self::PHASE_DYING:
				if($this->phaseTicks >= 100){
					// Drop 12,000 XP in multiple orbs
					$world = $this->getWorld();
					$remainingXp = 12000;
					while($remainingXp > 0){
						$batch = min($remainingXp, 1000);
						$orb = new ExperienceOrb($this->location, $batch);
						$orb->spawnToAll();
						$remainingXp -= $batch;
					}
					$this->flagForDespawn();
					return false;
				}
				break;
		}

		return $hasUpdate || true;
	}

	public function startDeathSequence() : void{
		$this->setPhase(self::PHASE_DYING);
	}
}
