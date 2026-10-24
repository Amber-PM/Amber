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

namespace pocketmine\entity\projectile;

use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\entity\Entity;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Living;
use pocketmine\entity\Location;
use pocketmine\event\entity\EntityDamageByChildEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\ProjectileHitEntityEvent;
use pocketmine\event\entity\ProjectileHitEvent;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\world\Explosion;

class WitherSkull extends Throwable{

	public const TAG_DANGEROUS = "Dangerous";

	protected bool $dangerous = false;
	protected float $damage = 8.0;

	public function __construct(Location $location, ?\pocketmine\entity\Entity $shootingEntity = null, ?CompoundTag $nbt = null){
		parent::__construct($location, $shootingEntity, $nbt);
	}

	public static function getNetworkTypeId() : string{
		return EntityIds::WITHER_SKULL;
	}

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(0.3, 0.3);
	}

	protected function getInitialDragMultiplier() : float{
		return 0.0;
	}

	protected function getInitialGravity() : float{
		return 0.0;
	}

	public function isDangerous() : bool{
		return $this->dangerous;
	}

	public function setDangerous(bool $dangerous = true) : void{
		$this->dangerous = $dangerous;
		$this->networkPropertiesDirty = true;
	}

	protected function initEntity(CompoundTag $nbt) : void{
		parent::initEntity($nbt);
		$this->dangerous = $nbt->getByte(self::TAG_DANGEROUS, 0) !== 0;
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT();
		$nbt->setByte(self::TAG_DANGEROUS, $this->dangerous ? 1 : 0);
		return $nbt;
	}

	protected function onHit(ProjectileHitEvent $event) : void{
		if($event instanceof ProjectileHitEntityEvent){
			$entity = $event->getEntityHit();
			$owningEntity = $this->getOwningEntity();

			$ev = new EntityDamageByChildEntityEvent($owningEntity ?? $this, $this, $entity, EntityDamageEvent::CAUSE_PROJECTILE, $this->damage);
			$entity->attack($ev);

			if($entity instanceof Living && $entity->isAlive()){
				// Inflict Wither II for 10 seconds (200 ticks)
				$entity->getEffects()->add(new EffectInstance(VanillaEffects::WITHER(), 200, 1));
			}
		}

		$explosion = new Explosion($this->location, $this->dangerous ? 1.5 : 1.0, $this);
		$explosion->explodeA();
		$explosion->explodeB();

		$this->flagForDespawn();
	}
}
