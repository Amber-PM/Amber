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
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
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
use pocketmine\nbt\tag\FloatTag;
use pocketmine\nbt\tag\ShortTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\player\Player;
use pocketmine\world\particle\HeartParticle;
use pocketmine\world\particle\SmokeParticle;
use function max;
use function min;
use function mt_rand;
use function sqrt;
use function strtolower;

class Wolf extends TameableAnimal{

	public const TAG_ANGRY = "Angry";
	public const TAG_HEALTH = "Health";

	private const MEAT_ITEM_IDS = [
		ItemTypeIds::RAW_BEEF => true,
		ItemTypeIds::STEAK => true,
		ItemTypeIds::RAW_PORKCHOP => true,
		ItemTypeIds::COOKED_PORKCHOP => true,
		ItemTypeIds::RAW_CHICKEN => true,
		ItemTypeIds::COOKED_CHICKEN => true,
		ItemTypeIds::RAW_MUTTON => true,
		ItemTypeIds::COOKED_MUTTON => true,
		ItemTypeIds::RAW_RABBIT => true,
		ItemTypeIds::COOKED_RABBIT => true,
		ItemTypeIds::ROTTEN_FLESH => true,
	];

	protected bool $angry = false;
	protected int $attackCooldownTicks = 0;
	protected ?Entity $combatTarget = null;

	public static function getNetworkTypeId() : string{
		return EntityIds::WOLF;
	}

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(0.85, 0.6);
	}

	public function getName() : string{
		return "Wolf";
	}

	protected function addAttributes() : void{
		parent::addAttributes();
		$this->setMaxHealth(8);
		$this->setHealth(8.0);
	}

	public function getHealthRatio() : float{
		$max = $this->getMaxHealth();
		if($max <= 0){
			return 0.0;
		}
		return $this->getHealth() / $max;
	}

	public function getTailAngle() : float{
		return 0.523 + $this->getHealthRatio() * 0.785;
	}

	public function isAngry() : bool{
		return $this->angry;
	}

	public function setAngry(bool $angry = true) : void{
		$this->angry = $angry;
		$this->networkPropertiesDirty = true;
		if(isset($this->networkProperties)){
			$this->networkProperties->setGenericFlag(EntityMetadataFlags::ANGRY, $angry);
		}
	}

	public function getAttackCooldownTicks() : int{
		return $this->attackCooldownTicks;
	}

	public function setAttackCooldownTicks(int $ticks) : void{
		$this->attackCooldownTicks = $ticks;
	}

	public function setTargetEntity(?Entity $target) : void{
		if($target !== null && !$this->isValidTarget($target)){
			return;
		}
		$this->combatTarget = $target;
		if($target === null){
			$this->targetId = null;
		}else{
			$this->targetId = $target->getId();
		}
		$this->networkPropertiesDirty = true;
	}

	public function getTargetEntity() : ?Entity{
		if($this->combatTarget !== null){
			if(!$this->isValidTarget($this->combatTarget)){
				$this->combatTarget = null;
				$this->targetId = null;
				return null;
			}
			return $this->combatTarget;
		}
		if($this->targetId !== null && isset($this->server)){
			return $this->server->getWorldManager()->findEntity($this->targetId);
		}
		return null;
	}

	public function isValidTarget(?Entity $target) : bool{
		if($target === null || $target->isClosed() || !$target->isAlive() || $target === $this){
			return false;
		}
		if($target->getWorld() !== $this->getWorld()){
			return false;
		}
		$typeId = $target::getNetworkTypeId();
		if($typeId === EntityIds::CREEPER || $typeId === "minecraft:creeper" || ($target instanceof Living && strtolower($target->getName()) === "creeper")){
			return false;
		}
		if($this->isTamed()){
			$ownerUuid = $this->getOwnerUUID();
			if($ownerUuid !== null){
				if($target instanceof Player && $target->getUniqueId()->toString() === $ownerUuid){
					return false;
				}
				if($target instanceof TameableAnimal && $target->isTamed() && $target->getOwnerUUID() === $ownerUuid){
					return false;
				}
			}
		}
		return true;
	}

	public function attackTarget(Entity $target) : bool{
		if($this->isSitting() || !$this->isValidTarget($target)){
			return false;
		}
		if($this->attackCooldownTicks > 0){
			return false;
		}
		if(isset($this->location) && isset($target->location)){
			if($this->location->distance($target->location) > 1.5){
				return false;
			}
		}
		$this->attackCooldownTicks = 20;
		$ev = new EntityDamageByEntityEvent($this, $target, EntityDamageEvent::CAUSE_ENTITY_ATTACK, 4.0);
		$target->attack($ev);
		return true;
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

		$this->setMaxHealth(20);
		$this->setHealth(20.0);

		$this->setAngry(false);
		$this->setSitting(true);

		$this->emitParticle(new HeartParticle());

		return true;
	}

	public static function isMeat(Item $item) : bool{
		return isset(self::MEAT_ITEM_IDS[$item->getTypeId()]);
	}

	public function onInteract(Player $player, Vector3 $clickPos) : bool{
		$item = $player->getInventory()->getItemInHand();

		if($this->isBaby() && self::isMeat($item)){
			if($this->feedBaby()){
				$this->consumeHeldItem($player, $item);
				return true;
			}
			return false;
		}

		if(!$this->isTamed()){
			if($item->getTypeId() === ItemTypeIds::BONE){
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

			if(self::isMeat($item)){
				if($this->getHealth() < $this->getMaxHealth()){
					$healAmount = (float) mt_rand(2, 4);
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

	public function attack(EntityDamageEvent $source) : void{
		parent::attack($source);

		if(!$source->isCancelled() && $source instanceof EntityDamageByEntityEvent){
			$damager = $source->getDamager();
			if($this->isValidTarget($damager)){
				$this->setTargetEntity($damager);
				if(!$this->isTamed()){
					$this->setAngry(true);
					$this->alertNearbyWolves($damager);
				}
			}
		}
	}

	public function alertNearbyWolves(?Entity $attacker) : void{
		if(!isset($this->location) || !isset($this->boundingBox)){
			return;
		}
		$world = $this->getWorld();
		if(!$world->isLoaded()){
			return;
		}

		$bb = $this->boundingBox->expandedCopy(16.0, 16.0, 16.0);
		foreach($world->getNearbyEntities($bb, $this) as $nearby){
			if($nearby instanceof Wolf && !$nearby->isTamed()){
				$nearby->setAngry(true);
				if($attacker !== null && $nearby->isValidTarget($attacker)){
					$nearby->setTargetEntity($attacker);
				}
			}
		}
	}

	public function tickFollowMovement() : void{
		if(!$this->isSitting() && $this->combatTarget !== null){
			if(!$this->isValidTarget($this->combatTarget)){
				$this->combatTarget = null;
				parent::tickFollowMovement();
				return;
			}

			if(!isset($this->location)){
				return;
			}

			if(!isset($this->motion)){
				$this->motion = Vector3::zero();
			}

			$targetLoc = $this->combatTarget->getLocation();
			$dx = $targetLoc->x - $this->location->x;
			$dz = $targetLoc->z - $this->location->z;
			$hDist = sqrt($dx * $dx + $dz * $dz);

			if($hDist <= 1.5){
				$this->attackTarget($this->combatTarget);
				$this->motion->x = 0.0;
				$this->motion->z = 0.0;
			}else{
				$ux = $dx / $hDist;
				$uz = $dz / $hDist;
				$this->motion->x = $ux * 0.35;
				$this->motion->z = $uz * 0.35;
				$this->lookAt($targetLoc);
				if($this->isCollidedHorizontally && $this->onGround){
					$this->motion->y = 0.42;
				}
			}
		}else{
			parent::tickFollowMovement();
		}
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		$hasUpdate = parent::entityBaseTick($tickDiff);

		if($this->attackCooldownTicks > 0){
			$this->attackCooldownTicks = max(0, $this->attackCooldownTicks - $tickDiff);
		}

		if($this->isAlive() && !$this->isSitting()){
			$target = $this->getTargetEntity();
			if($target !== null){
				if(!$this->isValidTarget($target)){
					$this->setTargetEntity(null);
				}elseif($this->attackCooldownTicks <= 0){
					if($this->attackTarget($target)){
						$hasUpdate = true;
					}
				}
			}
		}

		return $hasUpdate;
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);

		$properties->setGenericFlag(EntityMetadataFlags::ANGRY, $this->angry);
	}

	protected function writeSaveData(CompoundTag $nbt) : void{
		parent::writeSaveData($nbt);
		$nbt->setByte(self::TAG_ANGRY, $this->angry ? 1 : 0);
	}

	protected function initEntity(CompoundTag $nbt) : void{
		$ownerTag = $nbt->getTag(TameableAnimal::TAG_OWNER_UUID);
		if($ownerTag instanceof StringTag && $ownerTag->getValue() !== ""){
			$this->setMaxHealth(20);
		}
		parent::initEntity($nbt);
	}

	protected function readSaveData(CompoundTag $nbt) : void{
		parent::readSaveData($nbt);
		$angryTag = $nbt->getTag(self::TAG_ANGRY);
		$this->setAngry($angryTag instanceof ByteTag ? $angryTag->getValue() !== 0 : false);

		if($this->isTamed()){
			$this->setMaxHealth(20);
			if(($healthTag = $nbt->getTag(self::TAG_HEALTH)) instanceof FloatTag){
				$this->setHealth($healthTag->getValue());
			}elseif(($healthShortTag = $nbt->getTag(self::TAG_HEALTH)) instanceof ShortTag){
				$this->setHealth($healthShortTag->getValue());
			}
		}
	}
}
