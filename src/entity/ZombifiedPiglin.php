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
use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\player\Player;
use function mt_rand;

class ZombifiedPiglin extends Zombie{

	public const TAG_ANGER = "Anger";
	public const ANGER_DURATION_TICKS = 500; // 25 seconds in BDS

	protected int $angerTicks = 0;

	public static function getNetworkTypeId() : string{
		return EntityIds::ZOMBIE_PIGMAN;
	}

	public function getName() : string{
		return "Zombified Piglin";
	}

	protected function initEntity(CompoundTag $nbt) : void{
		parent::initEntity($nbt);
		$this->angerTicks = $nbt->getInt(self::TAG_ANGER, 0);
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT();
		$nbt->setInt(self::TAG_ANGER, $this->angerTicks);
		return $nbt;
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);
		$properties->setGenericFlag(EntityMetadataFlags::ANGRY, $this->isAngry());
	}

	public function isAngry() : bool{
		return $this->angerTicks > 0;
	}

	public function setAngry(bool $angry = true) : void{
		$this->angerTicks = $angry ? self::ANGER_DURATION_TICKS : 0;
		$this->networkPropertiesDirty = true;
	}

	public function getAngerTicks() : int{
		return $this->angerTicks;
	}

	public function setAngerTicks(int $ticks) : void{
		$this->angerTicks = $ticks;
		$this->networkPropertiesDirty = true;
	}

	public function attack(EntityDamageEvent $source) : void{
		// Fire and lava immunity
		if(
			$source->getCause() === EntityDamageEvent::CAUSE_FIRE ||
			$source->getCause() === EntityDamageEvent::CAUSE_FIRE_TICK ||
			$source->getCause() === EntityDamageEvent::CAUSE_LAVA
		){
			$source->cancel();
			return;
		}

		parent::attack($source);

		// Group anger mechanic: broadcast anger to all Zombified Piglins within 32 blocks
		if(!$source->isCancelled() && $source instanceof EntityDamageByEntityEvent){
			$attacker = $source->getDamager();
			if($attacker instanceof Player){
				$this->setAngry(true);
				foreach($this->getWorld()->getNearbyEntities($this->boundingBox->expandedCopy(32, 16, 32), $this) as $nearby){
					if($nearby instanceof ZombifiedPiglin && $nearby->isAlive() && $nearby->getId() !== $this->getId()){
						$nearby->setAngry(true);
					}
				}
			}
		}
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		$hasUpdate = parent::entityBaseTick($tickDiff);

		if($this->angerTicks > 0){
			$this->angerTicks -= $tickDiff;
			$hasUpdate = true;
			if($this->angerTicks <= 0){
				$this->networkPropertiesDirty = true;
			}
		}

		return $hasUpdate;
	}

	public function getDrops() : array{
		$drops = [
			VanillaItems::ROTTEN_FLESH()->setCount(mt_rand(0, 2)),
			VanillaItems::GOLD_NUGGET()->setCount(mt_rand(0, 1))
		];

		// 2.5% chance for Gold Ingot
		if(mt_rand(0, 199) < 5){
			$drops[] = VanillaItems::GOLD_INGOT();
		}

		return $drops;
	}
}
