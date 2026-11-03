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

use pocketmine\entity\raid\Raider;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\math\AxisAlignedBB;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\LevelSoundEventPacket;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\LevelSoundEvent;

class EvocationFang extends Entity{

	public static function getNetworkTypeId() : string{ return EntityIds::EVOCATION_FANG; }

	protected bool $gravityEnabled = false;

	private ?Living $owner = null;
	private int $warmupTicks = 12; // 0.6s warmup until snap
	private int $ageTicks = 0;
	private bool $hasSnapped = false;

	public function __construct(Location $location, ?Living $owner = null, ?CompoundTag $nbt = null){
		$this->owner = $owner;
		parent::__construct($location, $nbt);
	}

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(0.8, 0.8);
	}

	public function getName() : string{
		return "Evocation Fang";
	}

	protected function getInitialDragMultiplier() : float{
		return 0.0;
	}

	protected function getInitialGravity() : float{
		return 0.0;
	}

	public function getOwner() : ?Living{
		return $this->owner;
	}

	public function setOwner(?Living $owner) : void{
		$this->owner = $owner;
	}

	public function hasSnapped() : bool{
		return $this->hasSnapped;
	}

	public function snap() : void{
		if($this->hasSnapped){
			return;
		}

		$this->hasSnapped = true;
		$this->getWorld()->broadcastPacketToViewers($this->location, LevelSoundEventPacket::nonActorSound(LevelSoundEvent::FANG, $this->location, false));

		$bb = $this->boundingBox->expandedCopy(0.2, 0.2, 0.2);
		foreach($this->getWorld()->getNearbyEntities($bb, $this) as $entity){
			if(!$entity instanceof Living || !$entity->isAlive() || $entity === $this->owner || $entity instanceof Raider){
				continue;
			}

			// 6.0 Magic damage, bypassing armor
			$source = $this->owner !== null ?
				new EntityDamageByEntityEvent($this->owner, $entity, EntityDamageEvent::CAUSE_MAGIC, 6.0) :
				new EntityDamageEvent($entity, EntityDamageEvent::CAUSE_MAGIC, 6.0);

			$entity->attack($source);
		}
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		$hasUpdate = parent::entityBaseTick($tickDiff);

		if($this->isClosed()){
			return $hasUpdate;
		}

		$this->ageTicks += $tickDiff;

		if(!$this->hasSnapped){
			$this->warmupTicks -= $tickDiff;
			if($this->warmupTicks <= 0){
				$this->snap();
			}
		}

		// Lifespan 22 ticks (1.1s)
		if($this->ageTicks >= 22){
			$this->flagForDespawn();
		}

		return true;
	}
}
