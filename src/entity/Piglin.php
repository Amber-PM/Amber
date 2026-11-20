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
use pocketmine\item\Armor;
use pocketmine\item\Item;
use pocketmine\item\ItemTypeIds;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\player\Player;
use pocketmine\world\particle\HappyVillagerParticle;
use function count;
use function mt_rand;

class Piglin extends Living{

	public const TAG_ADMIRING = "Admiring";
	public const TAG_ADMIRING_TICKS = "AdmiringTicks";
	public const BARTER_DURATION_TICKS = 120; // 6 seconds in BDS

	protected bool $admiring = false;
	protected int $admiringTicks = 0;

	public static function getNetworkTypeId() : string{
		return EntityIds::PIGLIN;
	}

	public function getName() : string{
		return "Piglin";
	}

	public function getDefaultMaxHealth() : int{
		return 16;
	}

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(1.9, 0.6);
	}

	protected function initEntity(CompoundTag $nbt) : void{
		$this->setMaxHealth(16);
		parent::initEntity($nbt);
		if($nbt->getTag("Health") === null){
			$this->setHealth(16.0);
		}

		$this->admiring = $nbt->getByte(self::TAG_ADMIRING, 0) !== 0;
		$this->admiringTicks = $nbt->getInt(self::TAG_ADMIRING_TICKS, 0);
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT();
		$nbt->setByte(self::TAG_ADMIRING, $this->admiring ? 1 : 0);
		$nbt->setInt(self::TAG_ADMIRING_TICKS, $this->admiringTicks);
		return $nbt;
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);
		$properties->setGenericFlag(EntityMetadataFlags::ADMIRING, $this->admiring);
	}

	public function isAdmiring() : bool{
		return $this->admiring;
	}

	public function startBartering() : void{
		$this->admiring = true;
		$this->admiringTicks = self::BARTER_DURATION_TICKS;
		$this->networkPropertiesDirty = true;
	}

	public function finishBartering() : void{
		$this->admiring = false;
		$this->admiringTicks = 0;
		$this->networkPropertiesDirty = true;

		// Drop barter loot
		$loot = $this->getRandomBarterLoot();
		$this->getWorld()->dropItem($this->location, $loot);
		$this->getWorld()->addParticle($this->location->add(0, 1.5, 0), new HappyVillagerParticle());
	}

	public function isPacifiedBy(Player $player) : bool{
		// Player wearing any piece of gold armor pacifies Piglin
		foreach($player->getArmorInventory()->getContents() as $armor){
			$id = $armor->getTypeId();
			if(
				$id === ItemTypeIds::GOLDEN_HELMET ||
				$id === ItemTypeIds::GOLDEN_CHESTPLATE ||
				$id === ItemTypeIds::GOLDEN_LEGGINGS ||
				$id === ItemTypeIds::GOLDEN_BOOTS
			){
				return true;
			}
		}
		return false;
	}

	public function interact(Player $player, Vector3 $clickPos) : bool{
		$item = $player->getInventory()->getItemInHand();

		// Barter with Gold Ingot
		if($item->getTypeId() === ItemTypeIds::GOLD_INGOT && !$this->admiring){
			$item->pop();
			$player->getInventory()->setItemInHand($item);
			$this->startBartering();
			return true;
		}

		return false;
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		$hasUpdate = parent::entityBaseTick($tickDiff);

		if(!$this->isAlive()){
			return $hasUpdate;
		}

		if($this->admiring){
			$this->admiringTicks -= $tickDiff;
			$hasUpdate = true;
			if($this->admiringTicks <= 0){
				$this->finishBartering();
			}
		}

		return $hasUpdate;
	}

	public function getRandomBarterLoot() : Item{
		$pool = [
			VanillaItems::ENDER_PEARL()->setCount(mt_rand(2, 4)),
			VanillaItems::FIRE_CHARGE()->setCount(mt_rand(1, 3)),
			VanillaBlocks::GRAVEL()->asItem()->setCount(mt_rand(8, 16)),
			VanillaItems::LEATHER()->setCount(mt_rand(2, 4)),
			VanillaItems::NETHER_BRICK()->setCount(mt_rand(4, 12)),
			VanillaBlocks::OBSIDIAN()->asItem()->setCount(1),
			VanillaBlocks::CRYING_OBSIDIAN()->asItem()->setCount(mt_rand(1, 3)),
			VanillaItems::NETHER_QUARTZ()->setCount(mt_rand(5, 12)),
			VanillaItems::IRON_NUGGET()->setCount(mt_rand(10, 36))
		];

		return clone $pool[mt_rand(0, count($pool) - 1)];
	}

	public function getDrops() : array{
		return [
			VanillaItems::RAW_PORKCHOP()->setCount(mt_rand(0, 1)),
			VanillaItems::GOLD_NUGGET()->setCount(mt_rand(0, 1))
		];
	}

	public function getXpDropAmount() : int{
		return 5;
	}
}
