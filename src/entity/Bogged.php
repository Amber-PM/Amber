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

use pocketmine\block\VanillaBlocks;
use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\ItemTypeIds;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\player\Player;
use pocketmine\world\sound\PopSound;
use function mt_rand;

class Bogged extends Living{

	public const TAG_SHEARED = "Sheared";

	protected bool $sheared = false;

	public static function getNetworkTypeId() : string{
		return EntityIds::BOGGED;
	}

	public function getName() : string{
		return "Bogged";
	}

	public function getDefaultMaxHealth() : int{
		return 16;
	}

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(1.99, 0.6);
	}

	protected function initEntity(CompoundTag $nbt) : void{
		$this->setMaxHealth(16);
		parent::initEntity($nbt);

		$this->sheared = $nbt->getByte(self::TAG_SHEARED, 0) !== 0;

		if($nbt->getTag("Health") === null){
			$this->setHealth(16.0);
		}
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT();
		$nbt->setByte(self::TAG_SHEARED, $this->sheared ? 1 : 0);
		return $nbt;
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);
		$properties->setGenericFlag(EntityMetadataFlags::SHEARED, $this->sheared);
	}

	public function isSheared() : bool{
		return $this->sheared;
	}

	public function setSheared(bool $sheared = true) : void{
		$this->sheared = $sheared;
		$this->networkPropertiesDirty = true;
	}

	public function interact(Player $player, Vector3 $clickPos) : bool{
		$item = $player->getInventory()->getItemInHand();

		// Shearing interaction drops 2 mushrooms
		if($item->getTypeId() === ItemTypeIds::SHEARS && !$this->sheared){
			$this->setSheared(true);
			$this->getWorld()->dropItem($this->location, VanillaBlocks::BROWN_MUSHROOM()->asItem());
			$this->getWorld()->dropItem($this->location, VanillaBlocks::RED_MUSHROOM()->asItem());
			$this->broadcastSound(new PopSound());
			return true;
		}

		return false;
	}

	public function attackEntity(Entity $target) : bool{
		if(!$target instanceof Living || !$target->isAlive()){
			return false;
		}

		$ev = new EntityDamageByEntityEvent($this, $target, EntityDamageEvent::CAUSE_ENTITY_ATTACK, 3.0);
		$target->attack($ev);

		if(!$ev->isCancelled()){
			// Inflicts Poison I for 4 seconds (80 ticks)
			$target->getEffects()->add(new EffectInstance(VanillaEffects::POISON(), 80, 0));
			return true;
		}

		return false;
	}

	public function getDrops() : array{
		return [
			VanillaItems::BONE()->setCount(mt_rand(0, 2)),
			VanillaItems::ARROW()->setCount(mt_rand(0, 2))
		];
	}

	public function getXpDropAmount() : int{
		return 5;
	}
}
