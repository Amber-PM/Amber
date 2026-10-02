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

use pocketmine\block\BlockTypeIds;
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\animation\HurtAnimation;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Living;
use pocketmine\event\entity\ArmorStandEquipEvent;
use pocketmine\event\entity\ArmorStandPoseChangeEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\inventory\ArmorInventory;
use pocketmine\item\Armor;
use pocketmine\item\Item;
use pocketmine\item\ItemBlock;
use pocketmine\item\NameTag;
use pocketmine\item\Tool;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\nbt\NBT;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\network\mcpe\protocol\MobEquipmentPacket;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\network\mcpe\protocol\types\inventory\ContainerIds;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStackWrapper;
use pocketmine\player\Player;
use pocketmine\world\particle\BlockBreakParticle;
use pocketmine\world\sound\ArmorEquipGenericSound;
use pocketmine\world\sound\BlockBreakSound;
use pocketmine\world\sound\BlockPunchSound;
use pocketmine\world\sound\ClickSound;
use function max;

class ArmorStand extends Living{

	public const TAG_POSE = "Pose";
	public const TAG_MAIN_HAND = "MainHand";
	public const TAG_ARMOR = "Armor";
	public const TAG_LOCKED = "Locked";
	public const TAG_SHOW_BASE_PLATE = "ShowBasePlate";
	public const POSE_COUNT = 13;

	protected int $poseIndex = 0;
	protected bool $locked = false;
	protected bool $showBasePlate = true;
	protected Item $heldItem;

	public static function getNetworkTypeId() : string{
		return EntityIds::ARMOR_STAND;
	}

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(1.975, 0.5);
	}

	public function getName() : string{
		return "Armor Stand";
	}

	protected function getInitialDragMultiplier() : float{
		return 0.02;
	}

	protected function getInitialGravity() : float{
		return 0.08;
	}

	protected int $punchTicks = 0;

	protected function addAttributes() : void{
		parent::addAttributes();
		$this->setMaxHealth(6);
		$this->setHealth(6.0);
	}

	protected function applyDamageKnockback(float $base) : void{
		// Armor stands are stationary objects and do not take knockback
	}

	public function knockBack(float $x, float $z, float $force = self::DEFAULT_KNOCKBACK_FORCE, ?float $verticalLimit = self::DEFAULT_KNOCKBACK_VERTICAL_LIMIT) : void{
		// Armor stands are stationary objects and do not take knockback
	}

	public function damageArmor(float $damage) : void{
		// Armor stands do not degrade equipped armor when attacked
	}

	protected function applyPostDamageEffects(EntityDamageEvent $source) : void{
		// Armor stands do not reduce absorption or take Thorns/durability damage
	}

	public function canBeRenamed() : bool{
		return !$this->isLocked();
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		$hasUpdate = parent::entityBaseTick($tickDiff);
		if($this->punchTicks > 0){
			$this->punchTicks = max(0, $this->punchTicks - $tickDiff);
		}
		return $hasUpdate;
	}

	public function getPickedItem() : ?Item{
		return VanillaItems::ARMOR_STAND();
	}

	public function getPose() : int{
		return $this->poseIndex;
	}

	public function setPose(int $pose) : void{
		$this->poseIndex = ($pose % self::POSE_COUNT + self::POSE_COUNT) % self::POSE_COUNT;
		if(isset($this->networkProperties)){
			$this->networkProperties->setInt(EntityMetadataProperties::ARMOR_STAND_POSE_INDEX, $this->poseIndex);
		}
	}

	public function isLocked() : bool{
		return $this->locked;
	}

	public function setLocked(bool $locked) : void{
		$this->locked = $locked;
	}

	public function hasBasePlate() : bool{
		return $this->showBasePlate;
	}

	public function setBasePlate(bool $show) : void{
		$this->showBasePlate = $show;
		if(isset($this->networkProperties)){
			$this->networkProperties->setGenericFlag(EntityMetadataFlags::SHOWBASE, $show);
		}
	}

	public function getMainHandItem() : Item{
		if(!isset($this->heldItem)){
			$this->heldItem = VanillaItems::AIR();
		}
		return clone $this->heldItem;
	}

	public function setMainHandItem(Item $item) : void{
		$this->heldItem = clone $item;
		$this->broadcastMainHandItem();
	}

	public function broadcastMainHandItem() : void{
		if(!isset($this->heldItem)){
			$this->heldItem = VanillaItems::AIR();
		}
		if(!isset($this->id)){
			return;
		}
		foreach($this->getViewers() as $player){
			$session = $player->getNetworkSession();
			$session->sendDataPacket(MobEquipmentPacket::create(
				$this->getId(),
				ItemStackWrapper::legacy($session->getTypeConverter()->coreItemStackToNet($this->heldItem)),
				0,
				0,
				ContainerIds::INVENTORY
			));
		}
	}

	public function onInteract(Player $player, Vector3 $clickPos) : bool{
		if($this->isLocked() || $player->isSpectator()){
			return false;
		}

		$playerItem = $player->getInventory()->getItemInHand();
		if($playerItem instanceof NameTag && $playerItem->hasCustomName()){
			return false;
		}

		if($player->isSneaking()){
			$oldPose = $this->poseIndex;
			$newPose = ($oldPose + 1) % self::POSE_COUNT;
			$ev = new ArmorStandPoseChangeEvent($this, $player, $oldPose, $newPose);
			$ev->call();
			if($ev->isCancelled()){
				return false;
			}
			$this->setPose($ev->getNewPose());
			if(isset($this->location)){
				$this->broadcastSound(new ClickSound());
			}
			return true;
		}

		$playerItem = $player->getInventory()->getItemInHand();
		$armorSlot = null;
		if($playerItem instanceof Armor){
			$armorSlot = $playerItem->getArmorSlot();
		}elseif($playerItem instanceof ItemBlock && (
			$playerItem->getBlock()->getTypeId() === BlockTypeIds::CARVED_PUMPKIN ||
			$playerItem->getBlock()->getTypeId() === BlockTypeIds::MOB_HEAD
		)){
			$armorSlot = ArmorInventory::SLOT_HEAD;
		}

		if($armorSlot !== null){
			$oldItem = $this->armorInventory->getItem($armorSlot);
			$newItem = $playerItem;
			$ev = new ArmorStandEquipEvent($this, $player, $armorSlot, $oldItem, $newItem);
			$ev->call();
			if($ev->isCancelled()){
				return false;
			}
			$this->armorInventory->setItem($armorSlot, $ev->getNewItem());
			$player->getInventory()->setItemInHand($oldItem);
			if(isset($this->location)){
				$this->broadcastSound(new ArmorEquipGenericSound());
			}
			return true;
		}

		if(!$playerItem->isNull()){
			$slot = ArmorStandEquipEvent::SLOT_MAIN_HAND;
			$oldItem = $this->getMainHandItem();
			$newItem = $playerItem;
			$ev = new ArmorStandEquipEvent($this, $player, $slot, $oldItem, $newItem);
			$ev->call();
			if($ev->isCancelled()){
				return false;
			}
			$this->setMainHandItem($ev->getNewItem());
			$player->getInventory()->setItemInHand($oldItem);
			if(isset($this->location)){
				$this->broadcastSound(new ArmorEquipGenericSound());
			}
			return true;
		}

		// Empty hand: Strip highest priority equipped item
		$slot = null;
		$oldItem = null;

		$headItem = $this->armorInventory->getItem(ArmorStandEquipEvent::SLOT_HEAD);
		if(!$headItem->isNull()){
			$slot = ArmorStandEquipEvent::SLOT_HEAD;
			$oldItem = $headItem;
		}else{
			$chestItem = $this->armorInventory->getItem(ArmorStandEquipEvent::SLOT_CHEST);
			if(!$chestItem->isNull()){
				$slot = ArmorStandEquipEvent::SLOT_CHEST;
				$oldItem = $chestItem;
			}else{
				$legsItem = $this->armorInventory->getItem(ArmorStandEquipEvent::SLOT_LEGS);
				if(!$legsItem->isNull()){
					$slot = ArmorStandEquipEvent::SLOT_LEGS;
					$oldItem = $legsItem;
				}else{
					$feetItem = $this->armorInventory->getItem(ArmorStandEquipEvent::SLOT_FEET);
					if(!$feetItem->isNull()){
						$slot = ArmorStandEquipEvent::SLOT_FEET;
						$oldItem = $feetItem;
					}else{
						$handItem = $this->getMainHandItem();
						if(!$handItem->isNull()){
							$slot = ArmorStandEquipEvent::SLOT_MAIN_HAND;
							$oldItem = $handItem;
						}
					}
				}
			}
		}

		if($slot === null || $oldItem === null){
			return false;
		}

		$ev = new ArmorStandEquipEvent($this, $player, $slot, $oldItem, VanillaItems::AIR());
		$ev->call();
		if($ev->isCancelled()){
			return false;
		}

		if($slot === ArmorStandEquipEvent::SLOT_MAIN_HAND){
			$this->setMainHandItem($ev->getNewItem());
		}else{
			$this->armorInventory->setItem($slot, $ev->getNewItem());
		}
		$player->getInventory()->setItemInHand($oldItem);
		if(isset($this->location)){
			$this->broadcastSound(new ArmorEquipGenericSound());
		}
		return true;
	}

	public function attack(EntityDamageEvent $source) : void{
		if($this->isLocked()){
			$source->cancel();
			return;
		}

		if($source->isCancelled()){
			return;
		}

		if($source->getCause() === EntityDamageEvent::CAUSE_PROJECTILE){
			$source->call();
			if($source->isCancelled()){
				return;
			}
			$this->setLastDamageCause($source);
			$this->kill();
			return;
		}

		if($source instanceof EntityDamageByEntityEvent && $source->getCause() === EntityDamageEvent::CAUSE_ENTITY_ATTACK){
			$damager = $source->getDamager();
			if($damager instanceof Player){
				if($damager->isCreative(true)){
					$source->call();
					if($source->isCancelled()){
						return;
					}
					$source->cancel();
					$this->setLastDamageCause($source);
					$this->flagForDespawn();
					if(isset($this->location)){
						$this->broadcastSound(new BlockBreakSound(VanillaBlocks::OAK_PLANKS()));
					}
					return;
				}

				// Survival / Adventure player attack
				$source->call();
				if($source->isCancelled()){
					return;
				}

				$this->setLastDamageCause($source);
				$heldItem = $damager->getInventory()->getItemInHand();
				$isToolOrWeapon = $heldItem instanceof Tool || $heldItem->getAttackPoints() > 1;

				if($isToolOrWeapon || $this->punchTicks > 0){
					$this->kill();
				}else{
					$this->punchTicks = 20;
					$this->broadcastAnimation(new HurtAnimation($this));
					if(isset($this->location)){
						$this->broadcastSound(new BlockPunchSound(VanillaBlocks::OAK_PLANKS()));
					}
				}
				return;
			}
		}

		if($source->getCause() === EntityDamageEvent::CAUSE_BLOCK_EXPLOSION || $source->getCause() === EntityDamageEvent::CAUSE_ENTITY_EXPLOSION){
			$source->call();
			if($source->isCancelled()){
				return;
			}
			$this->setLastDamageCause($source);
			$this->kill();
			return;
		}

		if($source->getCause() === EntityDamageEvent::CAUSE_VOID || $source->getCause() === EntityDamageEvent::CAUSE_SUICIDE){
			$source->call();
			if($source->isCancelled()){
				return;
			}
			$this->setLastDamageCause($source);
			$this->kill();
			return;
		}

		// Environmental damage (fire, lava, cactus, etc.)
		if($this->isFireProof() && (
				$source->getCause() === EntityDamageEvent::CAUSE_FIRE ||
				$source->getCause() === EntityDamageEvent::CAUSE_FIRE_TICK ||
				$source->getCause() === EntityDamageEvent::CAUSE_LAVA
			)
		){
			$source->cancel();
		}

		$source->call();
		if($source->isCancelled()){
			return;
		}

		$this->setLastDamageCause($source);
		$this->setHealth($this->getHealth() - $source->getFinalDamage());
	}

	/**
	 * @return Item[]
	 */
	public function getDrops() : array{
		$cause = $this->lastDamageCause;
		$dropsStandItem = true;

		if($cause !== null){
			$causeType = $cause->getCause();

			// Creative player punch: no drops
			if($cause instanceof EntityDamageByEntityEvent && $cause->getCause() === EntityDamageEvent::CAUSE_ENTITY_ATTACK){
				$damager = $cause->getDamager();
				if($damager instanceof Player && $damager->isCreative(true)){
					return [];
				}
			}

			// Void, suicide (/kill): no drops
			if($causeType === EntityDamageEvent::CAUSE_VOID || $causeType === EntityDamageEvent::CAUSE_SUICIDE){
				return [];
			}

			// Explosions, fire, and lava do not drop the armor stand entity item itself
			if(
				$causeType === EntityDamageEvent::CAUSE_BLOCK_EXPLOSION ||
				$causeType === EntityDamageEvent::CAUSE_ENTITY_EXPLOSION ||
				$causeType === EntityDamageEvent::CAUSE_FIRE ||
				$causeType === EntityDamageEvent::CAUSE_FIRE_TICK ||
				$causeType === EntityDamageEvent::CAUSE_LAVA
			){
				$dropsStandItem = false;
			}
		}

		$drops = [];
		if($dropsStandItem){
			$standItem = $this->getPickedItem();
			if($standItem !== null){
				$drops[] = $standItem;
			}
		}

		if(isset($this->armorInventory)){
			foreach($this->armorInventory->getContents() as $item){
				if(!$item->isNull()){
					$drops[] = clone $item;
				}
			}
		}
		$held = $this->getMainHandItem();
		if(!$held->isNull()){
			$drops[] = clone $held;
		}

		return $drops;
	}

	protected function startDeathAnimation() : void{
		if(isset($this->location)){
			$this->broadcastSound(new BlockBreakSound(VanillaBlocks::OAK_PLANKS()));
			$this->getWorld()->addParticle($this->location, new BlockBreakParticle(VanillaBlocks::OAK_PLANKS()));
		}
		$this->flagForDespawn();
	}

	public function readSaveData(CompoundTag $nbt) : void{
		if(method_exists(parent::class, 'readSaveData')){
			parent::readSaveData($nbt);
		}
		$rawPose = $nbt->getInt(self::TAG_POSE, 0);
		$this->poseIndex = ($rawPose % self::POSE_COUNT + self::POSE_COUNT) % self::POSE_COUNT;
		$this->locked = $nbt->getByte(self::TAG_LOCKED, 0) !== 0;
		$this->showBasePlate = $nbt->getByte(self::TAG_SHOW_BASE_PLATE, 1) !== 0;

		$mainHandTag = $nbt->getTag(self::TAG_MAIN_HAND);
		if($mainHandTag instanceof CompoundTag){
			$this->heldItem = Item::nbtDeserialize($mainHandTag);
		}else{
			$this->heldItem = VanillaItems::AIR();
		}

		$armorTag = $nbt->getListTag(self::TAG_ARMOR);
		if($armorTag !== null && isset($this->armorInventory)){
			$index = 0;
			foreach($armorTag as $itemTag){
				if($itemTag instanceof CompoundTag){
					$slot = $itemTag->getByte("Slot", $index);
					if($slot >= 0 && $slot < 4){
						$this->armorInventory->setItem($slot, Item::nbtDeserialize($itemTag));
					}
				}
				$index++;
			}
		}
	}

	protected function initEntity(CompoundTag $nbt) : void{
		parent::initEntity($nbt);
		$this->readSaveData($nbt);
	}

	public function writeSaveData(CompoundTag $nbt) : void{
		if(method_exists(parent::class, 'writeSaveData')){
			parent::writeSaveData($nbt);
		}
		$nbt->setInt(self::TAG_POSE, $this->poseIndex);
		$nbt->setByte(self::TAG_LOCKED, $this->locked ? 1 : 0);
		$nbt->setByte(self::TAG_SHOW_BASE_PLATE, $this->showBasePlate ? 1 : 0);
		if(isset($this->heldItem) && !$this->heldItem->isNull()){
			$nbt->setTag(self::TAG_MAIN_HAND, $this->heldItem->nbtSerialize());
		}else{
			$nbt->removeTag(self::TAG_MAIN_HAND);
		}
		if(isset($this->armorInventory)){
			$armorTag = [];
			for($slot = 0; $slot < 4; ++$slot){
				$item = $this->armorInventory->getItem($slot);
				if(!$item->isNull()){
					$armorTag[] = $item->nbtSerialize($slot);
				}
			}
			$nbt->setTag(self::TAG_ARMOR, new ListTag($armorTag, NBT::TAG_Compound));
		}
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT();
		$this->writeSaveData($nbt);
		return $nbt;
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);

		$properties->setGenericFlag(EntityMetadataFlags::SHOWBASE, $this->showBasePlate);
		$properties->setInt(EntityMetadataProperties::ARMOR_STAND_POSE_INDEX, $this->poseIndex);
	}

	protected function sendSpawnPacket(Player $player) : void{
		parent::sendSpawnPacket($player);

		if(!isset($this->heldItem)){
			$this->heldItem = VanillaItems::AIR();
		}

		$session = $player->getNetworkSession();
		$session->sendDataPacket(MobEquipmentPacket::create(
			$this->getId(),
			ItemStackWrapper::legacy($session->getTypeConverter()->coreItemStackToNet($this->heldItem)),
			0,
			0,
			ContainerIds::INVENTORY
		));
	}
}
