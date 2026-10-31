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

use pocketmine\block\utils\MobHeadType;
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use function mt_rand;

class WitherSkeleton extends Living{

	public static function getNetworkTypeId() : string{
		return EntityIds::WITHER_SKELETON;
	}

	public function getName() : string{
		return "Wither Skeleton";
	}

	public function getDefaultMaxHealth() : int{
		return 20;
	}

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(2.4, 0.7);
	}

	protected function initEntity(CompoundTag $nbt) : void{
		$this->setMaxHealth(20);
		parent::initEntity($nbt);
		if($nbt->getTag("Health") === null){
			$this->setHealth(20.0);
		}
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
	}

	public function attackTarget(Living $target) : void{
		$ev = new EntityDamageByEntityEvent($this, $target, EntityDamageEvent::CAUSE_ENTITY_ATTACK, 4.0);
		$target->attack($ev);

		if(!$ev->isCancelled()){
			// Wither I effect for 10 seconds (200 ticks)
			$target->getEffects()->add(new EffectInstance(VanillaEffects::WITHER(), 200, 0));
		}
	}

	public function getDrops() : array{
		$drops = [
			VanillaItems::COAL()->setCount(mt_rand(0, 1)),
			VanillaItems::BONE()->setCount(mt_rand(0, 2))
		];

		// 2.5% rare drop: Wither Skeleton Skull
		if(mt_rand(0, 199) < 5){
			$drops[] = VanillaBlocks::MOB_HEAD()->setMobHeadType(MobHeadType::WITHER_SKELETON)->asItem();
		}

		return $drops;
	}

	public function getXpDropAmount() : int{
		return 5;
	}
}
