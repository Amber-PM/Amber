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
use pocketmine\addon\entity\EntityFilter;
use pocketmine\item\StringToItemParser;
use pocketmine\player\Player;
use function is_array;
use function is_string;
use function str_contains;

/**
 * minecraft:behavior.tempt: follow a player holding one of the listed items.
 */
final class TemptGoal extends Goal{
	private ?Player $player = null;
	/** @var array<int, true>|null */
	private ?array $items = null;
	private int $cooldownUntil = 0;

	public function getFlags() : int{ return self::FLAG_MOVE | self::FLAG_LOOK; }

	/** @return array<int, true> */
	private function items() : array{
		if($this->items === null){
			$this->items = [];
			foreach(is_array($this->data["items"] ?? null) ? $this->data["items"] : [] as $name){
				$item = is_string($name) ? StringToItemParser::getInstance()->parse(str_contains($name, ":") ? $name : "minecraft:$name") : null;
				if($item !== null){
					$this->items[$item->getTypeId()] = true;
				}
			}
		}
		return $this->items;
	}

	private function tempting(Player $player) : bool{
		$held = $player->getInventory()->getItemInHand();
		return !$held->isNull() && isset($this->items()[$held->getTypeId()]);
	}

	public function canStart() : bool{
		if($this->now() < $this->cooldownUntil || $this->items() === []){
			return false;
		}
		$radius = $this->float("within_radius", 10.0);
		$player = EntityFilter::nearestPlayer($this->mob, $radius);
		if(!$player instanceof Player || !$this->tempting($player) || $player->isSpectator()){
			return false;
		}
		$this->player = $player;
		return true;
	}

	public function canContinue() : bool{
		return $this->player !== null && !$this->player->isClosed() && $this->tempting($this->player)
			&& $this->player->getPosition()->distanceSquared($this->mob->getPosition()) < ($this->float("within_radius", 10.0) + 2) ** 2;
	}

	public function tick() : void{
		if($this->player === null){
			return;
		}
		$this->mob->lookAt($this->player->getEyePos());
		if($this->player->getPosition()->distanceSquared($this->mob->getPosition()) > 6.25){
			$this->navigator()->moveTo($this->player->getPosition(), $this->speedMultiplier(), $this->now());
		}else{
			$this->navigator()->stop();
		}
	}

	public function stop() : void{
		$this->player = null;
		$this->navigator()->stop();
		$this->cooldownUntil = $this->now() + 100;
	}
}
