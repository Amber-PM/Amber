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

namespace pocketmine\item;

use pocketmine\block\Block;
use pocketmine\entity\Location;
use pocketmine\entity\object\FireworkRocket as FireworkRocketEntity;
use pocketmine\entity\projectile\Arrow as ArrowEntity;
use pocketmine\entity\projectile\Projectile;
use pocketmine\event\entity\EntityShootBowEvent;
use pocketmine\event\entity\ProjectileLaunchEvent;
use pocketmine\item\enchantment\VanillaEnchantments;
use pocketmine\item\FireworkRocket as FireworkRocketItem;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\player\Player;
use pocketmine\world\sound\CrossbowLoadEndSound;
use pocketmine\world\sound\CrossbowLoadStartSound;
use pocketmine\world\sound\CrossbowShootSound;
use function cos;
use function deg2rad;
use function max;
use function sin;

class Crossbow extends Tool implements Releasable{

	public const TAG_CHARGED_ITEM = "chargedItem";

	public function getFuelTime() : int{
		return 300;
	}

	public function getMaxDurability() : int{
		return 465;
	}

	public function isCharged() : bool{
		return $this->getNamedTag()->getTag(self::TAG_CHARGED_ITEM) instanceof CompoundTag;
	}

	public function getChargedItem() : ?Item{
		$tag = $this->getNamedTag()->getCompoundTag(self::TAG_CHARGED_ITEM);
		if($tag !== null){
			return Item::nbtDeserialize($tag);
		}
		return null;
	}

	public function setChargedItem(?Item $item) : self{
		if($item !== null && !$item->isNull()){
			$this->getNamedTag()->setTag(self::TAG_CHARGED_ITEM, $item->nbtSerialize());
		}else{
			$this->getNamedTag()->removeTag(self::TAG_CHARGED_ITEM);
		}
		return $this;
	}

	public function getChargeDuration() : int{
		$level = $this->getEnchantmentLevel(VanillaEnchantments::QUICK_CHARGE());
		return max(5, 25 - ($level * 5));
	}

	public function isValidAmmo(Item $item) : bool{
		$id = $item->getTypeId();
		return $id === ItemTypeIds::ARROW || $id === ItemTypeIds::FIREWORK_ROCKET;
	}

	public function findAmmoItem(Player $player) : ?Item{
		$offhand = $player->getOffHandInventory()->getItem(0);
		if($this->isValidAmmo($offhand)){
			return $offhand;
		}

		foreach($player->getInventory()->getContents() as $item){
			if($this->isValidAmmo($item)){
				return $item;
			}
		}

		return null;
	}

	public function canStartUsingItem(Player $player) : bool{
		if($this->isCharged()){
			return false;
		}
		return !$player->hasFiniteResources() || $this->findAmmoItem($player) !== null;
	}

	/**
	 * @param Item[] &$returnedItems
	 */
	public function onClickAir(Player $player, Vector3 $directionVector, array &$returnedItems) : ItemUseResult{
		if($this->isCharged()){
			return $this->fire($player, $directionVector, $returnedItems);
		}

		$quickCharge = $this->getEnchantmentLevel(VanillaEnchantments::QUICK_CHARGE()) > 0;
		$player->getWorld()->addSound($player->getLocation(), new CrossbowLoadStartSound($quickCharge));
		return ItemUseResult::SUCCESS;
	}

	/**
	 * @param Item[] &$returnedItems
	 */
	public function onInteractBlock(Player $player, Block $blockReplace, Block $blockClicked, int $face, Vector3 $clickVector, array &$returnedItems) : ItemUseResult{
		if($this->isCharged()){
			return $this->fire($player, $player->getDirectionVector(), $returnedItems);
		}
		return ItemUseResult::NONE;
	}

	/**
	 * @param Item[] &$returnedItems
	 */
	public function onReleaseUsing(Player $player, array &$returnedItems) : ItemUseResult{
		if($this->isCharged()){
			return ItemUseResult::FAIL;
		}

		if($player->getItemUseDuration() < $this->getChargeDuration()){
			return ItemUseResult::FAIL;
		}

		$ammoInventory = null;
		$ammoSlot = null;
		$ammoItem = null;

		$offhand = $player->getOffHandInventory()->getItem(0);
		if($this->isValidAmmo($offhand)){
			$ammoInventory = $player->getOffHandInventory();
			$ammoSlot = 0;
			$ammoItem = clone $offhand;
		}else{
			foreach($player->getInventory()->getContents() as $slot => $item){
				if($this->isValidAmmo($item)){
					$ammoInventory = $player->getInventory();
					$ammoSlot = $slot;
					$ammoItem = clone $item;
					break;
				}
			}
		}

		if($ammoItem === null){
			if($player->hasFiniteResources()){
				return ItemUseResult::FAIL;
			}
			$ammoItem = VanillaItems::ARROW();
		}

		if($player->hasFiniteResources() && $ammoInventory !== null && $ammoSlot !== null){
			$sourceItem = $ammoInventory->getItem($ammoSlot);
			$sourceItem->pop();
			$ammoInventory->setItem($ammoSlot, $sourceItem);
		}

		$loadedAmmo = (clone $ammoItem)->setCount(1);
		$this->setChargedItem($loadedAmmo);

		$quickCharge = $this->getEnchantmentLevel(VanillaEnchantments::QUICK_CHARGE()) > 0;
		$player->getWorld()->addSound($player->getLocation(), new CrossbowLoadEndSound($quickCharge));

		return ItemUseResult::SUCCESS;
	}

	/**
	 * @param Item[] &$returnedItems
	 */
	public function fire(Player $player, Vector3 $directionVector, array &$returnedItems) : ItemUseResult{
		$ammo = $this->getChargedItem();
		if($ammo === null){
			$this->setChargedItem(null);
			return ItemUseResult::FAIL;
		}

		$multishot = $this->hasEnchantment(VanillaEnchantments::MULTISHOT());
		$angles = $multishot ? [-10.0, 0.0, 10.0] : [0.0];
		$durabilityDamage = $multishot ? 3 : 1;

		$location = $player->getLocation();
		$world = $player->getWorld();

		$launched = false;
		foreach($angles as $angleOffset){
			if($this->shootProjectile($player, $ammo, $angleOffset, $angleOffset !== 0.0)){
				$launched = true;
			}
		}

		if(!$launched){
			return ItemUseResult::FAIL;
		}

		$world->addSound($location, new CrossbowShootSound());

		$this->setChargedItem(null);
		if($player->hasFiniteResources()){
			$this->applyDamage($durabilityDamage);
		}

		return ItemUseResult::SUCCESS;
	}

	protected function createArrow(Location $location, Player $player) : ArrowEntity{
		return new ArrowEntity($location, $player, true);
	}

	protected function shootProjectile(Player $player, Item $ammo, float $yawOffset, bool $isExtra = false) : bool{
		$location = $player->getLocation();
		$world = $player->getWorld();
		$yaw = $location->yaw + $yawOffset;
		$pitch = $location->pitch;

		$y = -sin(deg2rad($pitch));
		$xz = cos(deg2rad($pitch));
		$x = -$xz * sin(deg2rad($yaw));
		$z = $xz * cos(deg2rad($yaw));
		$dir = (new Vector3($x, $y, $z))->normalize();

		$spawnLocation = Location::fromObject(
			$player->getEyePos(),
			$world,
			($yaw > 180 ? 360 : 0) - $yaw,
			-$pitch
		);

		if($ammo->getTypeId() === ItemTypeIds::FIREWORK_ROCKET){
			$explosions = [];
			if($ammo instanceof FireworkRocketItem){
				$explosions = $ammo->getExplosions();
			}
			$rocket = new FireworkRocketEntity($spawnLocation, 60, $explosions);
			$rocket->setOwningEntity($player);
			$rocket->setMotion($dir->multiply(1.6));
			$rocket->setShotFromCrossbow(true);
			$rocket->spawnToAll();
			return true;
		}

		$arrow = $this->createArrow($spawnLocation, $player);
		$arrow->setMotion($dir);

		if($isExtra || !$player->hasFiniteResources()){
			$arrow->setPickupMode(ArrowEntity::PICKUP_CREATIVE);
		}

		if(($pierceLevel = $this->getEnchantmentLevel(VanillaEnchantments::PIERCING())) > 0){
			$arrow->setPierceLevel($pierceLevel);
		}

		$ev = new EntityShootBowEvent($player, $this, $arrow, 3.15);
		if($player->isSpectator()){
			$ev->cancel();
		}
		$ev->call();

		if($ev->isCancelled()){
			$ev->getProjectile()->flagForDespawn();
			return false;
		}

		$projectile = $ev->getProjectile();
		$projectile->setMotion($projectile->getMotion()->multiply($ev->getForce()));

		if($projectile instanceof Projectile){
			$projectileEv = new ProjectileLaunchEvent($projectile);
			$projectileEv->call();
			if($projectileEv->isCancelled()){
				$projectile->flagForDespawn();
				return false;
			}
		}

		$projectile->spawnToAll();
		return true;
	}
}
