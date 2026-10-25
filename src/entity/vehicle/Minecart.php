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

namespace pocketmine\entity\vehicle;

use pocketmine\block\ActivatorRail;
use pocketmine\block\BaseRail;
use pocketmine\block\Block;
use pocketmine\block\DetectorRail;
use pocketmine\block\PoweredRail;
use pocketmine\data\bedrock\block\BlockLegacyMetadata;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Location;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\player\Player;
use function min;
use function sqrt;

class Minecart extends Vehicle{

	public const MAX_SPEED = 0.4; // 8 m/s = 0.4 blocks/tick

	protected float $gravity = 0.04;
	protected float $drag = 0.05;

	public function __construct(
		Location $location,
		protected MinecartType $minecartType = MinecartType::NORMAL,
		?CompoundTag $nbt = null
	){
		$this->maxPassengers = 1;
		parent::__construct($location, $nbt);
	}

	public static function getNetworkTypeId() : string{
		return EntityIds::MINECART;
	}

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(0.7, 0.98);
	}

	public function getMinecartType() : MinecartType{
		return $this->minecartType;
	}

	public function getDropItem() : ?Item{
		return VanillaItems::MINECART();
	}

	public function getSeatOffset(int $seat) : Vector3{
		return new Vector3(0, 0.35, 0);
	}

	public function onRailTick(Block $rail) : void{
		if($rail instanceof PoweredRail){
			if($rail->isPowered()){
				// Acceleration up to MAX_SPEED
				$hSpeed = sqrt($this->motion->x * $this->motion->x + $this->motion->z * $this->motion->z);
				if($hSpeed > 0.01){
					$factor = min(self::MAX_SPEED, $hSpeed + 0.06) / $hSpeed;
					$this->motion = new Vector3($this->motion->x * $factor, $this->motion->y, $this->motion->z * $factor);
				}else{
					// Boost along track
					if($rail->getShape() === BlockLegacyMetadata::RAIL_STRAIGHT_EAST_WEST){
						$this->motion = new Vector3(0.08, $this->motion->y, 0.0);
					}else{
						$this->motion = new Vector3(0.0, $this->motion->y, 0.08);
					}
				}
			}else{
				// Brake friction
				$this->motion = new Vector3($this->motion->x * 0.5, $this->motion->y, $this->motion->z * 0.5);
			}
		}elseif($rail instanceof ActivatorRail){
			if($rail->isPowered()){
				$rider = $this->getRider();
				if($rider !== null){
					$this->removePassenger($rider);
				}
			}
		}elseif($rail instanceof DetectorRail){
			// Activate detector rail in world
			if(!$rail->isActivated()){
				$rail->setActivated(true);
				$this->getWorld()->setBlock($rail->getPosition(), $rail);
			}
			$this->getWorld()->scheduleDelayedBlockUpdate($rail->getPosition(), 20);
		}else{
			// Normal rail friction
			$this->motion = new Vector3($this->motion->x * 0.96, $this->motion->y, $this->motion->z * 0.96);
		}
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		$hasUpdate = parent::entityBaseTick($tickDiff);

		$blockAt = $this->getWorld()->getBlock($this->location->floor());
		$blockBelow = $this->getWorld()->getBlock($this->location->floor()->down());

		if($blockAt instanceof BaseRail){
			$this->onRailTick($blockAt);
		}elseif($blockBelow instanceof BaseRail){
			$this->onRailTick($blockBelow);
		}

		// Keep passenger synced
		$rider = $this->getRider();
		if($rider !== null){
			if(!$rider->isAlive() || $rider->isClosed()){
				$this->removePassenger($rider);
			}else{
				$offset = $this->getSeatOffset(0);
				$targetPos = $this->location->add($offset->x, $offset->y, $offset->z);
				if($rider instanceof Player){
					if($rider->getPosition()->distanceSquared($targetPos) > 16.0){
						$rider->teleport(Location::fromObject($targetPos, $this->getWorld(), $this->location->yaw, $this->location->pitch));
					}
				}else{
					$rider->teleport(Location::fromObject($targetPos, $this->getWorld(), $this->location->yaw, $this->location->pitch));
				}
			}
		}

		return $hasUpdate || $this->motion->lengthSquared() > 0.0001;
	}
}
