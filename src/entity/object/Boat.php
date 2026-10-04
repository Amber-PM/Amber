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

namespace pocketmine\entity\object;

use pocketmine\block\BlockTypeIds;
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\Attribute;
use pocketmine\entity\Entity;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Location;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\BoatType;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\SetActorLinkPacket;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityLink;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\player\Player;
use pocketmine\world\particle\BlockBreakParticle;
use pocketmine\world\sound\BlockBreakSound;
use function cos;
use function count;
use function deg2rad;
use function floor;
use function in_array;
use function max;
use function min;
use function sin;
use function sqrt;
use function strtolower;

class Boat extends Entity{
	public static function getNetworkTypeId() : string{ return EntityIds::BOAT; }

	public const DEFAULT_MAX_HEALTH = 40.0;

	protected const TAG_TYPE = "Type";
	protected const TAG_HEALTH = "Health";

	protected BoatType $boatType;

	protected float $paddleTimeLeft = 0.0;
	protected float $paddleTimeRight = 0.0;
	protected int $bubbleTime = 0;

	public function __construct(Location $location, BoatType|CompoundTag|null $typeOrNbt = null, ?CompoundTag $nbt = null){
		if($typeOrNbt instanceof BoatType){
			$this->boatType = $typeOrNbt;
		}else{
			$this->boatType = BoatType::OAK;
			if($typeOrNbt instanceof CompoundTag){
				$nbt = $typeOrNbt;
			}
		}
		parent::__construct($location, $nbt);
	}

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo($this->boatType->isRaft() ? 0.45 : 0.6, 1.4);
	}

	protected function getInitialDragMultiplier() : float{ return 0.05; }

	protected function getInitialGravity() : float{ return 0.04; }

	public function getBoatType() : BoatType{
		return $this->boatType;
	}

	public function isRaft() : bool{
		return $this->boatType->isRaft();
	}

	protected function addAttributes() : void{
		parent::addAttributes();
		$this->setMaxHealth(40);
		$this->setHealth(40.0);
	}

	protected function initBoatProperties() : void{
		$this->addAttributes();
		$this->syncBoatMetadata();
	}

	protected function syncBoatMetadata() : void{
		$this->networkProperties->setInt(EntityMetadataProperties::VARIANT, $this->boatType->getVariantId());
		$this->networkProperties->setInt(EntityMetadataProperties::BOAT_BUBBLE_TIME, $this->bubbleTime);
		$this->networkProperties->setFloat(EntityMetadataProperties::PADDLE_TIME_LEFT, $this->paddleTimeLeft);
		$this->networkProperties->setFloat(EntityMetadataProperties::PADDLE_TIME_RIGHT, $this->paddleTimeRight);
	}

	public function getPaddleTime(int $paddle) : float{
		return $paddle === 0 ? $this->paddleTimeLeft : $this->paddleTimeRight;
	}

	public function setPaddleTime(int $paddle, float $time) : void{
		if($paddle === 0){
			$this->paddleTimeLeft = $time;
			$this->networkProperties->setFloat(EntityMetadataProperties::PADDLE_TIME_LEFT, $time);
		}else{
			$this->paddleTimeRight = $time;
			$this->networkProperties->setFloat(EntityMetadataProperties::PADDLE_TIME_RIGHT, $time);
		}
	}

	public function getBubbleTime() : int{
		return $this->bubbleTime;
	}

	public function setBubbleTime(int $time) : void{
		$this->bubbleTime = $time;
		$this->networkProperties->setInt(EntityMetadataProperties::BOAT_BUBBLE_TIME, $time);
	}

	protected function initEntity(CompoundTag $nbt) : void{
		parent::initEntity($nbt);
		$this->initBoatProperties();
		$this->readSaveData($nbt);
	}

	public function getDropItem() : Item{
		return match($this->boatType){
			BoatType::OAK => VanillaItems::OAK_BOAT(),
			BoatType::SPRUCE => VanillaItems::SPRUCE_BOAT(),
			BoatType::BIRCH => VanillaItems::BIRCH_BOAT(),
			BoatType::JUNGLE => VanillaItems::JUNGLE_BOAT(),
			BoatType::ACACIA => VanillaItems::ACACIA_BOAT(),
			BoatType::DARK_OAK => VanillaItems::DARK_OAK_BOAT(),
			BoatType::MANGROVE => VanillaItems::MANGROVE_BOAT(),
			BoatType::BAMBOO => VanillaItems::BAMBOO_RAFT(),
			BoatType::CHERRY => VanillaItems::CHERRY_BOAT(),
			BoatType::PALE_OAK => VanillaItems::PALE_OAK_BOAT(),
		};
	}

	public function attack(EntityDamageEvent $source) : void{
		if($this->isClosed()){
			return;
		}

		if($source instanceof EntityDamageByEntityEvent){
			$damager = $source->getDamager();
			if($damager instanceof Player && $damager->isCreative()){
				$this->ejectRiders();
				$this->close();
				return;
			}
		}

		$newHealth = $this->getHealth() - $source->getFinalDamage();
		$this->setHealth($newHealth);

		if($newHealth <= 0.0){
			$this->destroyBoat();
		}else{
			$this->networkProperties->setByte(EntityMetadataProperties::HURT_TIME, 10);
			$this->networkProperties->setByte(EntityMetadataProperties::HURT_DIRECTION, 1);
		}
	}

	protected function destroyBoat() : void{
		$this->ejectRiders();

		$world = $this->getWorld();
		if($world->isLoaded()){
			$planks = match($this->boatType){
				BoatType::OAK => VanillaBlocks::OAK_PLANKS(),
				BoatType::SPRUCE => VanillaBlocks::SPRUCE_PLANKS(),
				BoatType::BIRCH => VanillaBlocks::BIRCH_PLANKS(),
				BoatType::JUNGLE => VanillaBlocks::JUNGLE_PLANKS(),
				BoatType::ACACIA => VanillaBlocks::ACACIA_PLANKS(),
				BoatType::DARK_OAK => VanillaBlocks::DARK_OAK_PLANKS(),
				BoatType::MANGROVE => VanillaBlocks::MANGROVE_PLANKS(),
				BoatType::BAMBOO => VanillaBlocks::BAMBOO_PLANKS(),
				BoatType::CHERRY => VanillaBlocks::CHERRY_PLANKS(),
				BoatType::PALE_OAK => VanillaBlocks::PALE_OAK_PLANKS(),
			};
			$world->addSound($this->location, new BlockBreakSound($planks));
			$world->addParticle($this->location, new BlockBreakParticle($planks));
			$world->dropItem($this->location, $this->getDropItem());
		}

		$this->close();
	}

	/** @var array<int, Entity> seat => Entity */
	protected array $riders = [];

	public function getMaxRiders() : int{
		return 2;
	}

	public function getSeatPosition(int $seat) : Vector3{
		return match($seat){
			0 => new Vector3(0.0, 0.35, -0.2),
			1 => new Vector3(0.0, 0.35, 0.5),
			default => new Vector3(0.0, 0.35, 0.0),
		};
	}

	public function isFull() : bool{
		return count($this->riders) >= $this->getMaxRiders();
	}

	public function isRider(Entity $rider) : bool{
		return in_array($rider, $this->riders, true);
	}

	public function getRiderSeat(Entity $rider) : ?int{
		foreach($this->riders as $seat => $r){
			if($r === $rider){
				return $seat;
			}
		}
		return null;
	}

	public function getDriver() : ?Entity{
		return $this->riders[0] ?? null;
	}

	public function getPassenger() : ?Entity{
		return $this->riders[1] ?? null;
	}

	/**
	 * @return array<int, Entity>
	 */
	public function getRiders() : array{
		return $this->riders;
	}

	public function canAddRider(Entity $rider) : bool{
		if($rider->isClosed() || !$rider->isAlive() || $this->isRider($rider) || $this->isFull()){
			return false;
		}
		return true;
	}

	public function addRider(Entity $rider) : bool{
		if(!$this->canAddRider($rider)){
			return false;
		}

		$seat = null;
		for($i = 0; $i < $this->getMaxRiders(); ++$i){
			if(!isset($this->riders[$i])){
				$seat = $i;
				break;
			}
		}

		if($seat === null){
			return false;
		}

		$this->riders[$seat] = $rider;

		$properties = $rider->getNetworkProperties();
		$properties->setGenericFlag(EntityMetadataFlags::RIDING, true);
		$properties->setVector3(EntityMetadataProperties::RIDER_SEAT_POSITION, $this->getSeatPosition($seat));

		$this->broadcastLink($rider, $seat === 0 ? EntityLink::TYPE_RIDER : EntityLink::TYPE_PASSENGER);
		return true;
	}

	public function removeRider(Entity $rider) : bool{
		$seat = $this->getRiderSeat($rider);
		if($seat === null){
			return false;
		}

		unset($this->riders[$seat]);

		$properties = $rider->getNetworkProperties();
		$properties->setGenericFlag(EntityMetadataFlags::RIDING, false);

		$this->broadcastLink($rider, EntityLink::TYPE_REMOVE);

		$world = $this->getWorld();
		if($world->isLoaded() && !$rider->isClosed()){
			$yawRad = deg2rad($this->location->yaw + 90.0);
			$dismountPos = $this->location->add(1.2 * cos($yawRad), 0.0, 1.2 * sin($yawRad));
			$rider->teleport(Location::fromObject($dismountPos, $world, $rider->getLocation()->yaw, $rider->getLocation()->pitch));
		}

		return true;
	}

	public function ejectRiders() : void{
		foreach($this->riders as $rider){
			$this->removeRider($rider);
		}
	}

	protected function broadcastLink(Entity $rider, int $type) : void{
		if(!isset($this->id) || !isset($rider->id)){
			return;
		}
		$packet = SetActorLinkPacket::create(new EntityLink($this->getId(), $rider->getId(), $type, true, false, 0.0));
		$viewers = $this->getViewers();
		if($rider instanceof Player && $rider->isConnected()){
			$viewers[] = $rider;
		}
		foreach($viewers as $viewer){
			if($viewer->isConnected()){
				$viewer->getNetworkSession()->sendDataPacket($packet);
			}
		}
	}

	public function onInteract(Player $player, Vector3 $clickPos) : bool{
		if($player->isSneaking()){
			if($this->isRider($player)){
				$this->removeRider($player);
				return true;
			}
			return false;
		}

		if(!$this->isRider($player) && !$this->isFull()){
			$this->addRider($player);
			return true;
		}

		return false;
	}

	protected function readSaveData(CompoundTag $nbt) : void{
		if(($typeTag = $nbt->getTag(self::TAG_TYPE)) !== null){
			$typeName = $nbt->getString(self::TAG_TYPE, "oak");
			foreach(BoatType::cases() as $case){
				if(strtolower($case->name) === strtolower($typeName)){
					$this->boatType = $case;
					break;
				}
			}
		}
		if($nbt->getTag(self::TAG_HEALTH) !== null){
			$this->setHealth($nbt->getFloat(self::TAG_HEALTH, self::DEFAULT_MAX_HEALTH));
		}
		$this->syncBoatMetadata();
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT();
		$this->writeSaveData($nbt);
		return $nbt;
	}

	protected function writeSaveData(CompoundTag $nbt) : void{
		$nbt->setString(self::TAG_TYPE, strtolower($this->boatType->name));
		$nbt->setFloat(self::TAG_HEALTH, $this->getHealth());
	}

	protected function tryChangeMovement() : void{
		$world = $this->getWorld();
		if(!$world->isLoaded()){
			parent::tryChangeMovement();
			return;
		}

		$floorX = (int) floor($this->location->x);
		$floorY = (int) floor($this->location->y);
		$floorZ = (int) floor($this->location->z);

		$blockAt = $world->getBlockAt($floorX, $floorY, $floorZ);
		$blockBelow = $world->getBlockAt($floorX, (int) floor($this->location->y - 0.5), $floorZ);

		$inWater = $blockAt->getTypeId() === BlockTypeIds::WATER;
		$waterBelow = $blockBelow->getTypeId() === BlockTypeIds::WATER;

		if($inWater || $waterBelow){
			$this->resetFallDistance();

			$bubbleType = 0;
			for($dy = 1; $dy <= 10; ++$dy){
				$underBlock = $world->getBlockAt($floorX, $floorY - $dy, $floorZ);
				$underId = $underBlock->getTypeId();
				if($underId === BlockTypeIds::MAGMA){
					$bubbleType = 1;
					break;
				}
				if($underId === BlockTypeIds::SOUL_SAND){
					$bubbleType = 2;
					break;
				}
				if($underId !== BlockTypeIds::WATER){
					break;
				}
			}

			if($bubbleType === 1){
				// Downward whirlpool
				$this->motion = new Vector3($this->motion->x * 0.9, -0.3, $this->motion->z * 0.9);
				$this->setBubbleTime(min(60, $this->bubbleTime + 1));
				return;
			}
			if($bubbleType === 2){
				// Upward bubble column
				$this->motion = new Vector3($this->motion->x * 0.95, 0.25, $this->motion->z * 0.95);
				$this->setBubbleTime(min(60, $this->bubbleTime + 1));
				return;
			}

			if($this->bubbleTime > 0){
				$this->setBubbleTime(0);
			}

			$waterSurface = $inWater ? ($floorY + 1.0) : ((float) $floorY);
			$submerged = $waterSurface - $this->location->y;

			if($submerged > 0.1){
				$buoyancy = min(0.08, $submerged * 0.1);
				$this->motion = new Vector3($this->motion->x * 0.95, min(0.15, $this->motion->y + $buoyancy), $this->motion->z * 0.95);
			}else{
				$this->motion = new Vector3($this->motion->x * 0.95, max(-0.02, min(0.02, $submerged * 0.05)), $this->motion->z * 0.95);
			}
			$this->onGround = true;
			return;
		}

		if($this->bubbleTime > 0){
			$this->setBubbleTime(0);
		}

		$belowTypeId = $blockBelow->getTypeId();
		$friction = match($belowTypeId){
			BlockTypeIds::BLUE_ICE => 0.989,
			BlockTypeIds::PACKED_ICE, BlockTypeIds::ICE, BlockTypeIds::FROSTED_ICE => 0.98,
			default => $this->onGround ? 0.6 : (1.0 - $this->drag)
		};

		$mY = $this->motion->y;
		if($this->gravityEnabled && !$this->onGround){
			$mY -= $this->gravity;
		}
		$mY *= (1.0 - $this->drag);

		$this->motion = new Vector3($this->motion->x * $friction, $mY, $this->motion->z * $friction);
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		$hasUpdate = parent::entityBaseTick($tickDiff);

		if($this->isClosed() || !$this->isAlive()){
			return $hasUpdate;
		}

		$horizontalSpeedSq = $this->motion->x ** 2 + $this->motion->z ** 2;
		if($horizontalSpeedSq > 0.0001){
			$speed = sqrt($horizontalSpeedSq);
			$delta = min(0.2, $speed * 0.5 * $tickDiff);
			$this->paddleTimeLeft += $delta;
			$this->paddleTimeRight += $delta;
			$this->networkProperties->setFloat(EntityMetadataProperties::PADDLE_TIME_LEFT, $this->paddleTimeLeft);
			$this->networkProperties->setFloat(EntityMetadataProperties::PADDLE_TIME_RIGHT, $this->paddleTimeRight);
			$hasUpdate = true;
		}

		return $hasUpdate;
	}
}
