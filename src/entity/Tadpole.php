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
use pocketmine\item\Item;
use pocketmine\item\ItemTypeIds;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\player\Player;
use pocketmine\world\particle\HappyVillagerParticle;
use function min;
use function stripos;

class Tadpole extends Living implements Ageable{

	public const TAG_AGE = "Age";

	protected int $age = -24000;
	protected int $airTicks = 200;

	public static function getNetworkTypeId() : string{
		return EntityIds::TADPOLE;
	}

	public function getName() : string{
		return "Tadpole";
	}

	public function getDefaultMaxHealth() : int{
		return 6;
	}

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(0.6, 0.8);
	}

	public function isBaby() : bool{
		return true;
	}

	protected function initEntity(CompoundTag $nbt) : void{
		$this->setMaxHealth(6);
		parent::initEntity($nbt);
		if($nbt->getTag("Health") === null){
			$this->setHealth(6.0);
		}

		$this->age = $nbt->getInt(self::TAG_AGE, -24000);
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT();
		$nbt->setInt(self::TAG_AGE, $this->age);
		return $nbt;
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);
		$properties->setGenericFlag(EntityMetadataFlags::BABY, true);
	}

	public function getAge() : int{
		return $this->age;
	}

	public function setAge(int $age) : void{
		$this->age = $age;
		if($this->age >= 0){
			$this->growUp();
		}
	}

	public function canBreatheInWater() : bool{
		return true;
	}

	public function interact(Player $player, Vector3 $clickPos) : bool{
		$item = $player->getInventory()->getItemInHand();

		if($item->getTypeId() === ItemTypeIds::SLIMEBALL){
			$this->age = (int) min(0, $this->age + 2400);
			$item->pop();
			$player->getInventory()->setItemInHand($item);
			$this->getWorld()->addParticle($this->location->add(0, 0.3, 0), new HappyVillagerParticle());
			if($this->age >= 0){
				$this->growUp();
			}
			return true;
		}

		return false;
	}

	public function growUp() : void{
		$frog = new Frog(Location::fromObject($this->location, $this->getWorld()));

		// Determine variant from biome
		$biome = $this->getWorld()->getBiome((int) $this->location->x, (int) $this->location->y, (int) $this->location->z);
		$biomeName = $biome !== null ? $biome->getName() : "";

		$isCold = stripos($biomeName, "frozen") !== false ||
			stripos($biomeName, "ice") !== false ||
			stripos($biomeName, "snow") !== false ||
			stripos($biomeName, "cold") !== false ||
			stripos($biomeName, "taiga") !== false;

		$isWarm = stripos($biomeName, "desert") !== false ||
			stripos($biomeName, "badlands") !== false ||
			stripos($biomeName, "jungle") !== false ||
			stripos($biomeName, "savanna") !== false ||
			stripos($biomeName, "swamp") !== false ||
			stripos($biomeName, "mangrove") !== false ||
			stripos($biomeName, "nether") !== false;

		if($isCold){
			$frog->setVariant(Frog::VARIANT_COLD);
		}elseif($isWarm){
			$frog->setVariant(Frog::VARIANT_WARM);
		}else{
			$frog->setVariant(Frog::VARIANT_TEMPERATE);
		}

		$frog->spawnToAll();
		$this->flagForDespawn();
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		$hasUpdate = parent::entityBaseTick($tickDiff);

		$this->age += $tickDiff;
		if($this->age >= 0){
			$this->growUp();
			return true;
		}

		// Suffocate if out of water
		if(!$this->isUnderwater()){
			$this->airTicks -= $tickDiff;
			if($this->airTicks <= 0){
				$this->attack(new EntityDamageEvent($this, EntityDamageEvent::CAUSE_SUFFOCATION, 2.0));
				$this->airTicks = 20;
			}
		}else{
			$this->airTicks = 200;
		}

		return $hasUpdate;
	}
}
