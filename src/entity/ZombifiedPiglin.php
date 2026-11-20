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
use pocketmine\item\enchantment\VanillaEnchantments;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\player\Player;
use function abs;
use function max;
use function min;
use function mt_rand;

class ZombifiedPiglin extends Zombie{

	public const TAG_ANGER = "Anger";
	public const ANGER_DURATION_TICKS = 500; // 25 seconds in BDS

	protected int $angerTicks = 0;
	private ?int $angerStartedAt = null;
	private int $meleeCooldown = 0;

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
		$this->setAngerTicks($angry ? self::ANGER_DURATION_TICKS : 0);
	}

	public function getAngerTicks() : int{
		return $this->angerTicks;
	}

	public function setAngerTicks(int $ticks) : void{
		$this->angerTicks = max(0, $ticks);
		$this->angerStartedAt = $this->isAngry() ? $this->server->getTick() : null;
		if(!$this->isAngry()){
			$this->setTargetEntity(null);
		}
		$this->networkPropertiesDirty = true;
		$this->scheduleUpdate();
	}

	public function isFireProof() : bool{
		return true;
	}

	public function attack(EntityDamageEvent $source) : void{
		parent::attack($source);

		if($this->isAlive() && !$source->isCancelled() && $source instanceof EntityDamageByEntityEvent){
			$attacker = $source->getDamager();
			if($attacker instanceof Player){
				$target = $this->canRetaliateAgainst($attacker) ? $attacker : null;
				$this->setTargetEntity($target);
				$this->setAngry();
				foreach($this->getWorld()->getNearbyEntities($this->boundingBox->expandedCopy(20, 16, 20), $this) as $nearby){
					if($nearby instanceof ZombifiedPiglin && $nearby->isAlive() &&
						abs($this->location->y - $nearby->location->y) <= 16 &&
						($this->location->x - $nearby->location->x) ** 2 + ($this->location->z - $nearby->location->z) ** 2 <= 400){
						$nearby->setTargetEntity($target);
						$nearby->setAngry();
					}
				}
			}
		}
	}

	private function canRetaliateAgainst(Player $target) : bool{
		return !$target->isClosed() && $target->isAlive() && !$target->isCreative() && !$target->isSpectator() && $target->getWorld() === $this->getWorld();
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		$hasUpdate = parent::entityBaseTick($tickDiff);
		if(!$this->isAlive() || !$this->isAngry()){
			return $hasUpdate;
		}

		$elapsed = $this->angerStartedAt !== null ? min($tickDiff, max(0, $this->lastUpdate - $this->angerStartedAt)) : $tickDiff;
		$this->angerStartedAt = null;
		$this->angerTicks = max(0, $this->angerTicks - $elapsed);
		if(!$this->isAngry()){
			$this->setAngry(false);
			return true;
		}

		$this->meleeCooldown = max(0, $this->meleeCooldown - $elapsed);
		$targetId = $this->getTargetEntityId();
		if($targetId !== null){
			$target = $this->getWorld()->getEntity($targetId);
			if(!$target instanceof Player || !$this->canRetaliateAgainst($target)){
				$this->setTargetEntity(null);
			}elseif($this->meleeCooldown === 0 && $this->location->distanceSquared($target->getLocation()) <= 2.25){
				$this->meleeCooldown = 20;
				$target->attack(new EntityDamageByEntityEvent($this, $target, EntityDamageEvent::CAUSE_ENTITY_ATTACK, 5.0));
			}
		}

		return true;
	}

	public function getDrops() : array{
		$cause = $this->getLastDamageCause();
		$killer = $cause instanceof EntityDamageByEntityEvent ? $cause->getDamager() : null;
		$playerKill = $killer instanceof Player || ($killer instanceof TameableAnimal && $killer->isTamed());
		$looting = $killer instanceof Player ? $killer->getInventory()->getItemInHand()->getEnchantmentLevel(VanillaEnchantments::LOOTING()) : 0;
		$drops = [
			VanillaItems::ROTTEN_FLESH()->setCount(mt_rand(0, 1) + ($looting > 0 ? mt_rand(0, $looting) : 0)),
			VanillaItems::GOLD_NUGGET()->setCount(mt_rand(0, 1))
		];

		// 2.5% chance for Gold Ingot
		if($playerKill && mt_rand(0, 199) < 5){
			$drops[] = VanillaItems::GOLD_INGOT();
		}

		return $drops;
	}
}
