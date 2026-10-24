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

use pocketmine\event\entity\EntityPreExplodeEvent;
use pocketmine\item\Durable;
use pocketmine\item\Item;
use pocketmine\item\ItemTypeIds;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\player\Player;
use pocketmine\world\Explosion;
use pocketmine\world\Position;
use pocketmine\world\sound\IgniteSound;
use function max;
use function min;
use function mt_rand;

class Creeper extends Living implements Explosive{

	public const TAG_FUSE = "Fuse";
	public const TAG_POWERED = "powered";
	public const TAG_IGNITED = "ignited";

	public const DEFAULT_FUSE_TICKS = 30; // 1.5 seconds in BDS
	public const SWELL_START_DISTANCE_SQ = 6.25; // 2.5 blocks
	public const SWELL_STOP_DISTANCE_SQ = 36.0; // 6.0 blocks
	public const CAT_AVOID_DISTANCE_SQ = 36.0; // 6.0 blocks

	protected int $fuse = self::DEFAULT_FUSE_TICKS;
	protected int $maxFuse = self::DEFAULT_FUSE_TICKS;
	protected int $fuseDirection = -1; // -1 = deflating, 1 = swelling
	protected bool $powered = false;
	protected bool $forcedIgnited = false;

	public static function getNetworkTypeId() : string{
		return EntityIds::CREEPER;
	}

	public function getName() : string{
		return "Creeper";
	}

	public function getDefaultMaxHealth() : int{
		return 20;
	}

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(1.8, 0.6);
	}

	protected function initEntity(CompoundTag $nbt) : void{
		$this->setMaxHealth(20);
		parent::initEntity($nbt);
		if($nbt->getTag("Health") === null){
			$this->setHealth(20.0);
		}

		$this->fuse = $nbt->getInt(self::TAG_FUSE, self::DEFAULT_FUSE_TICKS);
		$this->powered = $nbt->getByte(self::TAG_POWERED, 0) !== 0;
		$this->forcedIgnited = $nbt->getByte(self::TAG_IGNITED, 0) !== 0;
		if($this->forcedIgnited){
			$this->fuseDirection = 1;
		}
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT();
		$nbt->setInt(self::TAG_FUSE, $this->fuse);
		$nbt->setByte(self::TAG_POWERED, $this->powered ? 1 : 0);
		$nbt->setByte(self::TAG_IGNITED, $this->forcedIgnited ? 1 : 0);
		return $nbt;
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);
		$properties->setGenericFlag(EntityMetadataFlags::POWERED, $this->powered);
		$properties->setGenericFlag(EntityMetadataFlags::IGNITED, $this->isIgnited());
		$properties->setShort(EntityMetadataProperties::FUSE_LENGTH, $this->fuse);
		$properties->setByte(EntityMetadataProperties::CREEPER_SWELL_DIRECTION, $this->fuseDirection);
	}

	public function isPowered() : bool{
		return $this->powered;
	}

	public function setPowered(bool $powered = true) : void{
		$this->powered = $powered;
		$this->networkPropertiesDirty = true;
	}

	public function isIgnited() : bool{
		return $this->fuseDirection > 0;
	}

	public function setIgnited(bool $ignited = true) : void{
		$this->fuseDirection = $ignited ? 1 : -1;
		$this->networkPropertiesDirty = true;
	}

	public function getFuse() : int{
		return $this->fuse;
	}

	public function setFuse(int $fuse) : void{
		$this->fuse = max(0, min($this->maxFuse, $fuse));
		$this->networkPropertiesDirty = true;
	}

	public function getMaxFuse() : int{
		return $this->maxFuse;
	}

	public function setMaxFuse(int $maxFuse) : void{
		$this->maxFuse = $maxFuse;
	}

	public function getFuseDirection() : int{
		return $this->fuseDirection;
	}

	public function setFuseDirection(int $direction) : void{
		$this->fuseDirection = $direction;
		$this->networkPropertiesDirty = true;
	}

	public function interact(Player $player, Vector3 $clickPos) : bool{
		$item = $player->getInventory()->getItemInHand();
		if($item->getTypeId() === ItemTypeIds::FLINT_AND_STEEL && !$this->forcedIgnited){
			$this->forcedIgnited = true;
			$this->fuseDirection = 1;
			$this->broadcastSound(new IgniteSound());
			if(!$player->isCreative() && $item instanceof Durable){
				$item->applyDamage(1);
				$player->getInventory()->setItemInHand($item);
			}
			$this->networkPropertiesDirty = true;
			return true;
		}

		return false;
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		$hasUpdate = parent::entityBaseTick($tickDiff);

		if(!$this->isAlive()){
			return $hasUpdate;
		}

		if(!$this->forcedIgnited){
			$hasFelineNearby = false;
			$closestPlayer = null;
			$closestDistSq = 999.0;

			foreach($this->getWorld()->getNearbyEntities($this->boundingBox->expandedCopy(8, 8, 8), $this) as $nearby){
				if($nearby instanceof Cat){
					if($this->location->distanceSquared($nearby->getLocation()) <= self::CAT_AVOID_DISTANCE_SQ){
						$hasFelineNearby = true;
						break;
					}
				}
				if($nearby instanceof Player && $nearby->isAlive() && !$nearby->isCreative() && !$nearby->isSpectator()){
					$distSq = $this->location->distanceSquared($nearby->getLocation());
					if($distSq < $closestDistSq){
						$closestDistSq = $distSq;
						$closestPlayer = $nearby;
					}
				}
			}

			if($hasFelineNearby){
				// Cats and ocelots scare creepers, stopping swelling
				if($this->fuseDirection !== -1){
					$this->setFuseDirection(-1);
				}
			}elseif($closestPlayer !== null){
				if($closestDistSq <= self::SWELL_START_DISTANCE_SQ){
					if($this->fuseDirection !== 1){
						$this->setFuseDirection(1);
						$this->broadcastSound(new IgniteSound());
					}
				}elseif($closestDistSq > self::SWELL_STOP_DISTANCE_SQ){
					if($this->fuseDirection !== -1){
						$this->setFuseDirection(-1);
					}
				}
			}else{
				if($this->fuseDirection !== -1){
					$this->setFuseDirection(-1);
				}
			}
		}

		// Update fuse
		if($this->fuseDirection > 0){
			$this->fuse -= $tickDiff;
			$hasUpdate = true;
			if($this->fuse <= 0){
				$this->explode();
				return false;
			}
		}elseif($this->fuse < $this->maxFuse){
			$this->fuse = min($this->maxFuse, $this->fuse + $tickDiff);
			$hasUpdate = true;
		}

		return $hasUpdate;
	}

	public function explode() : void{
		$radius = $this->powered ? 6.0 : 3.0;
		$ev = new EntityPreExplodeEvent($this, $radius);
		$ev->call();
		if(!$ev->isCancelled()){
			$explosion = new Explosion(
				Position::fromObject($this->location->add(0, $this->size->getHeight() / 2, 0), $this->getWorld()),
				$ev->getRadius(),
				$this,
				$ev->getFireChance()
			);
			if($ev->isBlockBreaking()){
				$explosion->explodeA();
			}
			$explosion->explodeB();
		}
		$this->flagForDespawn();
	}

	public function getDrops() : array{
		return [
			VanillaItems::GUNPOWDER()->setCount(mt_rand(0, 2))
		];
	}

	public function getXpDropAmount() : int{
		return 5;
	}
}
