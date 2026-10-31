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

use pocketmine\entity\projectile\WitherSkull;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\world\Explosion;
use pocketmine\world\sound\ExplodeSound;
use function max;

class Wither extends Living implements Explosive{

	public const TAG_INVULNERABLE_TICKS = "Invul";
	public const INVULNERABLE_TICKS_MAX = 220; // 11 seconds

	protected int $invulnerableTicks = self::INVULNERABLE_TICKS_MAX;
	protected int $target1 = 0;
	protected int $target2 = 0;
	protected int $target3 = 0;

	public static function getNetworkTypeId() : string{
		return EntityIds::WITHER;
	}

	public function getName() : string{
		return "Wither";
	}

	public function getDefaultMaxHealth() : int{
		return 600; // Bedrock parity
	}

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(3.0, 1.0);
	}

	protected function getInitialGravity() : float{
		return 0.0;
	}

	protected function getInitialDragMultiplier() : float{
		return 0.0;
	}

	protected function initEntity(CompoundTag $nbt) : void{
		$this->setMaxHealth(600);
		parent::initEntity($nbt);

		$invulTag = $nbt->getTag(self::TAG_INVULNERABLE_TICKS);
		if($invulTag instanceof IntTag){
			$this->invulnerableTicks = $invulTag->getValue();
		}

		if($nbt->getTag("Health") === null){
			// Start at 200 HP during spawn phase charging up to 600 HP
			$this->setHealth($this->invulnerableTicks > 0 ? 200.0 : 600.0);
		}
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT();
		$nbt->setInt(self::TAG_INVULNERABLE_TICKS, $this->invulnerableTicks);
		return $nbt;
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);

		$properties->setInt(EntityMetadataProperties::WITHER_INVULNERABLE_TICKS, $this->invulnerableTicks);
		$properties->setLong(EntityMetadataProperties::WITHER_TARGET_1, $this->target1);
		$properties->setLong(EntityMetadataProperties::WITHER_TARGET_2, $this->target2);
		$properties->setLong(EntityMetadataProperties::WITHER_TARGET_3, $this->target3);

		$armored = $this->hasWitherArmor();
		$properties->setGenericFlag(EntityMetadataFlags::CHARGED, $armored);
		$properties->setGenericFlag(EntityMetadataFlags::POWERED, $armored);
	}

	public function getInvulnerableTicks() : int{
		return $this->invulnerableTicks;
	}

	public function setInvulnerableTicks(int $ticks) : void{
		$this->invulnerableTicks = max(0, $ticks);
		$this->networkPropertiesDirty = true;
	}

	public function hasWitherArmor() : bool{
		return $this->invulnerableTicks <= 0 && $this->getHealth() <= ($this->getMaxHealth() / 2);
	}

	public function getHeadTarget(int $head) : int{
		return match($head){
			1 => $this->target1,
			2 => $this->target2,
			3 => $this->target3,
			default => $this->target1,
		};
	}

	public function setHeadTarget(int $head, int $targetEntityId) : void{
		match($head){
			1 => $this->target1 = $targetEntityId,
			2 => $this->target2 = $targetEntityId,
			3 => $this->target3 = $targetEntityId,
			default => $this->target1 = $targetEntityId,
		};
		$this->networkPropertiesDirty = true;
	}

	public function attack(EntityDamageEvent $source) : void{
		// Fire, lava, freezing, and drowning immunity
		if(
			$source->getCause() === EntityDamageEvent::CAUSE_FIRE ||
			$source->getCause() === EntityDamageEvent::CAUSE_FIRE_TICK ||
			$source->getCause() === EntityDamageEvent::CAUSE_LAVA ||
			$source->getCause() === EntityDamageEvent::CAUSE_DROWNING ||
			$source->getCause() === EntityDamageEvent::CAUSE_SUFFOCATION
		){
			$source->cancel();
			return;
		}

		// Completely invulnerable during initial charging spawn phase
		if($this->invulnerableTicks > 0){
			$source->cancel();
			return;
		}

		// Wither armor completely negates all projectile damage
		if($this->hasWitherArmor() && $source->getCause() === EntityDamageEvent::CAUSE_PROJECTILE){
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

		// Handle spawn charging countdown and health increase
		if($this->invulnerableTicks > 0){
			$this->invulnerableTicks -= $tickDiff;
			// Health charge up from 200 to 600
			$chargeProgress = 1.0 - ($this->invulnerableTicks / self::INVULNERABLE_TICKS_MAX);
			$newHealth = 200.0 + (400.0 * $chargeProgress);
			if($newHealth > $this->getHealth()){
				$this->setHealth($newHealth);
			}

			if($this->invulnerableTicks <= 0){
				$this->invulnerableTicks = 0;
				$this->explode();
			}
			$hasUpdate = true;
		}

		return $hasUpdate;
	}

	public function explode() : void{
		$explosion = new Explosion($this->location, 7.0, $this);
		$explosion->explodeA();
		$explosion->explodeB();
		$this->broadcastSound(new ExplodeSound());
	}

	public function shootSkull(Vector3 $targetPos, bool $dangerous = false) : WitherSkull{
		$headPos = $this->location->add(0, 2.5, 0);
		$direction = $targetPos->subtractVector($headPos)->normalize();

		$location = Location::fromObject($headPos, $this->getWorld(), 0.0, 0.0);
		$skull = new WitherSkull($location);
		$skull->setMotion($direction->multiply(1.2));
		$skull->setDangerous($dangerous);
		$skull->setOwningEntity($this);
		$skull->spawnToAll();

		return $skull;
	}

	public function getDrops() : array{
		return [
			VanillaItems::NETHER_STAR()
		];
	}

	public function getXpDropAmount() : int{
		return 50;
	}
}
