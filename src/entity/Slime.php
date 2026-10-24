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

use pocketmine\entity\Location;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\player\Player;
use function atan2;
use function cos;
use function max;
use function mt_rand;
use function sin;
use const M_PI;

class Slime extends Living{

	public const TAG_SIZE = "Size";

	public static function getNetworkTypeId() : string{
		return EntityIds::SLIME;
	}

	protected int $slimeSize = 1;
	protected int $jumpDelay = 20;
	protected ?Player $target = null;

	public function getSlimeSize() : int{
		return $this->slimeSize;
	}

	public function setSlimeSize(int $size) : void{
		$this->slimeSize = max(1, $size);
		$maxHealth = $this->slimeSize * $this->slimeSize;
		$this->setMaxHealth($maxHealth);
		$this->setHealth((float) $maxHealth);
		$this->setSize(new EntitySizeInfo($this->slimeSize * 0.52, $this->slimeSize * 0.52));
		$this->networkPropertiesDirty = true;
	}

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(0.52, 0.52);
	}

	public function getName() : string{
		return "Slime";
	}

	protected function initEntity(CompoundTag $nbt) : void{
		parent::initEntity($nbt);
		if(($sizeTag = $nbt->getTag(self::TAG_SIZE)) instanceof IntTag){
			$this->setSlimeSize($sizeTag->getValue());
		}else{
			$this->setSlimeSize(1);
		}
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT();
		$nbt->setInt(self::TAG_SIZE, $this->slimeSize);
		return $nbt;
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);
		$properties->setFloat(EntityMetadataProperties::SCALE, $this->slimeSize * 0.52);
	}

	public function calculateFallDamage(float $fallDistance) : float{
		return 0.0;
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		$hasUpdate = parent::entityBaseTick($tickDiff);

		if(!$this->isAlive()){
			return $hasUpdate;
		}

		if($this->onGround){
			if($this->jumpDelay <= 0){
				$this->jump();
				$this->jumpDelay = mt_rand(10, 30);
			}else{
				$this->jumpDelay -= $tickDiff;
				$this->motion = new Vector3(0, $this->motion->y, 0);
			}
		}

		// Check player attack on collision
		if($this->slimeSize > 1){
			$this->checkCollisionAttack();
		}

		return $hasUpdate;
	}

	public function jump() : void{
		$this->findNearestTarget();

		$yaw = $this->location->yaw;
		if($this->target !== null && $this->target->isAlive()){
			$tPos = $this->target->getLocation();
			$xDist = $tPos->x - $this->location->x;
			$zDist = $tPos->z - $this->location->z;
			$yaw = atan2($zDist, $xDist) * 180 / M_PI - 90;
			$this->location->yaw = $yaw;
		}else{
			$yaw = mt_rand(0, 359);
			$this->location->yaw = (float) $yaw;
		}

		$speed = 0.15 * ($this->slimeSize >= 2 ? 1.5 : 1.0);
		$rad = $yaw * M_PI / 180;
		$motionX = -sin($rad) * $speed;
		$motionZ = cos($rad) * $speed;
		$motionY = 0.42;

		$this->setMotion(new Vector3($motionX, $motionY, $motionZ));
	}

	private function checkCollisionAttack() : void{
		$damage = $this->slimeSize;
		foreach($this->getWorld()->getNearbyEntities($this->boundingBox->expandedCopy(0.2, 0.2, 0.2)) as $entity){
			if($entity instanceof Player && $entity->isAlive() && !$entity->isCreative() && !$entity->isSpectator()){
				$ev = new EntityDamageByEntityEvent($this, $entity, EntityDamageEvent::CAUSE_ENTITY_ATTACK, (float) $damage);
				$entity->attack($ev);
			}
		}
	}

	private function findNearestTarget() : void{
		$closest = null;
		$closestDist = 256.0; // 16 blocks squared
		foreach($this->getWorld()->getPlayers() as $player){
			if(!$player->isAlive() || $player->isCreative() || $player->isSpectator()){
				continue;
			}
			$dist = $this->location->distanceSquared($player->getLocation());
			if($dist < $closestDist){
				$closestDist = $dist;
				$closest = $player;
			}
		}
		$this->target = $closest;
	}

	protected function onDeath() : void{
		parent::onDeath();

		if($this->slimeSize > 1){
			$newSize = (int) ($this->slimeSize / 2);
			$count = mt_rand(2, 4);
			for($i = 0; $i < $count; ++$i){
				$offsetX = (mt_rand(-5, 5) / 10) * $this->slimeSize * 0.2;
				$offsetZ = (mt_rand(-5, 5) / 10) * $this->slimeSize * 0.2;
				$loc = Location::fromObject($this->location->add($offsetX, 0.2, $offsetZ), $this->getWorld());
				$baby = $this->createBabySlime($loc, $newSize);
				$baby->spawnToAll();
				$baby->setMotion(new Vector3($offsetX * 0.5, 0.3, $offsetZ * 0.5));
			}
		}
	}

	protected function createBabySlime(Location $location, int $size) : Slime{
		$slime = new Slime($location);
		$slime->setSlimeSize($size);
		return $slime;
	}

	public function getDrops() : array{
		if($this->slimeSize === 1){
			return [
				VanillaItems::SLIMEBALL()->setCount(mt_rand(0, 2))
			];
		}
		return [];
	}

	public function getXpDropAmount() : int{
		return $this->slimeSize === 1 ? 1 : 0;
	}
}
