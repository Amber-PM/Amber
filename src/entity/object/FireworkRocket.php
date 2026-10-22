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
use pocketmine\event\entity\EntityDamageByChildEntityEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\FireworkRocket as FireworkItem;
use pocketmine\item\FireworkRocketExplosion;
use pocketmine\math\Vector3;
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
use pocketmine\world\sound\FireworkExplosionSound;
use pocketmine\world\sound\FireworkLaunchSound;
use function atan2;
use function count;
use function max;
use function sqrt;
use const M_PI;
use const PHP_INT_MAX;

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

	protected int $flightTimeMultiplier = 1;

	public function getFlightTimeMultiplier() : int{
		return $this->flightTimeMultiplier;
	}

	public function setFlightTimeMultiplier(int $flightTimeMultiplier) : self{
		$this->flightTimeMultiplier = $flightTimeMultiplier;
		return $this;
	}

	protected bool $shotFromCrossbow = false;

	public function isShotFromCrossbow() : bool{
		return $this->shotFromCrossbow;
	}

	public function setShotFromCrossbow(bool $shotFromCrossbow = true) : self{
		$this->shotFromCrossbow = $shotFromCrossbow;
		return $this;
	}

	protected bool $hasExploded = false;

	public function hasExploded() : bool{
		return $this->hasExploded;
	}

	protected function move(float $dx, float $dy, float $dz) : void{
		if(!$this->shotFromCrossbow){
			parent::move($dx, $dy, $dz);
			return;
		}

		$this->blocksAround = null;

		$start = $this->location->asVector3();
		$end = $start->add($dx, $dy, $dz);
		$world = $this->getWorld();

		$hitResult = null;

		foreach(VoxelRayTrace::betweenPoints($start, $end) as $vector3){
			$block = $world->getBlockAt((int) $vector3->x, (int) $vector3->y, (int) $vector3->z);
			$blockHitResult = $block->calculateIntercept($start, $end);
			if($blockHitResult !== null){
				$end = $blockHitResult->hitVector;
				$hitResult = $blockHitResult;
				break;
			}
		}

		$entityDistance = PHP_INT_MAX;
		$newDiff = $end->subtractVector($start);
		$ownerId = $this->getOwningEntityId();
		foreach($world->getCollidingEntities($this->boundingBox->addCoord($newDiff->x, $newDiff->y, $newDiff->z)->expand(1, 1, 1), $this) as $entity){
			if($entity->getId() === $ownerId && $this->ticksLived < 5){
				continue;
			}
			if(!$entity instanceof Living || !$entity->isAlive()){
				continue;
			}

			$entityBB = $entity->boundingBox->expandedCopy(0.3, 0.3, 0.3);
			$entityHitResult = $entityBB->calculateIntercept($start, $end);
			if($entityHitResult === null){
				continue;
			}

			$distance = $start->distanceSquared($entityHitResult->hitVector);
			if($distance < $entityDistance){
				$entityDistance = $distance;
				$hitResult = $entityHitResult;
				$end = $entityHitResult->hitVector;
			}
		}

		$f = sqrt($this->motion->x ** 2 + $this->motion->z ** 2);
		$yaw = $f > 0.0001 ? atan2($this->motion->x, $this->motion->z) * 180 / M_PI : $this->location->yaw;
		$pitch = atan2($this->motion->y, $f) * 180 / M_PI;

		$this->location = new Location(
			$end->x,
			$end->y,
			$end->z,
			$this->location->world,
			$yaw,
			$pitch
		);
		$this->recalculateBoundingBox();

		$world->onEntityMoved($this);
		$this->checkBlockIntersections();

		$this->isCollided = $hitResult !== null;
	}

	protected function onFirstUpdate(int $currentTick) : void{
		parent::onFirstUpdate($currentTick);

		$this->broadcastSound(new FireworkLaunchSound());
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		if($this->hasExploded){
			$this->flagForDespawn();
			return false;
		}

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
			if($this->shotFromCrossbow){
				if($this->isCollided){
					$this->explode();
					return true;
				}

				$world = $this->getWorld();
				$ownerId = $this->getOwningEntityId();
				foreach($world->getCollidingEntities($this->boundingBox->expandedCopy(0.2, 0.2, 0.2), $this) as $entity){
					if($entity instanceof Living && ($entity->getId() !== $ownerId || $this->ticksLived >= 5)){
						$this->explode();
						return true;
					}
				}
			}else{
				//Don't keep accelerating long-lived fireworks - this gets very rapidly out of control and makes the server
				//die. Vanilla fireworks will only live for about 52 ticks maximum anyway, so this only makes sure plugin
				//created fireworks don't murder the server
				if(!$this->attachedToPlayer && $this->ticksLived < 60){
					$this->addMotion($this->motion->x * 0.15, 0.04, $this->motion->z * 0.15);
				}
			}

			if($this->ticksLived >= $this->maxFlightTimeTicks){
				$this->detachBoost();
				$this->explode();
				return true;
			}
		}

		return $hasUpdate || $this->attachedToPlayer || $this->hasExploded;
	}

	public function explode() : void{
		if($this->hasExploded){
			return;
		}
		$this->hasExploded = true;
		$this->motion = new Vector3(0, 0, 0);

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
					$owner = $this->getOwningEntity();
					$ev = $owner !== null ?
						new EntityDamageByChildEntityEvent($owner, $this, $entity, EntityDamageEvent::CAUSE_ENTITY_EXPLOSION, $damage) :
						new EntityDamageByEntityEvent($this, $entity, EntityDamageEvent::CAUSE_ENTITY_EXPLOSION, $damage);
					$entity->attack($ev);
					break;
				}
			}
		}else{
			$this->broadcastSound(new FireworkExplosionSound());
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

		// FIREWORK_ITEM contains the item's named tag, not a serialized item stack.
		$fireworksData = CompoundTag::create()
			->setTag(FireworkItem::TAG_FIREWORK_DATA, CompoundTag::create()
				->setByte("Flight", $this->flightTimeMultiplier)
				->setTag(FireworkItem::TAG_EXPLOSIONS, $explosions)
			);

		$properties->setCompoundTag(EntityMetadataProperties::FIREWORK_ITEM, new CacheableNbt($fireworksData));
	}
}
