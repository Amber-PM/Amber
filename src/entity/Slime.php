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

use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\player\Player;
use function mt_rand;

class Slime extends Living{

	public const TAG_SIZE = "Size";

	public const SIZE_SMALL = 1;
	public const SIZE_MEDIUM = 2;
	public const SIZE_LARGE = 4;

	protected int $slimeSize = self::SIZE_SMALL;

	public static function getNetworkTypeId() : string{
		return EntityIds::SLIME;
	}

	public function getName() : string{
		return "Slime";
	}

	public function getDefaultMaxHealth() : int{
		return 1;
	}

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(0.52 * $this->slimeSize, 0.52 * $this->slimeSize);
	}

	protected function initEntity(CompoundTag $nbt) : void{
		$this->slimeSize = $nbt->getInt(self::TAG_SIZE, self::SIZE_SMALL);
		if($this->slimeSize !== 1 && $this->slimeSize !== 2 && $this->slimeSize !== 4){
			$this->slimeSize = self::SIZE_SMALL;
		}

		$maxHp = $this->slimeSize * $this->slimeSize;
		$this->setMaxHealth($maxHp);
		parent::initEntity($nbt);
		if($nbt->getTag("Health") === null){
			$this->setHealth((float) $maxHp);
		}

		$this->updateSizeProperties();
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT();
		$nbt->setInt(self::TAG_SIZE, $this->slimeSize);
		return $nbt;
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);
		$properties->setFloat(EntityMetadataProperties::SCALE, 0.51 * $this->slimeSize);
	}

	public function getSlimeSize() : int{
		return $this->slimeSize;
	}

	public function setSlimeSize(int $size) : void{
		$this->slimeSize = $size;
		$maxHp = $this->slimeSize * $this->slimeSize;
		$this->setMaxHealth($maxHp);
		$this->setHealth((float) $maxHp);
		$this->updateSizeProperties();
		$this->networkPropertiesDirty = true;
	}

	private function updateSizeProperties() : void{
		$dim = 0.52 * $this->slimeSize;
		$this->size = new EntitySizeInfo($dim, $dim);
		$this->setScale(0.51 * $this->slimeSize);
		$this->recalculateBoundingBox();
	}

	public function getAttackDamage() : float{
		return match($this->slimeSize){
			self::SIZE_LARGE => 4.0,
			self::SIZE_MEDIUM => 2.0,
			default => 0.0
		};
	}

	protected function onDeath() : void{
		parent::onDeath();

		// Split into 2-4 smaller slimes if size > 1
		if($this->slimeSize > 1 && !$this->isFlaggedForDespawn()){
			$newSize = (int) ($this->slimeSize / 2);
			$count = mt_rand(2, 4);

			for($i = 0; $i < $count; ++$i){
				$offsetX = (mt_rand(-10, 10) / 10.0) * ($this->slimeSize * 0.5);
				$offsetZ = (mt_rand(-10, 10) / 10.0) * ($this->slimeSize * 0.5);
				$spawnLoc = new Location(
					$this->location->x + $offsetX,
					$this->location->y + 0.5,
					$this->location->z + $offsetZ,
					$this->getWorld(),
					mt_rand(0, 360),
					0
				);

				$child = $this->createChildInstance($spawnLoc, $newSize);
				$child->spawnToAll();
			}
		}
	}

	protected function createChildInstance(Location $location, int $newSize) : Slime{
		$child = new Slime($location);
		$child->setSlimeSize($newSize);
		return $child;
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		$hasUpdate = parent::entityBaseTick($tickDiff);

		if(!$this->isAlive()){
			return $hasUpdate;
		}

		// Contact attack for medium and large slimes
		if($this->slimeSize > 1){
			$damage = $this->getAttackDamage();
			foreach($this->getWorld()->getNearbyEntities($this->boundingBox, $this) as $nearby){
				if($nearby instanceof Player && $nearby->isAlive() && !$nearby->isCreative() && !$nearby->isSpectator()){
					$ev = new EntityDamageByEntityEvent($this, $nearby, EntityDamageEvent::CAUSE_ENTITY_ATTACK, $damage);
					$nearby->attack($ev);
				}
			}
		}

		return $hasUpdate;
	}

	public function getDrops() : array{
		if($this->slimeSize === self::SIZE_SMALL){
			return [
				VanillaItems::SLIMEBALL()->setCount(mt_rand(0, 2))
			];
		}
		return [];
	}

	public function getXpDropAmount() : int{
		return $this->slimeSize;
	}
}
