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
use function in_array;
use function sin;
use function strtolower;

class Boat extends Entity{
	public static function getNetworkTypeId() : string{ return EntityIds::BOAT; }

	public const DEFAULT_MAX_HEALTH = 40.0;

	protected const TAG_TYPE = "Type";
	protected const TAG_HEALTH = "Health";

	protected BoatType $boatType;

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
		$this->networkProperties->setInt(EntityMetadataProperties::BOAT_BUBBLE_TIME, 0);
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
}
