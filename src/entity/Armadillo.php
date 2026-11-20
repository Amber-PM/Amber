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
use pocketmine\item\Durable;
use pocketmine\item\StringToItemParser;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\player\Player;
use pocketmine\world\sound\PopSound;
use function mt_rand;

class Armadillo extends Living{

	public const TAG_ROLLED_UP = "RolledUp";

	private const UNROLL_TICKS = 100;
	private const BRUSH_COOLDOWN_TICKS = 10;
	private const BRUSH_DURABILITY_COST = 16;

	protected bool $rolledUp = false;
	private ?int $unrollAtTick = null;

	public static function getNetworkTypeId() : string{
		return EntityIds::ARMADILLO;
	}

	public function getName() : string{
		return "Armadillo";
	}

	public function getDefaultMaxHealth() : int{
		return 12;
	}

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(0.8, 0.8);
	}

	protected function initEntity(CompoundTag $nbt) : void{
		$this->setMaxHealth(12);
		parent::initEntity($nbt);

		$this->rolledUp = $nbt->getByte(self::TAG_ROLLED_UP, 0) !== 0;
		$this->unrollAtTick = $this->rolledUp ? $this->server->getTick() + self::UNROLL_TICKS : null;

		if($nbt->getTag("Health") === null){
			$this->setHealth(12.0);
		}
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT();
		$nbt->setByte(self::TAG_ROLLED_UP, $this->rolledUp ? 1 : 0);
		return $nbt;
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);
		$properties->setGenericFlag(EntityMetadataFlags::SCARED, $this->rolledUp);
	}

	public function isRolledUp() : bool{
		return $this->rolledUp;
	}

	public function setRolledUp(bool $rolled = true) : void{
		$this->rolledUp = $rolled;
		$this->unrollAtTick = $rolled ? $this->server->getTick() + self::UNROLL_TICKS : null;
		$this->scheduleUpdate();
		$this->networkPropertiesDirty = true;
	}

	protected function getAcceptedDamage(EntityDamageEvent $source) : float{
		$damage = parent::getAcceptedDamage($source);
		return $this->rolledUp ? $damage * 0.5 : $damage;
	}

	public function attack(EntityDamageEvent $source) : void{
		parent::attack($source);

		if(!$source->isCancelled() && (
			$source->getCause() === EntityDamageEvent::CAUSE_ENTITY_ATTACK ||
			$source->getCause() === EntityDamageEvent::CAUSE_PROJECTILE
		)){
			$this->setRolledUp(true);
		}
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		if($this->unrollAtTick !== null && $this->lastUpdate >= $this->unrollAtTick){
			$this->setRolledUp(false);
		}

		return parent::entityBaseTick($tickDiff) || $this->unrollAtTick !== null;
	}

	public function onInteract(Player $player, Vector3 $clickPos) : bool{
		return $this->interact($player, $clickPos);
	}

	public function interact(Player $player, Vector3 $clickPos) : bool{
		$item = $player->getInventory()->getItemInHand();
		$brush = StringToItemParser::getInstance()->parse("brush");
		if(!$item instanceof Durable || $brush === null || $item->getTypeId() !== $brush->getTypeId() || $player->hasItemCooldown($item)){
			return false;
		}

		$player->resetItemCooldown($item, self::BRUSH_COOLDOWN_TICKS);
		if($player->hasFiniteResources() && $item->applyDamage(self::BRUSH_DURABILITY_COST)){
			$player->getInventory()->setItemInHand($item);
		}

		$this->getWorld()->dropItem($this->location, VanillaItems::SCUTE());
		$this->broadcastSound(new PopSound());
		return true;
	}

	public function getDrops() : array{
		return [
			VanillaItems::SCUTE()->setCount(mt_rand(0, 1))
		];
	}

	public function getXpDropAmount() : int{
		return mt_rand(1, 3);
	}
}
