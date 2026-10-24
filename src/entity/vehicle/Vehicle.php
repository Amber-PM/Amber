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

use pocketmine\entity\Entity;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Location;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\Item;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\SetActorLinkPacket;
use pocketmine\network\mcpe\protocol\types\entity\EntityLink;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\player\Player;
use function count;

abstract class Vehicle extends Entity{

	/** @var array<int, Entity> */
	protected array $passengers = [];

	protected int $maxPassengers = 1;
	protected float $health = 40.0;
	protected float $maxHealth = 40.0;

	public function __construct(Location $location, ?CompoundTag $nbt = null){
		parent::__construct($location, $nbt);
	}

	public function getHealth() : float{
		return $this->health;
	}

	public function getMaxHealth() : int{
		return (int) $this->maxHealth;
	}

	abstract public function getDropItem() : ?Item;

	public function attack(EntityDamageEvent $source) : void{
		parent::attack($source);
		if($source->isCancelled()){
			return;
		}

		$damager = null;
		if($source instanceof EntityDamageByEntityEvent){
			$damager = $source->getDamager();
		}

		if($damager instanceof Player && $damager->isCreative()){
			$this->flagForDespawn();
			return;
		}

		$this->health -= $source->getFinalDamage();
		if($this->health <= 0.0){
			$this->flagForDespawn();
			$drop = $this->getDropItem();
			if($drop !== null && $this->location->isValid()){
				$this->getWorld()->dropItem($this->location, $drop);
			}
		}
	}

	public function getMaxPassengers() : int{
		return $this->maxPassengers;
	}

	/**
	 * @return array<int, Entity>
	 */
	public function getPassengers() : array{
		return $this->passengers;
	}

	public function getRider() : ?Entity{
		return $this->passengers[0] ?? null;
	}

	public function isPassenger(Entity $entity) : bool{
		foreach($this->passengers as $p){
			if($p === $entity){
				return true;
			}
		}
		return false;
	}

	public function canAddPassenger(Entity $entity) : bool{
		return count($this->passengers) < $this->maxPassengers && !$this->isPassenger($entity) && $entity->isAlive();
	}

	public function addPassenger(Entity $entity, int $seat = -1) : bool{
		if(!$this->canAddPassenger($entity)){
			return false;
		}

		if($seat < 0 || isset($this->passengers[$seat])){
			for($s = 0; $s < $this->maxPassengers; ++$s){
				if(!isset($this->passengers[$s])){
					$seat = $s;
					break;
				}
			}
		}

		if($seat < 0 || $seat >= $this->maxPassengers){
			return false;
		}

		$this->passengers[$seat] = $entity;
		$entity->getNetworkProperties()->setGenericFlag(EntityMetadataFlags::RIDING, true);

		$type = ($seat === 0) ? EntityLink::TYPE_RIDER : EntityLink::TYPE_PASSENGER;
		$this->broadcastLink($entity, $type);

		$offset = $this->getSeatOffset($seat);
		$entity->teleport($this->location->add($offset->x, $offset->y, $offset->z));

		return true;
	}

	public function removePassenger(Entity $entity) : bool{
		$seat = -1;
		foreach($this->passengers as $s => $p){
			if($p === $entity){
				$seat = $s;
				break;
			}
		}

		if($seat === -1){
			return false;
		}

		unset($this->passengers[$seat]);
		$entity->getNetworkProperties()->setGenericFlag(EntityMetadataFlags::RIDING, false);
		$this->broadcastLink($entity, EntityLink::TYPE_REMOVE);

		return true;
	}

	public function broadcastLink(Entity $passenger, int $type) : void{
		if(!$this->location->isValid()){
			return;
		}
		$world = $this->location->getWorld();
		if(!$world->isLoaded()){
			return;
		}

		$link = new EntityLink(
			fromActorUniqueId: $this->getId(),
			toActorUniqueId: $passenger->getId(),
			type: $type,
			immediate: true,
			causedByRider: $type === EntityLink::TYPE_RIDER,
			vehicleAngularVelocity: 0.0
		);

		$pk = SetActorLinkPacket::create($link);
		$world->broadcastPacketToViewers($this->location, $pk);

		if($passenger instanceof Player && $passenger->isConnected()){
			$passenger->getNetworkSession()->sendDataPacket($pk);
		}
	}

	abstract public function getSeatOffset(int $seat) : Vector3;

	protected function getInitialGravity() : float{
		return 0.04;
	}

	protected function getInitialDragMultiplier() : float{
		return 0.05;
	}

	protected function onDispose() : void{
		$passengers = $this->passengers;
		$this->passengers = [];
		foreach($passengers as $passenger){
			$passenger->getNetworkProperties()->setGenericFlag(EntityMetadataFlags::RIDING, false);
		}
		parent::onDispose();
	}
}
