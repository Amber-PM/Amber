<?php

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketMine Team
 * @link http://www.pocketmine.net/
 *
 *
 */

declare(strict_types=1);

namespace pocketmine\addon\entity\ai\goal;

use pocketmine\addon\entity\ai\Goal;
use pocketmine\entity\object\ItemEntity;
use pocketmine\event\entity\EntityItemPickupEvent;
use pocketmine\item\Armor;
use pocketmine\item\Bow;
use pocketmine\item\Item;
use pocketmine\item\Sword;
use pocketmine\item\TieredTool;
use pocketmine\item\StringToItemParser;
use function is_array;
use function is_string;
use function str_contains;

/**
 * minecraft:behavior.pickup_items: walk to items on the ground the mob wants (minecraft:shareables, or anything
 * with can_pickup_any_item) and take them - into its inventory, its hands or its armour slots.
 */
final class PickupItemsGoal extends Goal{
	private ?ItemEntity $target = null;
	/** @var array<int, true>|null item type ids the mob wants; null = any */
	private ?array $wanted = null;
	private bool $wantedLoaded = false;

	public function getFlags() : int{ return self::FLAG_MOVE; }

	/** @return array<int, true>|null */
	private function wanted() : ?array{
		if($this->wantedLoaded){
			return $this->wanted;
		}
		$this->wantedLoaded = true;
		if((bool) ($this->data["can_pickup_any_item"] ?? false)){
			return $this->wanted = null;
		}
		$shareables = $this->mob->getComponent("minecraft:shareables");
		$ids = [];
		foreach(is_array($shareables) && is_array($shareables["items"] ?? null) ? $shareables["items"] : [] as $entry){
			$name = is_array($entry) ? ($entry["item"] ?? null) : $entry;
			$item = is_string($name) ? StringToItemParser::getInstance()->parse(str_contains($name, ":") ? $name : "minecraft:$name") : null;
			if($item !== null){
				$ids[$item->getTypeId()] = true;
			}
		}
		if(is_array($shareables) && (bool) ($shareables["all_items"] ?? false)){
			return $this->wanted = null;
		}
		return $this->wanted = $ids;
	}

	private function wants(Item $item) : bool{
		if((bool) ($this->data["__equip"] ?? false)){
			//behavior.equip_item: armour for an empty armour slot, or a weapon or tool for empty hands
			return ($item instanceof Armor && $this->mob->getArmorInventory()->getItem($item->getArmorSlot())->isNull())
				|| (($item instanceof TieredTool || $item instanceof Sword || $item instanceof Bow) && $this->mob->getMainHandItem() === null);
		}
		$wanted = $this->wanted();
		return $wanted === null || isset($wanted[$item->getTypeId()]);
	}

	public function canStart() : bool{
		if($this->mob->isSitting() || $this->mob->getRiders() !== []){
			return false;
		}
		$radius = $this->float("max_dist", 3.0) + $this->float("goal_radius", 0.5);
		$best = null;
		$bestDist = $radius * $radius;
		foreach($this->mob->getWorld()->getNearbyEntities($this->mob->getBoundingBox()->expandedCopy($radius, 2, $radius), $this->mob) as $entity){
			if(!$entity instanceof ItemEntity || $entity->isClosed() || $entity->getPickupDelay() > 0 || !$this->wants($entity->getItem())){
				continue;
			}
			$d = $entity->getPosition()->distanceSquared($this->mob->getPosition());
			if($d < $bestDist){
				$best = $entity;
				$bestDist = $d;
			}
		}
		$this->target = $best;
		return $best !== null;
	}

	public function canContinue() : bool{
		return $this->target !== null && !$this->target->isClosed();
	}

	public function tick() : void{
		if($this->target === null){
			return;
		}
		if($this->target->getPosition()->distanceSquared($this->mob->getPosition()) > 1.5){
			$this->navigator()->moveTo($this->target->getPosition(), $this->speedMultiplier(), $this->now());
			return;
		}
		$event = new EntityItemPickupEvent($this->mob, $this->target, $this->target->getItem(), $this->mob->getInventory());
		$event->call();
		if(!$event->isCancelled() && $this->mob->takeItem($event->getItem(), (bool) ($this->data["can_pickup_to_hand_or_equipment"] ?? true))){
			$this->target->flagForDespawn();
		}
		$this->target = null;
	}

	public function stop() : void{
		$this->target = null;
		$this->navigator()->stop();
	}
}
