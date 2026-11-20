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

use pocketmine\item\VanillaItems;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use function mt_rand;

class Salmon extends WaterAnimal{

	public const SIZE_SMALL = 0;
	public const SIZE_MEDIUM = 1;
	public const SIZE_LARGE = 2;

	public const TAG_VARIANT = "Variant";

	protected int $variant = self::SIZE_MEDIUM;

	public static function getNetworkTypeId() : string{
		return EntityIds::SALMON;
	}

	public function getName() : string{
		return "Salmon";
	}

	public function getDefaultMaxHealth() : int{
		return 3;
	}

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(0.4, 0.7);
	}

	protected function initEntity(CompoundTag $nbt) : void{
		$this->setMaxHealth(3);
		parent::initEntity($nbt);
		if($nbt->getTag("Health") === null){
			$this->setHealth(3.0);
		}

		$this->variant = $nbt->getInt(self::TAG_VARIANT, mt_rand(0, 2));
		if($this->variant < 0 || $this->variant > 2){
			$this->variant = self::SIZE_MEDIUM;
		}

		$this->updateScale();
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT();
		$nbt->setInt(self::TAG_VARIANT, $this->variant);
		return $nbt;
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);
		$properties->setInt(EntityMetadataProperties::VARIANT, $this->variant);
	}

	public function getVariant() : int{
		return $this->variant;
	}

	public function setVariant(int $variant) : void{
		if($variant < 0 || $variant > 2){
			throw new \InvalidArgumentException("Invalid salmon size $variant");
		}
		$this->variant = $variant;
		$this->updateScale();
		$this->networkPropertiesDirty = true;
	}

	private function updateScale() : void{
		$scale = match($this->variant){
			self::SIZE_SMALL => 0.75,
			self::SIZE_LARGE => 1.25,
			default => 1.0
		};
		$this->setScale($scale);
	}

	public function getDrops() : array{
		return [VanillaItems::RAW_SALMON()];
	}
}
