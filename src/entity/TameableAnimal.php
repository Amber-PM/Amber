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

use pocketmine\block\BaseFire;
use pocketmine\block\Block;
use pocketmine\block\Cactus;
use pocketmine\block\Lava;
use pocketmine\block\Magma;
use pocketmine\block\utils\DyeColor;
use pocketmine\data\bedrock\DyeColorIdMap;
use pocketmine\entity\effect\EffectManager;
use pocketmine\inventory\ArmorInventory;
use pocketmine\item\Dye;
use pocketmine\item\Item;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\world\particle\HappyVillagerParticle;
use pocketmine\world\particle\HeartParticle;
use pocketmine\world\particle\Particle;
use Ramsey\Uuid\Uuid;
use function floor;
use function max;
use function min;
use function mt_rand;
use function sqrt;

/**
 * @phpstan-consistent-constructor
 */
abstract class TameableAnimal extends Living implements Ageable{

	public const TAG_OWNER_UUID = "OwnerUUID";
	public const TAG_OWNER_NAME = "OwnerName";
	public const TAG_SITTING = "Sitting";
	public const TAG_COLLAR_COLOR = "CollarColor";
	public const TAG_IN_LOVE = "InLove";
	public const TAG_AGE = "Age";

	protected ?string $ownerUUID = null;
	protected ?string $ownerName = null;
	protected bool $sitting = false;
	protected DyeColor $collarColor = DyeColor::RED;
	protected int $inLoveTicks = 0;
	protected int $age = 0;
	protected bool $tamed = false;

	protected function initEntity(CompoundTag $nbt) : void{
		parent::initEntity($nbt);
		$this->readSaveData($nbt);
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT();
		$this->writeSaveData($nbt);
		return $nbt;
	}

	public function isTamed() : bool{
		return $this->tamed;
	}

	public function setTamed(bool $tamed = true) : void{
		$this->tamed = $tamed;
		$this->networkPropertiesDirty = true;
		if(isset($this->networkProperties)){
			$this->networkProperties->setGenericFlag(EntityMetadataFlags::TAMED, $tamed);
		}
	}

	public function getOwnerUUID() : ?string{
		return $this->ownerUUID;
	}

	public function setOwnerUUID(?string $uuid) : void{
		$this->ownerUUID = $uuid;
	}

	public function getOwnerName() : ?string{
		return $this->ownerName;
	}

	public function setOwnerName(?string $name) : void{
		$this->ownerName = $name;
	}

	public function getOwner() : ?Player{
		if($this->ownerUUID === null){
			return null;
		}

		if(isset($this->location)){
			$world = $this->location->getWorld();
			if($world->isLoaded()){
				foreach($world->getPlayers() as $player){
					if($player->getUniqueId()->toString() === $this->ownerUUID){
						return $player;
					}
				}
			}
		}

		if(Uuid::isValid($this->ownerUUID)){
			try{
				$player = Server::getInstance()->getPlayerByUUID(Uuid::fromString($this->ownerUUID));
				if($player !== null){
					return $player;
				}
			}catch(\Throwable){
				// Server or UUID lookup error
			}
		}

		return null;
	}

	public function isSitting() : bool{
		return $this->sitting;
	}

	public function setSitting(bool $sitting = true) : void{
		$this->sitting = $sitting;
		$this->networkPropertiesDirty = true;
		if(isset($this->networkProperties)){
			$this->networkProperties->setGenericFlag(EntityMetadataFlags::SITTING, $sitting);
		}
	}

	public function getCollarColor() : DyeColor{
		return $this->collarColor;
	}

	public function setCollarColor(DyeColor $color) : void{
		$this->collarColor = $color;
		$this->networkPropertiesDirty = true;
		if(isset($this->networkProperties)){
			$this->networkProperties->setByte(EntityMetadataProperties::COLOR, DyeColorIdMap::getInstance()->toId($color));
		}
	}

	public function getAge() : int{
		return $this->age;
	}

	public function setAge(int $age) : void{
		$this->age = $age;
		$this->networkPropertiesDirty = true;
		if(isset($this->networkProperties)){
			$this->networkProperties->setGenericFlag(EntityMetadataFlags::BABY, $this->isBaby());
		}
	}

	public function isBaby() : bool{
		return $this->age < 0;
	}

	public function getInLoveTicks() : int{
		return $this->inLoveTicks;
	}

	public function setInLoveTicks(int $ticks) : void{
		$this->inLoveTicks = $ticks;
	}

	public function isInLove() : bool{
		return $this->inLoveTicks > 0;
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);

		$properties->setGenericFlag(EntityMetadataFlags::TAMED, $this->tamed);
		$properties->setGenericFlag(EntityMetadataFlags::SITTING, $this->sitting);
		$properties->setGenericFlag(EntityMetadataFlags::BABY, $this->isBaby());
		$properties->setByte(EntityMetadataProperties::COLOR, DyeColorIdMap::getInstance()->toId($this->collarColor));

		$owner = $this->getOwner();
		if($owner !== null){
			$properties->setLong(EntityMetadataProperties::OWNER_EID, $owner->getId());
		}else{
			$properties->setLong(EntityMetadataProperties::OWNER_EID, 0);
		}
	}

	protected function writeSaveData(CompoundTag $nbt) : void{
		if($this->ownerUUID !== null){
			$nbt->setString(self::TAG_OWNER_UUID, $this->ownerUUID);
			if($this->ownerName !== null){
				$nbt->setString(self::TAG_OWNER_NAME, $this->ownerName);
			}
		}
		$nbt->setByte(self::TAG_SITTING, $this->sitting ? 1 : 0);
		$nbt->setByte(self::TAG_COLLAR_COLOR, DyeColorIdMap::getInstance()->toId($this->collarColor));
		$nbt->setInt(self::TAG_AGE, $this->age);
		$nbt->setInt(self::TAG_IN_LOVE, $this->inLoveTicks);
	}

	public function saveNBTData(CompoundTag $nbt) : void{
		$this->writeSaveData($nbt);
	}

	protected function readSaveData(CompoundTag $nbt) : void{
		$ownerUUID = $nbt->getTag(self::TAG_OWNER_UUID);
		if($ownerUUID instanceof StringTag && $ownerUUID->getValue() !== ""){
			$this->ownerUUID = $ownerUUID->getValue();
			$this->setTamed(true);
		}else{
			$this->ownerUUID = null;
		}

		$ownerName = $nbt->getTag(self::TAG_OWNER_NAME);
		if($ownerName instanceof StringTag && $ownerName->getValue() !== ""){
			$this->ownerName = $ownerName->getValue();
		}else{
			$this->ownerName = null;
		}

		$sittingTag = $nbt->getTag(self::TAG_SITTING);
		$this->setSitting($sittingTag instanceof ByteTag ? $sittingTag->getValue() !== 0 : false);

		$colorTag = $nbt->getTag(self::TAG_COLLAR_COLOR);
		$this->setCollarColor($colorTag instanceof ByteTag ? self::colorFromId($colorTag->getValue()) : DyeColor::RED);

		$this->setAge($nbt->getInt(self::TAG_AGE, 0));
		$this->setInLoveTicks($nbt->getInt(self::TAG_IN_LOVE, 0));
	}

	public function readNBTData(CompoundTag $nbt) : void{
		$this->readSaveData($nbt);
	}

	private static function colorFromId(int $id) : DyeColor{
		$mapped = DyeColorIdMap::getInstance()->fromId($id);
		if($mapped !== null){
			return $mapped;
		}
		foreach(DyeColor::cases() as $case){
			if($case->id() === $id){
				return $case;
			}
		}
		return DyeColor::RED;
	}

	public function lookAt(Vector3 $target) : void{
		if(!isset($this->location) || !isset($this->size)){
			return;
		}
		parent::lookAt($target);
	}

	public function feedBaby() : bool{
		if(!$this->isBaby()){
			return false;
		}

		$newAge = $this->age + 2400;
		if($newAge >= 0){
			$newAge = 0;
		}
		$this->setAge($newAge);

		$this->emitParticle(new HappyVillagerParticle());

		return true;
	}

	public function isDangerousBlock(Block $block) : bool{
		return $block instanceof Lava
			|| $block instanceof BaseFire
			|| $block instanceof Magma
			|| $block instanceof Cactus;
	}

	public function tickFollowMovement() : void{
		if(!isset($this->location)){
			return;
		}

		if(!isset($this->motion)){
			$this->motion = Vector3::zero();
		}

		if($this->isSitting() || !$this->isTamed()){
			$this->motion->x = 0.0;
			$this->motion->z = 0.0;
			return;
		}

		$owner = $this->getOwner();
		if(
			$owner === null ||
			!$owner->isAlive() ||
			$owner->isClosed() ||
			!$owner->isOnline() ||
			$owner->getWorld() !== $this->getWorld()
		){
			$this->motion->x = 0.0;
			$this->motion->z = 0.0;
			return;
		}

		$ownerLocation = $owner->getLocation();
		$d = $this->location->distance($ownerLocation);

		if($d <= 3.0){
			$this->motion->x = 0.0;
			$this->motion->z = 0.0;
			$this->lookAt($ownerLocation);
		}elseif($d <= 12.0){
			$dx = $ownerLocation->x - $this->location->x;
			$dz = $ownerLocation->z - $this->location->z;
			$dXZ = sqrt($dx * $dx + $dz * $dz);
			if($dXZ > 0.0001){
				$ux = $dx / $dXZ;
				$uz = $dz / $dXZ;
			}else{
				$ux = 0.0;
				$uz = 0.0;
			}

			$this->motion->x = $ux * 0.25;
			$this->motion->z = $uz * 0.25;
			$this->lookAt($ownerLocation);

			if($this->isCollidedHorizontally && $this->onGround){
				$this->motion->y = 0.42;
			}
		}else{
			if(!$this->attemptSafeTeleportToOwner($ownerLocation)){
				$this->motion->x = 0.0;
				$this->motion->z = 0.0;
			}
		}
	}

	public function attemptSafeTeleportToOwner(Vector3 $ownerLocation) : bool{
		$baseX = (int) floor($ownerLocation->x);
		$baseY = (int) floor($ownerLocation->y);
		$baseZ = (int) floor($ownerLocation->z);
		$world = $this->getWorld();

		foreach([0, -1, 1] as $dy){
			$targetY = $baseY + $dy;
			for($dx = -1; $dx <= 1; ++$dx){
				for($dz = -1; $dz <= 1; ++$dz){
					$targetX = $baseX + $dx;
					$targetZ = $baseZ + $dz;

					try{
						$floor = $world->getBlockAt($targetX, $targetY - 1, $targetZ);
						$target = $world->getBlockAt($targetX, $targetY, $targetZ);
						$above = $world->getBlockAt($targetX, $targetY + 1, $targetZ);
					}catch(\Throwable){
						continue;
					}

					if(
						$floor->isSolid() && !$this->isDangerousBlock($floor) &&
						!$target->isSolid() && !$this->isDangerousBlock($target) &&
						!$above->isSolid() && !$this->isDangerousBlock($above)
					){
						$safePos = new Vector3($targetX + 0.5, (float) $targetY, $targetZ + 0.5);
						if(!isset($this->lastLocation)){
							$this->lastLocation = clone $this->location;
						}
						if(!isset($this->lastMotion)){
							$this->lastMotion = clone $this->motion;
						}
						$this->teleport($safePos);
						$this->setMotion(Vector3::zero());
						$this->lookAt($ownerLocation);
						return true;
					}
				}
			}
		}

		return false;
	}

	public function findEligibleMate() : ?self{
		if(!isset($this->location) || $this->ownerUUID === null || $this->isBaby() || $this->isSitting() || !$this->isTamed()){
			return null;
		}
		$world = $this->getWorld();
		if(!$world->isLoaded()){
			return null;
		}

		$candidates = isset($this->boundingBox)
			? $world->getNearbyEntities($this->boundingBox->expandedCopy(8.0, 8.0, 8.0), $this)
			: $world->getEntities();

		foreach($candidates as $nearby){
			if(
				$nearby instanceof static &&
				$nearby !== $this &&
				$nearby->isAlive() &&
				!$nearby->isClosed() &&
				$nearby->isTamed() &&
				$nearby->getOwnerUUID() === $this->ownerUUID &&
				$nearby->isInLove() &&
				$nearby->getAge() >= 0 &&
				!$nearby->isSitting() &&
				isset($nearby->location) &&
				$this->location->distance($nearby->location) <= 8.0
			){
				return $nearby;
			}
		}

		return null;
	}

	public function breed(self $partner) : ?self{
		if(!isset($this->location) || !isset($partner->location)){
			return null;
		}

		$midX = ($this->location->x + $partner->location->x) / 2.0;
		$midY = ($this->location->y + $partner->location->y) / 2.0;
		$midZ = ($this->location->z + $partner->location->z) / 2.0;
		$midLocation = new Location($midX, $midY, $midZ, $this->getWorld(), 0.0, 0.0);

		$baby = $this->createBabyEntity($midLocation);
		$baby->setAge(-24000);
		$baby->setTamed(true);
		$baby->setOwnerUUID($this->getOwnerUUID());
		$baby->setOwnerName($this->getOwnerName());
		$baby->setCollarColor(mt_rand(0, 1) === 0 ? $this->getCollarColor() : $partner->getCollarColor());

		if($this instanceof Cat && $partner instanceof Cat && $baby instanceof Cat){
			$baby->setCatType(mt_rand(0, 1) === 0 ? $this->getCatType() : $partner->getCatType());
		}

		if($baby instanceof Wolf){
			$baby->setMaxHealth(20);
			$baby->setHealth(20.0);
		}

		try{
			$baby->spawnToAll();
		}catch(\Throwable){
		}

		$this->setInLoveTicks(0);
		$partner->setInLoveTicks(0);

		if(isset($this->location)){
			$world = $this->getWorld();
			if($world->isLoaded()){
				$world->addParticle($midLocation->add(0, 0.5, 0), new HeartParticle());
			}
		}

		return $baby;
	}

	protected function createBabyEntity(Location $location) : static{
		try{
			return new static($location);
		}catch(\Throwable){
			$baby = (new \ReflectionClass(static::class))->newInstanceWithoutConstructor();
			(new \ReflectionProperty(Entity::class, "closed"))->setValue($baby, false);
			(new \ReflectionProperty(Entity::class, "id"))->setValue($baby, Entity::nextRuntimeId());
			(new \ReflectionProperty(Entity::class, "size"))->setValue($baby, $this->getInitialSizeInfo());
			(new \ReflectionProperty(Entity::class, "scale"))->setValue($baby, 1.0);
			(new \ReflectionProperty(Entity::class, "networkProperties"))->setValue($baby, new EntityMetadataCollection());
			(new \ReflectionProperty(Entity::class, "attributeMap"))->setValue($baby, new AttributeMap());
			(new \ReflectionProperty(Living::class, "effectManager"))->setValue($baby, new EffectManager($baby));
			(new \ReflectionProperty(Living::class, "armorInventory"))->setValue($baby, new ArmorInventory($baby));
			(new \ReflectionProperty(Entity::class, "location"))->setValue($baby, $location);
			(new \ReflectionProperty(Entity::class, "motion"))->setValue($baby, Vector3::zero());
			(new \ReflectionProperty(Entity::class, "lastLocation"))->setValue($baby, clone $location);
			(new \ReflectionProperty(Entity::class, "lastMotion"))->setValue($baby, Vector3::zero());
			$width = $baby->size->getWidth();
			$height = $baby->size->getHeight();
			(new \ReflectionProperty(Entity::class, "boundingBox"))->setValue($baby, new AxisAlignedBB(
				$location->x - $width / 2,
				$location->y,
				$location->z - $width / 2,
				$location->x + $width / 2,
				$location->y + $height,
				$location->z + $width / 2
			));
			$baby->addAttributes();
			try{
				$location->getWorld()->addEntity($baby);
			}catch(\Throwable){
			}
			return $baby;
		}
	}

	protected function emitParticle(Particle $particle) : void{
		if(isset($this->location)){
			$world = $this->getWorld();
			if($world->isLoaded()){
				$height = isset($this->size) ? $this->size->getHeight() * 0.5 : 0.5;
				$world->addParticle($this->location->add(0, $height, 0), $particle);
			}
		}
	}

	protected function extractDyeColor(Item $item) : ?DyeColor{
		if($item instanceof Dye){
			return $item->getColor();
		}
		if(method_exists($item, "getColor")){
			$color = $item->getColor();
			if($color instanceof DyeColor){
				return $color;
			}
		}
		return null;
	}

	protected function consumeHeldItem(Player $player, Item $held) : void{
		if($player->hasFiniteResources()){
			$held->pop();
			$player->getInventory()->setItemInHand($held);
		}
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		$hasUpdate = parent::entityBaseTick($tickDiff);

		if(!$this->isAlive() || $this->closed){
			return $hasUpdate;
		}

		if($this->age < 0){
			$newAge = $this->age + $tickDiff;
			if($newAge >= 0){
				$newAge = 0;
			}
			$this->setAge($newAge);
			$hasUpdate = true;
		}

		if($this->inLoveTicks > 0){
			$this->inLoveTicks = max(0, $this->inLoveTicks - $tickDiff);
			if($this->inLoveTicks % 20 === 0){
				$this->emitParticle(new HeartParticle());
			}

			$mate = null;
			if(!$this->isSitting() && !$this->isBaby()){
				$mate = $this->findEligibleMate();
			}

			if($mate !== null && isset($this->location) && isset($mate->location)){
				$dist = $this->location->distance($mate->location);
				if($dist <= 1.5){
					$this->breed($mate);
				}else{
					$dx = $mate->location->x - $this->location->x;
					$dz = $mate->location->z - $this->location->z;
					$hDist = sqrt($dx * $dx + $dz * $dz);
					if($hDist > 0.0001){
						$ux = $dx / $hDist;
						$uz = $dz / $hDist;
					}else{
						$ux = 0.0;
						$uz = 0.0;
					}
					if(!isset($this->motion)){
						$this->motion = Vector3::zero();
					}
					$this->motion->x = $ux * 0.25;
					$this->motion->z = $uz * 0.25;
					$this->lookAt($mate->location);
					if($this->isCollidedHorizontally && $this->onGround){
						$this->motion->y = 0.42;
					}
				}
				$hasUpdate = true;
			}else{
				$this->tickFollowMovement();
			}
		}else{
			$this->tickFollowMovement();
		}

		return $hasUpdate;
	}
}
