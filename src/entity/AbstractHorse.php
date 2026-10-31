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

use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityRegainHealthEvent;
use pocketmine\item\Item;
use pocketmine\item\ItemIdentifier;
use pocketmine\item\ItemTypeIds;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\FloatTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\network\mcpe\NetworkBroadcastUtils;
use pocketmine\network\mcpe\protocol\SetActorLinkPacket;
use pocketmine\network\mcpe\protocol\types\entity\EntityLink;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\world\particle\HappyVillagerParticle;
use pocketmine\world\particle\HeartParticle;
use pocketmine\world\particle\SmokeParticle;
use Ramsey\Uuid\Uuid;
use function cos;
use function deg2rad;
use function max;
use function min;
use function mt_rand;
use function sin;
use function strtolower;

abstract class AbstractHorse extends Living implements Ageable{

	public const TAG_TAMED = "Tame";
	public const TAG_OWNER_UUID = "OwnerUUID";
	public const TAG_OWNER_NAME = "OwnerName";
	public const TAG_TEMPER = "Temper";
	public const TAG_SADDLED = "Saddled";
	public const TAG_AGE = "Age";
	public const TAG_IN_LOVE = "InLove";
	public const TAG_BREEDING_COOLDOWN = "BreedingCooldown";
	public const TAG_JUMP_STRENGTH = "JumpStrength";
	public const BREEDING_COOLDOWN_TICKS = 6000;

	protected bool $tamed = false;
	protected ?string $ownerUUID = null;
	protected ?string $ownerName = null;
	protected int $temper = 0;
	protected bool $saddled = false;
	protected int $age = 0;
	protected int $inLoveTicks = 0;
	protected int $breedingCooldownTicks = 0;
	protected ?Player $rider = null;
	protected int $buckingTicks = 0;
	protected float $jumpStrength = 0.7;
	protected float $speedMultiplier = 0.225;
	protected bool $rearing = false;
	protected int $rearingTicks = 0;

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

	public function getTemper() : int{
		return $this->temper;
	}

	public function setTemper(int $temper) : void{
		$this->temper = max(0, min(100, $temper));
	}

	public function increaseTemper(int $amount) : void{
		$this->setTemper($this->temper + $amount);
	}

	public function isSaddled() : bool{
		return $this->saddled;
	}

	public function setSaddled(bool $saddled = true) : void{
		$this->saddled = $saddled;
		$this->networkPropertiesDirty = true;
		if(isset($this->networkProperties)){
			$this->networkProperties->setGenericFlag(EntityMetadataFlags::SADDLED, $saddled);
		}
	}

	public function getJumpStrength() : float{
		return $this->jumpStrength;
	}

	public function setJumpStrength(float $strength) : void{
		$this->jumpStrength = max(0.4, min(1.0, $strength));
	}

	public function isRearing() : bool{
		return $this->rearing;
	}

	public function setRearing(bool $rearing = true) : void{
		$this->rearing = $rearing;
		if($rearing){
			$this->rearingTicks = 20;
		}
		$this->networkPropertiesDirty = true;
		if(isset($this->networkProperties)){
			$this->networkProperties->setGenericFlag(EntityMetadataFlags::REARING, $rearing);
		}
	}

	public function getAge() : int{
		return $this->age;
	}

	public function setAge(int $age) : void{
		$this->age = $age;
		$scale = $age < 0 ? 0.5 : 1.0;
		if($this->getScale() !== $scale){
			$this->setScale($scale);
		}
		if($age <= 0){
			$this->breedingCooldownTicks = 0;
		}else{
			$this->breedingCooldownTicks = $age;
		}
		$this->networkPropertiesDirty = true;
		if(isset($this->networkProperties)){
			$this->networkProperties->setGenericFlag(EntityMetadataFlags::BABY, $this->isBaby());
		}
	}

	public function isBaby() : bool{
		return $this->age < 0;
	}

	public function getBreedingCooldownTicks() : int{
		return $this->breedingCooldownTicks;
	}

	public function setBreedingCooldownTicks(int $ticks) : void{
		$this->breedingCooldownTicks = max(0, $ticks);
		if(!$this->isBaby()){
			$this->age = $this->breedingCooldownTicks;
		}
	}

	public function canBreed() : bool{
		return !$this->isBaby() && $this->isTamed() && $this->breedingCooldownTicks <= 0 && $this->age <= 0;
	}

	public function getInLoveTicks() : int{
		return $this->inLoveTicks;
	}

	public function setInLoveTicks(int $ticks) : void{
		$this->inLoveTicks = max(0, $ticks);
	}

	public function isInLove() : bool{
		return $this->inLoveTicks > 0;
	}

	public function getRider() : ?Player{
		if($this->rider !== null && (!$this->rider->isAlive() || $this->rider->isClosed() || !$this->rider->isOnline())){
			$this->dismountRider();
			return null;
		}
		return $this->rider;
	}

	public function setRider(?Player $rider) : bool{
		if($rider === $this->rider){
			return true;
		}

		if($this->rider !== null){
			$oldRider = $this->rider;
			$this->rider = null;
			$this->broadcastLinkPacket($oldRider, EntityLink::TYPE_REMOVE);
		}

		if($rider !== null){
			if(!$rider->isAlive() || $rider->isClosed() || !$rider->isOnline()){
				return false;
			}
			$this->rider = $rider;
			$this->broadcastLinkPacket($rider, EntityLink::TYPE_RIDER);
			if(!$this->isTamed()){
				$this->buckingTicks = mt_rand(40, 100);
			}
		}

		return true;
	}

	public function dismountRider() : void{
		$this->setRider(null);
	}

	protected function broadcastLinkPacket(Player $target, int $type) : void{
		$link = new EntityLink($this->getId(), $target->getId(), $type, immediate: true, causedByRider: false, vehicleAngularVelocity: 0.0);
		$pk = SetActorLinkPacket::create($link);
		$viewers = $this->getViewers();
		$viewers[] = $target;
		if(count($viewers) > 0){
			try{
				NetworkBroadcastUtils::broadcastPackets($viewers, [$pk]);
			}catch(\Throwable){
				// Sessionless player in mock / testing environment
			}
		}
	}

	public static function isSaddle(Item $item) : bool{
		return strtolower($item->getName()) === "saddle";
	}

	public static function isBreedingItem(Item $item) : bool{
		$id = $item->getTypeId();
		return $id === ItemTypeIds::GOLDEN_CARROT
			|| $id === ItemTypeIds::GOLDEN_APPLE
			|| $id === ItemTypeIds::ENCHANTED_GOLDEN_APPLE;
	}

	public function feedBaby(int $growTicks) : void{
		$newAge = $this->age + $growTicks;
		if($newAge >= 0){
			$newAge = 0;
		}
		$this->setAge($newAge);
		if(isset($this->location)){
			$this->getWorld()->addParticle($this->location->add(0, 1.0, 0), new HappyVillagerParticle());
		}
	}

	protected function consumeHeldItem(Player $player, Item $held) : void{
		if($player->hasFiniteResources()){
			$held->pop();
			$player->getInventory()->setItemInHand($held);
		}
	}

	public function onInteract(Player $player, Vector3 $clickPos) : bool{
		$item = $player->getInventory()->getItemInHand();
		$id = $item->getTypeId();

		// Food processing table
		$foodData = match($id){
			ItemTypeIds::WHEAT => ['heal' => 1.0, 'temper' => 3, 'grow' => 400],
			ItemTypeIds::SUGAR => ['heal' => 1.0, 'temper' => 3, 'grow' => 600],
			ItemTypeIds::APPLE => ['heal' => 2.0, 'temper' => 3, 'grow' => 1200],
			ItemTypeIds::CARROT => ['heal' => 1.0, 'temper' => 3, 'grow' => 1200],
			ItemTypeIds::GOLDEN_CARROT => ['heal' => 4.0, 'temper' => 5, 'grow' => 1200, 'breed' => true],
			ItemTypeIds::GOLDEN_APPLE, ItemTypeIds::ENCHANTED_GOLDEN_APPLE => ['heal' => 10.0, 'temper' => 10, 'grow' => 4800, 'breed' => true],
			default => null
		};

		if($this->isBaby()){
			if($foodData !== null){
				$this->feedBaby($foodData['grow']);
				$this->consumeHeldItem($player, $item);
				return true;
			}
			return false;
		}

		// Adult untamed horse
		if(!$this->isTamed()){
			if($foodData !== null){
				$this->increaseTemper($foodData['temper']);
				if($this->getHealth() < $this->getMaxHealth()){
					$heal = min((float) $this->getMaxHealth() - $this->getHealth(), $foodData['heal']);
					$this->setHealth($this->getHealth() + $heal);
				}
				$this->consumeHeldItem($player, $item);
				if(isset($this->location)){
					$this->getWorld()->addParticle($this->location->add(0, 1.0, 0), new HappyVillagerParticle());
				}
				return true;
			}

			// Mount untamed horse with empty hand or non-food item
			return $this->setRider($player);
		}

		// Adult tamed horse
		// Saddle equipment
		if(!$this->isSaddled() && self::isSaddle($item)){
			$this->setSaddled(true);
			$this->consumeHeldItem($player, $item);
			return true;
		}

		// Shears to remove saddle
		if($this->isSaddled() && $item->getTypeId() === ItemTypeIds::SHEARS && $this->rider === null){
			$this->setSaddled(false);
			$saddle = new Item(new ItemIdentifier(ItemTypeIds::newId()), "Saddle");
			$this->getWorld()->dropItem($this->location, $saddle);
			return true;
		}

		// Breeding
		if(isset($foodData['breed']) && $this->canBreed() && !$this->isInLove()){
			$this->setInLoveTicks(600);
			$this->consumeHeldItem($player, $item);
			if(isset($this->location)){
				$this->getWorld()->addParticle($this->location->add(0, 1.0, 0), new HeartParticle());
			}
			return true;
		}

		// Healing
		if($foodData !== null && $this->getHealth() < $this->getMaxHealth()){
			$heal = min((float) $this->getMaxHealth() - $this->getHealth(), $foodData['heal']);
			$ev = new EntityRegainHealthEvent($this, $heal, EntityRegainHealthEvent::CAUSE_EATING);
			$ev->call();
			if(!$ev->isCancelled()){
				$this->setHealth($this->getHealth() + $ev->getAmount());
				$this->consumeHeldItem($player, $item);
				if(isset($this->location)){
					$this->getWorld()->addParticle($this->location->add(0, 1.0, 0), new HeartParticle());
				}
				return true;
			}
		}

		// Mount tamed horse if not sneaking
		if(!$player->isSneaking()){
			return $this->setRider($player);
		}

		return false;
	}

	public function tame(Player $player) : bool{
		$this->setTamed(true);
		$this->setOwnerUUID($player->getUniqueId()->toString());
		$this->setOwnerName($player->getName());

		if(isset($this->location)){
			$this->getWorld()->addParticle($this->location->add(0, 1.0, 0), new HeartParticle());
		}

		return true;
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		$hasUpdate = parent::entityBaseTick($tickDiff);

		if(!$this->isAlive() || $this->closed){
			if($this->rider !== null){
				$this->dismountRider();
			}
			return $hasUpdate;
		}

		if($this->rearingTicks > 0){
			$this->rearingTicks -= $tickDiff;
			if($this->rearingTicks <= 0){
				$this->setRearing(false);
			}
		}

		if($this->age < 0){
			$newAge = $this->age + $tickDiff;
			if($newAge >= 0){
				$newAge = 0;
			}
			$this->setAge($newAge);
			$hasUpdate = true;
		}elseif($this->age > 0){
			$this->setAge(max(0, $this->age - $tickDiff));
			$hasUpdate = true;
		}elseif($this->breedingCooldownTicks > 0){
			$this->setBreedingCooldownTicks(max(0, $this->breedingCooldownTicks - $tickDiff));
			$hasUpdate = true;
		}

		if($this->inLoveTicks > 0){
			$this->inLoveTicks = max(0, $this->inLoveTicks - $tickDiff);
			if($this->inLoveTicks % 20 === 0 && isset($this->location)){
				$this->getWorld()->addParticle($this->location->add(0, 1.0, 0), new HeartParticle());
			}
			$hasUpdate = true;
		}

		// Rider handling
		if($this->rider !== null){
			$rider = $this->rider;
			if(!$rider->isAlive() || $rider->isClosed() || !$rider->isOnline() || $rider->isSneaking() || $rider->getWorld() !== $this->getWorld()){
				$this->dismountRider();
				return $hasUpdate;
			}

			// Untamed bucking
			if(!$this->isTamed()){
				$this->buckingTicks -= $tickDiff;
				if($this->buckingTicks <= 0){
					if(mt_rand(0, 100) < $this->temper){
						$this->tame($rider);
					}else{
						$this->dismountRider();
						$this->setRearing(true);
						$this->increaseTemper(5);
						if(isset($this->location)){
							$this->getWorld()->addParticle($this->location->add(0, 1.0, 0), new SmokeParticle());
						}
					}
				}
			}elseif($this->isSaddled()){
				// Steered by rider
				$riderYaw = $rider->getLocation()->getYaw();
				$this->setRotation($riderYaw, 0.0);

				// Move along rider heading
				$rad = deg2rad($riderYaw);
				$riderMotion = $rider->getMotion();
				if($riderMotion->lengthSquared() > 0.001){
					$this->motion->x = -sin($rad) * $this->speedMultiplier;
					$this->motion->z = cos($rad) * $this->speedMultiplier;
					if($this->isCollidedHorizontally && $this->onGround){
						$this->motion->y = $this->jumpStrength * 0.6;
					}
				}
			}

			$hasUpdate = true;
		}

		return $hasUpdate;
	}

	public function getDrops() : array{
		$drops = [
			VanillaItems::LEATHER()->setCount(mt_rand(0, 2)),
		];
		if($this->isSaddled()){
			$drops[] = new Item(new ItemIdentifier(ItemTypeIds::newId()), "Saddle");
		}
		return $drops;
	}

	public function getXpDropAmount() : int{
		return mt_rand(1, 3);
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);

		$properties->setGenericFlag(EntityMetadataFlags::TAMED, $this->tamed);
		$properties->setGenericFlag(EntityMetadataFlags::SADDLED, $this->saddled);
		$properties->setGenericFlag(EntityMetadataFlags::BABY, $this->isBaby());
		$properties->setGenericFlag(EntityMetadataFlags::REARING, $this->rearing);
		$properties->setVector3(EntityMetadataProperties::RIDER_SEAT_POSITION, new Vector3(0.0, 1.1, -0.2));
		$properties->setByte(EntityMetadataProperties::CONTROLLING_RIDER_SEAT_NUMBER, 0);

		$owner = $this->getOwner();
		if($owner !== null){
			$properties->setLong(EntityMetadataProperties::OWNER_EID, $owner->getId());
		}else{
			$properties->setLong(EntityMetadataProperties::OWNER_EID, 0);
		}
	}

	protected function writeSaveData(CompoundTag $nbt) : void{
		$nbt->setByte(self::TAG_TAMED, $this->tamed ? 1 : 0);
		if($this->ownerUUID !== null){
			$nbt->setString(self::TAG_OWNER_UUID, $this->ownerUUID);
			if($this->ownerName !== null){
				$nbt->setString(self::TAG_OWNER_NAME, $this->ownerName);
			}
		}
		$nbt->setInt(self::TAG_TEMPER, $this->temper);
		$nbt->setByte(self::TAG_SADDLED, $this->saddled ? 1 : 0);
		$nbt->setInt(self::TAG_AGE, $this->age);
		$nbt->setInt(self::TAG_IN_LOVE, $this->inLoveTicks);
		$nbt->setInt(self::TAG_BREEDING_COOLDOWN, $this->breedingCooldownTicks);
		$nbt->setFloat(self::TAG_JUMP_STRENGTH, $this->jumpStrength);
	}

	protected function readSaveData(CompoundTag $nbt) : void{
		$tamedTag = $nbt->getTag(self::TAG_TAMED);
		$this->setTamed($tamedTag instanceof ByteTag ? $tamedTag->getValue() !== 0 : false);

		$ownerUUID = $nbt->getTag(self::TAG_OWNER_UUID);
		if($ownerUUID instanceof StringTag && $ownerUUID->getValue() !== ""){
			$this->ownerUUID = $ownerUUID->getValue();
		}

		$ownerName = $nbt->getTag(self::TAG_OWNER_NAME);
		if($ownerName instanceof StringTag && $ownerName->getValue() !== ""){
			$this->ownerName = $ownerName->getValue();
		}

		$this->setTemper($nbt->getInt(self::TAG_TEMPER, 0));
		$saddledTag = $nbt->getTag(self::TAG_SADDLED);
		$this->setSaddled($saddledTag instanceof ByteTag ? $saddledTag->getValue() !== 0 : false);

		$this->setAge($nbt->getInt(self::TAG_AGE, 0));
		$this->setInLoveTicks($nbt->getInt(self::TAG_IN_LOVE, 0));
		$this->setBreedingCooldownTicks($nbt->getInt(self::TAG_BREEDING_COOLDOWN, max(0, $this->age)));

		$jumpTag = $nbt->getTag(self::TAG_JUMP_STRENGTH);
		$this->setJumpStrength($jumpTag instanceof FloatTag ? $jumpTag->getValue() : 0.7);
	}
}
