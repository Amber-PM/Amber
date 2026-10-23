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

use pocketmine\block\utils\DyeColor;
use pocketmine\event\entity\EntityRegainHealthEvent;
use pocketmine\event\entity\EntityTameEvent;
use pocketmine\event\entity\PetCollarColorChangeEvent;
use pocketmine\event\entity\PetSitChangeEvent;
use pocketmine\item\Dye;
use pocketmine\item\Item;
use pocketmine\item\ItemTypeIds;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\ShortTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\player\Player;
use pocketmine\world\particle\HeartParticle;
use pocketmine\world\particle\Particle;
use pocketmine\world\particle\SmokeParticle;
use function max;
use function min;
use function mt_rand;
use function sqrt;
use function strtolower;

class Cat extends TameableAnimal{

	public const TAG_CAT_TYPE = "CatType";

	public const TYPE_TABBY = 0;
	public const TYPE_BLACK = 1;
	public const TYPE_RED = 2;
	public const TYPE_SIAMESE = 3;
	public const TYPE_BRITISH_SHORTHAIR = 4;
	public const TYPE_CALICO = 5;
	public const TYPE_PERSIAN = 6;
	public const TYPE_RAGDOLL = 7;
	public const TYPE_WHITE = 8;
	public const TYPE_JELLIE = 9;
	public const TYPE_ALL_BLACK = 10;

	protected int $catType = self::TYPE_TABBY;
	protected int $creeperRepelTicks = 0;

	public static function getNetworkTypeId() : string{
		return EntityIds::CAT;
	}

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(0.7, 0.6);
	}

	public function getName() : string{
		return "Cat";
	}

	protected function addAttributes() : void{
		parent::addAttributes();
		$this->setMaxHealth(10);
		$this->setHealth(10.0);
	}

	public function calculateFallDamage(float $fallDistance) : float{
		return 0.0;
	}

	public function getCatType() : int{
		return $this->catType;
	}

	public function setCatType(int $type) : void{
		$this->catType = max(self::TYPE_TABBY, min(self::TYPE_ALL_BLACK, $type));
		$this->networkPropertiesDirty = true;
		if(isset($this->networkProperties)){
			$this->networkProperties->setInt(EntityMetadataProperties::VARIANT, $this->catType);
		}
	}

	public function tame(Player $player) : bool{
		$ev = new EntityTameEvent($this, $player);
		$ev->call();
		if($ev->isCancelled()){
			return false;
		}

		$this->setTamed(true);
		$this->setOwnerUUID($player->getUniqueId()->toString());
		$this->setOwnerName($player->getName());

		$this->setCollarColor(DyeColor::RED);
		$this->setSitting(true);

		if(isset($this->networkProperties)){
			$this->networkProperties->setLong(EntityMetadataProperties::OWNER_EID, $player->getId());
		}

		$this->emitParticle(new HeartParticle());

		return true;
	}

	public static function isFish(Item $item) : bool{
		$id = $item->getTypeId();
		return $id === ItemTypeIds::RAW_FISH || $id === ItemTypeIds::RAW_SALMON;
	}

	public function onInteract(Player $player, Vector3 $clickPos) : bool{
		$item = $player->getInventory()->getItemInHand();

		if($this->isBaby() && self::isFish($item)){
			if($this->feedBaby()){
				$this->consumeHeldItem($player, $item);
				return true;
			}
			return false;
		}

		if(!$this->isTamed()){
			if(self::isFish($item)){
				$this->consumeHeldItem($player, $item);
				if(mt_rand(1, 3) === 1){
					$this->tame($player);
				}else{
					$this->emitParticle(new SmokeParticle());
				}
				return true;
			}
			return false;
		}

		$ownerUuid = $this->getOwnerUUID();
		if($ownerUuid !== null && $ownerUuid === $player->getUniqueId()->toString()){
			$dyeColor = $this->extractDyeColor($item);
			if($dyeColor !== null && $dyeColor !== $this->collarColor){
				$ev = new PetCollarColorChangeEvent($this, $player, $this->collarColor, $dyeColor);
				$ev->call();
				if(!$ev->isCancelled()){
					$this->setCollarColor($ev->getNewColor());
					$this->consumeHeldItem($player, $item);
					return true;
				}
				return false;
			}

			if(self::isFish($item)){
				if($this->getHealth() < $this->getMaxHealth()){
					$healAmount = 2.0;
					$actualHeal = min((float) $this->getMaxHealth() - $this->getHealth(), $healAmount);
					$ev = new EntityRegainHealthEvent($this, $actualHeal, EntityRegainHealthEvent::CAUSE_EATING);
					$ev->call();
					if($ev->isCancelled()){
						return false;
					}
					$this->setHealth($this->getHealth() + $ev->getAmount());
					$this->consumeHeldItem($player, $item);
					$this->emitParticle(new HeartParticle());
					return true;
				}

				if($this->canBreed() && !$this->isInLove()){
					$this->setInLoveTicks(600);
					$this->consumeHeldItem($player, $item);
					$this->emitParticle(new HeartParticle());
					return true;
				}

				return false;
			}

			$ev = new PetSitChangeEvent($this, $player, !$this->isSitting());
			$ev->call();
			if(!$ev->isCancelled()){
				$this->setSitting($ev->isSitting());
				return true;
			}
			return false;
		}

		return false;
	}

	public function isCreeper(Entity $entity) : bool{
		if($entity->isClosed() || !$entity->isAlive()){
			return false;
		}
		$typeId = $entity::getNetworkTypeId();
		return $typeId === EntityIds::CREEPER
			|| $typeId === "minecraft:creeper"
			|| ($entity instanceof Living && strtolower($entity->getName()) === "creeper");
	}

	public function repelCreepers() : void{
		if(!isset($this->location) || !isset($this->boundingBox)){
			return;
		}
		$world = $this->getWorld();
		if(!$world->isLoaded()){
			return;
		}

		$radius = 10.0;
		$bb = $this->boundingBox->expandedCopy($radius, $radius, $radius);
		foreach($world->getNearbyEntities($bb, $this) as $nearby){
			if($this->isCreeper($nearby) && isset($nearby->location)){
				$distSq = $this->location->distanceSquared($nearby->location);
				if($distSq <= $radius * $radius){
					$dx = $nearby->location->x - $this->location->x;
					$dz = $nearby->location->z - $this->location->z;
					$hDist = sqrt($dx * $dx + $dz * $dz);
					if($hDist > 0.0001){
						$dx /= $hDist;
						$dz /= $hDist;
					}else{
						$dx = 1.0;
						$dz = 0.0;
					}
					$currentMotion = $nearby->getMotion();
					$nearby->setMotion(new Vector3($dx * 0.3, $currentMotion->y, $dz * 0.3));
				}
			}
		}
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		$hasUpdate = parent::entityBaseTick($tickDiff);

		if($this->isAlive() && !$this->isClosed()){
			$this->creeperRepelTicks += $tickDiff;
			if($this->creeperRepelTicks >= 10){
				$this->creeperRepelTicks %= 10;
				$this->repelCreepers();
			}
		}

		return $hasUpdate;
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);

		$properties->setInt(EntityMetadataProperties::VARIANT, $this->catType);
	}

	protected function writeSaveData(CompoundTag $nbt) : void{
		parent::writeSaveData($nbt);
		$nbt->setInt(self::TAG_CAT_TYPE, $this->catType);
	}

	protected function readSaveData(CompoundTag $nbt) : void{
		parent::readSaveData($nbt);
		$catTypeTag = $nbt->getTag(self::TAG_CAT_TYPE);
		if($catTypeTag instanceof IntTag || $catTypeTag instanceof ByteTag || $catTypeTag instanceof ShortTag){
			$this->setCatType($catTypeTag->getValue());
		}else{
			$this->setCatType(mt_rand(self::TYPE_TABBY, self::TYPE_ALL_BLACK));
		}
	}
}
