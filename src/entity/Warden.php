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
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\world\sound\ExplodeSound;
use function max;
use function min;

class Warden extends Living{

	public const TAG_ANGER = "Anger";
	public const ANGRY_THRESHOLD = 80;
	public const MAX_ANGER = 150;

	protected int $anger = 0;
	protected bool $emerging = false;
	protected bool $digging = false;
	protected bool $sniffing = false;
	protected bool $sonicBooming = false;

	public static function getNetworkTypeId() : string{
		return EntityIds::WARDEN;
	}

	public function getName() : string{
		return "Warden";
	}

	public function getDefaultMaxHealth() : int{
		return 500;
	}

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(2.9, 0.9);
	}

	public function knockBack(float $x, float $z, float $force = 0.4, ?float $verticalLimit = null) : void{
		// 100% knockback resistance
	}

	protected function initEntity(CompoundTag $nbt) : void{
		$this->setMaxHealth(500);
		parent::initEntity($nbt);

		$angerTag = $nbt->getTag(self::TAG_ANGER);
		if($angerTag instanceof IntTag){
			$this->anger = $angerTag->getValue();
		}

		if($nbt->getTag("Health") === null){
			$this->setHealth(500.0);
		}
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT();
		$nbt->setInt(self::TAG_ANGER, $this->anger);
		return $nbt;
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);

		$properties->setGenericFlag(EntityMetadataFlags::EMERGING, $this->emerging);
		$properties->setGenericFlag(EntityMetadataFlags::DIGGING, $this->digging);
		$properties->setGenericFlag(EntityMetadataFlags::SNIFFING, $this->sniffing);
		$properties->setGenericFlag(EntityMetadataFlags::SCENTING, $this->sniffing);
		$properties->setGenericFlag(EntityMetadataFlags::SONIC_BOOM, $this->sonicBooming);
	}

	public function getAnger() : int{
		return $this->anger;
	}

	public function setAnger(int $anger) : void{
		$this->anger = max(0, min(self::MAX_ANGER, $anger));
	}

	public function increaseAnger(int $amount = 20) : void{
		$this->setAnger($this->anger + $amount);
	}

	public function isAngry() : bool{
		return $this->anger >= self::ANGRY_THRESHOLD;
	}

	public function isEmerging() : bool{
		return $this->emerging;
	}

	public function setEmerging(bool $emerging = true) : void{
		$this->emerging = $emerging;
		$this->networkPropertiesDirty = true;
	}

	public function isDigging() : bool{
		return $this->digging;
	}

	public function setDigging(bool $digging = true) : void{
		$this->digging = $digging;
		$this->networkPropertiesDirty = true;
	}

	public function isSniffing() : bool{
		return $this->sniffing;
	}

	public function setSniffing(bool $sniffing = true) : void{
		$this->sniffing = $sniffing;
		$this->networkPropertiesDirty = true;
	}

	public function isSonicBooming() : bool{
		return $this->sonicBooming;
	}

	public function setSonicBooming(bool $booming = true) : void{
		$this->sonicBooming = $booming;
		$this->networkPropertiesDirty = true;
	}

	public function attack(EntityDamageEvent $source) : void{
		// Fire, lava, and suffocation immunity
		if(
			$source->getCause() === EntityDamageEvent::CAUSE_FIRE ||
			$source->getCause() === EntityDamageEvent::CAUSE_FIRE_TICK ||
			$source->getCause() === EntityDamageEvent::CAUSE_LAVA ||
			$source->getCause() === EntityDamageEvent::CAUSE_DROWNING ||
			$source->getCause() === EntityDamageEvent::CAUSE_SUFFOCATION
		){
			$source->cancel();
			return;
		}

		parent::attack($source);

		if(!$source->isCancelled()){
			// Sustaining damage boosts anger
			$this->increaseAnger(20);
		}
	}

	public function sonicBoom(Living $victim) : bool{
		if($this->location->distance($victim->getLocation()) > 30.0){
			return false;
		}

		$this->setSonicBooming(true);
		$ev = new EntityDamageEvent($victim, EntityDamageEvent::CAUSE_MAGIC, 10.0);
		$victim->attack($ev);

		$direction = $victim->getLocation()->subtractVector($this->location)->normalize();
		$victim->knockBack($direction->x, $direction->z, 2.5, 0.5);

		$this->broadcastSound(new ExplodeSound());
		return true;
	}

	public function getDrops() : array{
		return [
			VanillaBlocks::SCULK()->asItem()
		];
	}

	public function getXpDropAmount() : int{
		return 5;
	}
}
