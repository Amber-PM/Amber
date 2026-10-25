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

use pocketmine\block\BlockTypeIds;
use pocketmine\block\Water;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Location;
use pocketmine\item\Item;
use pocketmine\item\StringToItemParser;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\ShortTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\player\Player;
use function cos;
use function deg2rad;
use function sin;

class Boat extends Vehicle{

	public const TAG_TYPE = "Type";

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

	public function getDropItem() : ?Item{
		$name = $this->boatType->isRaft() ? "bamboo_raft" : ($this->boatType->value . "_boat");
		return StringToItemParser::getInstance()->parse($name);
	}

	public function getSeatOffset(int $seat) : Vector3{
		// In Bedrock: Seat 0 (rider) is at (0, 0.2, -0.2), Seat 1 (passenger) is at (0, 0.2, 0.4)
		return ($seat === 0) ? new Vector3(0, 0.2, -0.2) : new Vector3(0, 0.2, 0.4);
	}

	public function getTransformedSeatOffset(int $seat) : Vector3{
		$base = $this->getSeatOffset($seat);
		$rad = deg2rad($this->location->yaw);
		$cos = cos($rad);
		$sin = sin($rad);
		$rx = $base->x * $cos - $base->z * $sin;
		$rz = $base->x * $sin + $base->z * $cos;
		return new Vector3($rx, $base->y, $rz);
	}

	public function getFriction() : float{
		if(!$this->onGround && !$this->isInWater()){
			return 1.0 - $this->drag;
		}

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

	public function getWaterSurfaceY() : ?float{
		$floor = $this->location->floor();
		$world = $this->getWorld();
		$block = $world->getBlock($floor);
		if(!($block instanceof Water)){
			return null;
		}

		$topY = $floor->y;
		while($topY < $world->getMaxY()){
			$above = $world->getBlockAt($floor->x, $topY + 1, $floor->z);
			if($above instanceof Water){
				$topY++;
			}else{
				break;
			}
		}

		return $topY + 0.8;
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

	protected function tryChangeMovement() : void{
		$friction = $this->getFriction();
		$mY = $this->motion->y;

		if($this->gravityEnabled){
			$mY -= $this->gravity;
		}

		$mY *= (1.0 - $this->drag);

		$this->motion = new Vector3($this->motion->x * $friction, $mY, $this->motion->z * $friction);
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		$hasUpdate = parent::entityBaseTick($tickDiff);

		$surfaceY = $this->getWaterSurfaceY();
		if($surfaceY !== null){
			// Buoyant surface levitation
			$diff = $surfaceY - $this->location->y;
			if($diff > 0.0){
				$this->motion = new Vector3($this->motion->x, $diff * 0.1, $this->motion->z);
			}else{
				$this->motion = new Vector3($this->motion->x, -0.005, $this->motion->z);
			}
		}

		// Synchronize passenger positions
		foreach($this->passengers as $seat => $passenger){
			if(!$passenger->isAlive() || $passenger->isClosed()){
				$this->removePassenger($passenger);
				continue;
			}
			$offset = $this->getTransformedSeatOffset($seat);
			$targetPos = $this->location->add($offset->x, $offset->y, $offset->z);
			if($passenger instanceof Player){
				if($passenger->getPosition()->distanceSquared($targetPos) > 16.0){
					$passenger->teleport(Location::fromObject($targetPos, $this->getWorld(), $this->location->yaw, $this->location->pitch));
				}
			}else{
				$passenger->teleport(Location::fromObject($targetPos, $this->getWorld(), $this->location->yaw, $this->location->pitch));
			}
		}

		return $hasUpdate || $this->motion->lengthSquared() > 0.0001;
	}

	protected function initEntity(CompoundTag $nbt) : void{
		parent::initEntity($nbt);
		if(($typeTag = $nbt->getTag(self::TAG_TYPE)) !== null){
			if($typeTag instanceof StringTag){
				$this->boatType = BoatType::tryFrom($typeTag->getValue()) ?? BoatType::OAK;
			}elseif($typeTag instanceof IntTag || $typeTag instanceof ByteTag || $typeTag instanceof ShortTag){
				$this->boatType = BoatType::fromVariantId($typeTag->getValue());
			}
		}
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT();
		$nbt->setString(self::TAG_TYPE, $this->boatType->value);
		return $nbt;
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);
		$properties->setInt(EntityMetadataProperties::VARIANT, $this->boatType->getVariantId());
		$properties->setFloat(EntityMetadataProperties::PADDLE_TIME_LEFT, $this->paddleTimeLeft);
		$properties->setFloat(EntityMetadataProperties::PADDLE_TIME_RIGHT, $this->paddleTimeRight);
	}
}
