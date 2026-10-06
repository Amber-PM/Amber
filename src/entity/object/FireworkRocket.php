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

use pocketmine\entity\animation\FireworkParticlesAnimation;
use pocketmine\entity\Entity;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Explosive;
use pocketmine\entity\Living;
use pocketmine\entity\Location;
use pocketmine\entity\NeverSavedWithChunkEntity;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\FireworkRocket as FireworkItem;
use pocketmine\item\FireworkRocketExplosion;
use pocketmine\math\VoxelRayTrace;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\player\Player;
use pocketmine\network\mcpe\protocol\types\CacheableNbt;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\utils\Utils;
use pocketmine\world\sound\FireworkCrackleSound;
use pocketmine\world\sound\FireworkLaunchSound;
use function count;
use function max;
use function sqrt;

class FireworkRocket extends Entity implements Explosive, NeverSavedWithChunkEntity{

	public static function getNetworkTypeId() : string{ return EntityIds::FIREWORKS_ROCKET; }

	protected int $maxFlightTimeTicks;
	private bool $attachedToPlayer = false;

	public function attachTo(Player $player) : void{
		$this->detachBoost();
		$this->setOwningEntity($player);
		$this->attachedToPlayer = true;
		$this->setPosition($player->getPosition());
		$player->addGlideBoost($this);
	}

	public function getRemainingFlightTimeTicks() : int{
		return max(0, $this->maxFlightTimeTicks - $this->ticksLived);
	}

	private function detachBoost() : void{
		if($this->attachedToPlayer){
			$owner = $this->getOwningEntity();
			if($owner instanceof Player && !$owner->isClosed()){
				$owner->removeGlideBoost($this);
			}
		}
	}

	protected function onDispose() : void{
		$this->detachBoost();
		parent::onDispose();
	}

	/** @var FireworkRocketExplosion[] */
	protected array $explosions = [];

	/**
	 * @param FireworkRocketExplosion[] $explosions
	 */
	public function __construct(Location $location, int $maxFlightTimeTicks, array $explosions, ?CompoundTag $nbt = null){
		if($maxFlightTimeTicks < 0){
			throw new \InvalidArgumentException("Life ticks cannot be negative");
		}
		$this->maxFlightTimeTicks = $maxFlightTimeTicks;
		$this->setExplosions($explosions);

		parent::__construct($location, $nbt);
	}

	protected function getInitialSizeInfo() : EntitySizeInfo{ return new EntitySizeInfo(0.25, 0.25); }

	protected function getInitialDragMultiplier() : float{ return 0.0; }

	protected function getInitialGravity() : float{ return 0.0; }

	/**
	 * Returns the total number of ticks the firework will fly for before it explodes.
	 */
	public function getMaxFlightTimeTicks() : int{
		return $this->maxFlightTimeTicks;
	}

	/**
	 * Sets the total number of ticks the firework will fly for before it explodes.
	 *
	 * @return $this
	 */
	public function setMaxFlightTimeTicks(int $maxFlightTimeTicks) : self{
		if($maxFlightTimeTicks < 0){
			throw new \InvalidArgumentException("Max flight time ticks cannot be negative");
		}
		$this->maxFlightTimeTicks = $maxFlightTimeTicks;
		return $this;
	}

	/**
	 * @return FireworkRocketExplosion[]
	 */
	public function getExplosions() : array{
		return $this->explosions;
	}

	/**
	 * @param FireworkRocketExplosion[] $explosions
	 *
	 * @return $this
	 */
	public function setExplosions(array $explosions) : self{
		Utils::validateArrayValueType($explosions, function(FireworkRocketExplosion $_) : void{});
		$this->explosions = $explosions;
		return $this;
	}

	protected function onFirstUpdate(int $currentTick) : void{
		parent::onFirstUpdate($currentTick);

		$this->broadcastSound(new FireworkLaunchSound());
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		if($this->attachedToPlayer){
			$owner = $this->getOwningEntity();
			if(!$owner instanceof Player || $owner->isClosed() || !$owner->isAlive() || $owner->getWorld() !== $this->getWorld()){
				$this->detachBoost();
				$this->flagForDespawn();
				return false;
			}
			$this->setPosition($owner->getPosition());
			$this->updateMovement();
		}
		$hasUpdate = parent::entityBaseTick($tickDiff);

		if(!$this->isFlaggedForDespawn()){
			//Don't keep accelerating long-lived fireworks - this gets very rapidly out of control and makes the server
			//die. Vanilla fireworks will only live for about 52 ticks maximum anyway, so this only makes sure plugin
			//created fireworks don't murder the server
			if(!$this->attachedToPlayer && $this->ticksLived < 60){
				$this->addMotion($this->motion->x * 0.15, 0.04, $this->motion->z * 0.15);
			}

			if($this->ticksLived >= $this->maxFlightTimeTicks){
				$this->detachBoost();
				$this->flagForDespawn();
				$this->explode();
			}
		}

		return $hasUpdate || $this->attachedToPlayer;
	}

	public function explode() : void{
		if(($explosionCount = count($this->explosions)) !== 0){
			$this->broadcastAnimation(new FireworkParticlesAnimation($this));
			foreach($this->explosions as $explosion){
				$this->broadcastSound($explosion->getType()->getExplosionSound());
				if($explosion->willTwinkle()){
					$this->broadcastSound(new FireworkCrackleSound());
				}
			}

			$force = ($explosionCount * 2) + 5;
			$world = $this->getWorld();
			foreach($world->getCollidingEntities($this->getBoundingBox()->expandedCopy(5, 5, 5), $this) as $entity){
				if(!$entity instanceof Living){
					continue;
				}

				$position = $entity->getPosition();
				$distance = $position->distanceSquared($this->location);
				if($distance > 25){
					continue;
				}

				//cast two rays - one to the entity's feet and another to halfway up its body (according to Java, anyway)
				//this seems like it'd miss some cases but who am I to argue with vanilla logic :>
				$height = $entity->getBoundingBox()->getYLength();
				for($i = 0; $i < 2; $i++){
					$target = $position->add(0, 0.5 * $i * $height, 0);
					if(!$target->equals($this->location)){
						foreach(VoxelRayTrace::betweenPoints($this->location, $target) as $blockPos){
							if($world->getBlock($blockPos)->calculateIntercept($this->location, $target) !== null){
								continue 2;
							}
						}
					}

					//no obstruction
					$damage = $force * sqrt((5 - $position->distance($this->location)) / 5);
					$ev = new EntityDamageByEntityEvent($this, $entity, EntityDamageEvent::CAUSE_ENTITY_EXPLOSION, $damage);
					$entity->attack($ev);
					break;
				}
			}
		}
	}

	public function canBeCollidedWith() : bool{
		return false;
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);

		$explosions = new ListTag();
		foreach($this->explosions as $explosion){
			$explosions->push($explosion->toCompoundTag());
		}
		$fireworksData = CompoundTag::create()
			->setTag(FireworkItem::TAG_FIREWORK_DATA, CompoundTag::create()
				->setTag(FireworkItem::TAG_EXPLOSIONS, $explosions)
			);

		$properties->setCompoundTag(EntityMetadataProperties::FIREWORK_ITEM, new CacheableNbt($fireworksData));
	}
}
