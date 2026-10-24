<?php

/*
 *
 *     _             _               
 *    / \\   _ __ ___ | |__   ___ _ __ 
 *   / _ \\ | '_ ` _ \\| '_ \\ / _ \\ '__|
 *  / ___ \\| | | | | | |_) |  __/ |   
 * /_/   \\_\\_| |_| |_|_.__/ \\___|_|   
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

use pocketmine\block\BlockTypeIds;
use pocketmine\block\Water;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Location;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use function cos;
use function deg2rad;
use function sin;

class Boat extends Vehicle{

	protected float $gravity = 0.04;
	protected float $drag = 0.05;

	protected float $paddleTimeLeft = 0.0;
	protected float $paddleTimeRight = 0.0;

	public function __construct(
		Location $location,
		protected BoatType $boatType = BoatType::OAK,
		?CompoundTag $nbt = null
	){
		$this->maxPassengers = 2;
		parent::__construct($location, $nbt);
	}

	public static function getNetworkTypeId() : string{
		return EntityIds::BOAT;
	}

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(0.455, 1.4);
	}

	public function getBoatType() : BoatType{
		return $this->boatType;
	}

	public function getDropItem() : ?\pocketmine\item\Item{
		$name = $this->boatType->isRaft() ? "bamboo_raft" : ($this->boatType->value . "_boat");
		return \pocketmine\item\StringToItemParser::getInstance()->parse($name);
	}

	public function getSeatOffset(int $seat) : Vector3{
		// In Bedrock: Seat 0 (rider) is at (0, 0.2, -0.2), Seat 1 (passenger) is at (0, 0.2, 0.4)
		return ($seat === 0) ? new Vector3(0, 0.2, -0.2) : new Vector3(0, 0.2, 0.4);
	}

	public function getFriction() : float{
		$blockUnder = $this->getWorld()->getBlock($this->location->floor()->down());
		$id = $blockUnder->getTypeId();

		if($id === BlockTypeIds::BLUE_ICE){
			return 0.989;
		}
		if($id === BlockTypeIds::ICE || $id === BlockTypeIds::PACKED_ICE || $id === BlockTypeIds::FROSTED_ICE){
			return 0.98;
		}
		if($this->isInWater()){
			return 0.90;
		}

		return 0.60;
	}

	public function isInWater() : bool{
		$block = $this->getWorld()->getBlock($this->location->floor());
		return $block instanceof Water;
	}

	public function onPaddleTick(bool $left, bool $right) : void{
		$rider = $this->getRider();
		if($rider === null){
			return;
		}

		$yaw = $this->location->yaw;
		if($left && $right){
			// Row straight forward
			$rad = deg2rad($yaw);
			$speed = 0.04;
			$this->motion = $this->motion->add(-sin($rad) * $speed, 0, cos($rad) * $speed);
			$this->paddleTimeLeft += 0.2;
			$this->paddleTimeRight += 0.2;
		}elseif($left){
			// Steer right
			$this->location->yaw += 3.0;
			$this->paddleTimeLeft += 0.2;
		}elseif($right){
			// Steer left
			$this->location->yaw -= 3.0;
			$this->paddleTimeRight += 0.2;
		}

		$this->networkPropertiesDirty = true;
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		$hasUpdate = parent::entityBaseTick($tickDiff);

		if($this->isInWater()){
			// Buoyant surface levitation
			$surfaceY = $this->location->floor()->y + 0.8;
			$diff = $surfaceY - $this->location->y;
			if($diff > 0.0){
				$this->motion = new Vector3($this->motion->x, $diff * 0.1, $this->motion->z);
			}else{
				$this->motion = new Vector3($this->motion->x, -0.005, $this->motion->z);
			}
		}

		// Apply surface friction
		$friction = $this->getFriction();
		$this->motion = new Vector3($this->motion->x * $friction, $this->motion->y, $this->motion->z * $friction);

		// Synchronize passenger positions
		foreach($this->passengers as $seat => $passenger){
			if(!$passenger->isAlive() || $passenger->isClosed()){
				$this->removePassenger($passenger);
				continue;
			}
			$offset = $this->getSeatOffset($seat);
			$passenger->teleport($this->location->add($offset->x, $offset->y, $offset->z));
		}

		return $hasUpdate || $this->motion->lengthSquared() > 0.0001;
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);
		$properties->setFloat(EntityMetadataProperties::PADDLE_TIME_LEFT, $this->paddleTimeLeft);
		$properties->setFloat(EntityMetadataProperties::PADDLE_TIME_RIGHT, $this->paddleTimeRight);
	}
}
