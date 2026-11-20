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

use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityRegainHealthEvent;
use pocketmine\item\Item;
use pocketmine\item\ItemTypeIds;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\player\Player;
use pocketmine\world\particle\HappyVillagerParticle;
use pocketmine\world\particle\HeartParticle;
use function max;
use function mt_rand;

class Dolphin extends Living implements Ageable{

	public const TAG_MOISTURE = "Moisture";
	public const TAG_UNDERWATER_TICKS = "UnderwaterTicks";
	public const MAX_MOISTURE = 1200; // 1 minute on land
	public const MAX_UNDERWATER_AIR = 4800; // 4 minutes underwater

	protected int $moisture = self::MAX_MOISTURE;
	protected int $underwaterAir = self::MAX_UNDERWATER_AIR;
	protected ?int $targetEntityId = null;
	protected int $angerTicks = 0;

	public static function getNetworkTypeId() : string{
		return EntityIds::DOLPHIN;
	}

	public function getName() : string{
		return "Dolphin";
	}

	public function getDefaultMaxHealth() : int{
		return 10;
	}

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(0.6, 0.9);
	}

	public function isBaby() : bool{
		return false;
	}

	protected function initEntity(CompoundTag $nbt) : void{
		$this->setMaxHealth(10);
		parent::initEntity($nbt);
		if($nbt->getTag("Health") === null){
			$this->setHealth(10.0);
		}

		$this->moisture = $nbt->getInt(self::TAG_MOISTURE, self::MAX_MOISTURE);
		$this->underwaterAir = $nbt->getInt(self::TAG_UNDERWATER_TICKS, self::MAX_UNDERWATER_AIR);
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT();
		$nbt->setInt(self::TAG_MOISTURE, $this->moisture);
		$nbt->setInt(self::TAG_UNDERWATER_TICKS, $this->underwaterAir);
		return $nbt;
	}

	public function getMoisture() : int{
		return $this->moisture;
	}

	public function setMoisture(int $moisture) : void{
		$this->moisture = $moisture;
	}

	public function getUnderwaterAir() : int{
		return $this->underwaterAir;
	}

	public function setUnderwaterAir(int $air) : void{
		$this->underwaterAir = $air;
	}

	public function isAngry() : bool{
		return $this->angerTicks > 0;
	}

	public function setAngry(int $ticks = 300) : void{
		$wasAngry = $this->isAngry();
		$this->angerTicks = $ticks;
		if(!$wasAngry){
			$this->networkPropertiesDirty = true;
		}
	}

	public function getTargetEntityId() : ?int{
		return $this->targetEntityId;
	}

	public function setTargetEntityId(?int $id) : void{
		$this->targetEntityId = $id;
	}

	public function isFeedingItem(Item $item) : bool{
		return $item->getTypeId() === ItemTypeIds::RAW_FISH || $item->getTypeId() === ItemTypeIds::RAW_SALMON;
	}

	public function interact(Player $player, Vector3 $clickPos) : bool{
		$item = $player->getInventory()->getItemInHand();

		if($this->isFeedingItem($item)){
			if($this->getHealth() < $this->getMaxHealth()){
				$this->heal(new EntityRegainHealthEvent($this, 2.0, EntityRegainHealthEvent::CAUSE_EATING));
				$item->pop();
				$player->getInventory()->setItemInHand($item);
				$this->getWorld()->addParticle($this->location->add(0, 0.5, 0), new HeartParticle());
				return true;
			}

			// Full health -> happy state
			$item->pop();
			$player->getInventory()->setItemInHand($item);
			$this->getWorld()->addParticle($this->location->add(0, 0.5, 0), new HappyVillagerParticle());
			return true;
		}

		return false;
	}

	public function attack(EntityDamageEvent $source) : void{
		parent::attack($source);

		if($source->isCancelled()){
			return;
		}

		if($source instanceof EntityDamageByEntityEvent){
			$damager = $source->getDamager();
			if($damager !== null){
				$this->setAngry(300);
				$this->setTargetEntityId($damager->getId());

				// Alert nearby dolphins in 16-block radius
				foreach($this->getWorld()->getNearbyEntities($this->boundingBox->expandedCopy(16, 8, 16), $this) as $nearby){
					if($nearby instanceof Dolphin && $nearby->getId() !== $this->getId()){
						$nearby->setAngry(300);
						$nearby->setTargetEntityId($damager->getId());
					}
				}
			}
		}
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		$hasUpdate = parent::entityBaseTick($tickDiff);

		if($this->angerTicks > 0){
			$this->angerTicks = max(0, $this->angerTicks - $tickDiff);
			if($this->angerTicks === 0){
				$this->targetEntityId = null;
				$this->networkPropertiesDirty = true;
			}
			$hasUpdate = true;
		}

		// Moisture on land vs in water
		if($this->isUnderwater()){
			$this->moisture = self::MAX_MOISTURE;
			$this->underwaterAir -= $tickDiff;
			if($this->underwaterAir <= 0){
				$this->attack(new EntityDamageEvent($this, EntityDamageEvent::CAUSE_DROWNING, 2.0));
				$this->underwaterAir = 20;
			}
		}else{
			$this->underwaterAir = self::MAX_UNDERWATER_AIR;
			$this->moisture -= $tickDiff;
			if($this->moisture <= 0){
				$this->attack(new EntityDamageEvent($this, EntityDamageEvent::CAUSE_SUFFOCATION, 2.0));
				$this->moisture = 20;
			}
		}

		// Dolphin's Grace: Grant swimming speed to players swimming within 9 blocks
		foreach($this->getWorld()->getNearbyEntities($this->boundingBox->expandedCopy(9, 9, 9), $this) as $nearby){
			if($nearby instanceof Player && $nearby->isUnderwater() && $nearby->isAlive()){
				$nearby->getEffects()->add(new EffectInstance(VanillaEffects::SPEED(), 100, 1, false));
			}
		}

		return $hasUpdate;
	}

	public function getDrops() : array{
		$drops = [];
		if(mt_rand(0, 1) === 1){
			$drops[] = VanillaItems::RAW_FISH();
		}
		return $drops;
	}
}
